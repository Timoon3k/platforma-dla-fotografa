<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database\Repositories;

use Kadr\Infrastructure\Database\Schema\Table;
use Kadr\Infrastructure\Database\Schema\Tables;
use Kadr\Infrastructure\Database\TenantRepository;

/**
 * Sesje i magic linki klientów (ADR-003).
 *
 * W bazie leży wyłącznie hash tokenu. Sesja jest rekordem, a nie samodzielnym
 * JWT — token, którego nie da się unieważnić, jest w tym produkcie
 * nieakceptowalny (docs/SECURITY.md §5).
 */
final class ClientSessionRepository extends TenantRepository {

	public const PURPOSE_SESSION = 'session';
	public const PURPOSE_MAGIC   = 'magic_link';

	protected function table(): Table {
		return Tables::clients()[1];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function findByTokenHash( string $tokenHash ): ?array {
		return $this->findOneBy( array( 'token_hash' => $tokenHash ) );
	}

	public function create(
		int $clientId,
		string $tokenHash,
		string $purpose,
		string $expiresAt,
		?string $ipHash = null
	): int {
		return $this->insertRow(
			array(
				'client_id'  => $clientId,
				'token_hash' => $tokenHash,
				'purpose'    => $purpose,
				'ip_hash'    => $ipHash,
				'expires_at' => $expiresAt,
			)
		);
	}

	/**
	 * Oznaczenie magic linku jako wykorzystanego.
	 *
	 * Magic link jest jednorazowy: ponowne kliknięcie w ten sam link z historii
	 * przeglądarki albo z przeskanowanej skrzynki nie może zalogować.
	 */
	public function markUsed( string $tokenHash, string $usedAt ): int {
		return $this->updateBy(
			array( 'token_hash' => $tokenHash ),
			array( 'used_at' => $usedAt )
		);
	}

	public function revoke( string $tokenHash, string $revokedAt ): int {
		return $this->updateBy(
			array( 'token_hash' => $tokenHash ),
			array( 'revoked_at' => $revokedAt )
		);
	}

	/**
	 * Unieważnienie wszystkich sesji klienta — po zmianie hasła albo
	 * na żądanie fotografa.
	 */
	public function revokeAllFor( int $clientId, string $revokedAt ): int {
		return $this->updateBy(
			array( 'client_id' => $clientId, 'revoked_at' => null ),
			array( 'revoked_at' => $revokedAt )
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function activeFor( int $clientId ): array {
		return $this->findAllBy(
			array( 'client_id' => $clientId, 'revoked_at' => null ),
			'created_at',
			'DESC'
		);
	}
}
