<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database\Repositories;

use Kadr\Domain\Audit\AuditEvent;
use Kadr\Infrastructure\Database\Schema\Table;
use Kadr\Infrastructure\Database\Schema\Tables;
use Kadr\Infrastructure\Database\TenantRepository;

/**
 * Dziennik zdarzeń — kto, co, kiedy.
 *
 * Rejestr, do którego sięga się w dwóch sytuacjach: gdy klientka pyta, kto
 * pobrał jej zdjęcia, i gdy trzeba odtworzyć, co się stało z galerią.
 * Obie to sytuacje, w których „nie wiemy" jest złą odpowiedzią.
 *
 * Czego tu NIE zapisujemy (docs/SECURITY.md §5): tokenów, haseł, sekretów
 * ani pełnych adresów IP. Adres idzie wyłącznie jako hash z solą — wystarczy,
 * żeby zobaczyć „to samo źródło pobrało sto razy", a nie wystarczy, żeby
 * wskazać osobę.
 */
final class AuditLogRepository extends TenantRepository {

	protected function table(): Table {
		return Tables::byName( Tables::AUDIT_LOG );
	}

	/**
	 * @param array<string, scalar|null|array<mixed>> $changes
	 */
	public function record(
		AuditEvent $event,
		string $actorType = 'system',
		?int $actorId = null,
		?string $entityType = null,
		?string $entityId = null,
		array $changes = array(),
		?string $ipHash = null
	): void {
		$this->insertRow(
			array(
				'action'      => $event->value,
				'actor_type'  => $actorType,
				'actor_id'    => $actorId,
				'entity_type' => $entityType,
				'entity_id'   => $entityId,
				// Zwykłe `json_encode`, nie `wp_json_encode`: dziennik musi
				// dać się przetestować bez ładowania WordPressa.
				'changes'     => array() === $changes ? null : json_encode( $changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				'ip_hash'     => $ipHash,
			)
		);
	}

	/**
	 * Historia jednej encji — galerii, wyboru, klientki.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function forEntity( string $entityType, string $entityId, int $limit = 50 ): array {
		return $this->findAllBy(
			array(
				'entity_type' => $entityType,
				'entity_id'   => $entityId,
			),
			'id',
			'DESC',
			$limit
		);
	}

	/**
	 * Ostatnie zdarzenia tenanta.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function latest( int $limit = 50 ): array {
		return $this->findAllBy( array(), 'id', 'DESC', $limit );
	}

	public function countFor( AuditEvent $event ): int {
		return $this->countBy( array( 'action' => $event->value ) );
	}
}
