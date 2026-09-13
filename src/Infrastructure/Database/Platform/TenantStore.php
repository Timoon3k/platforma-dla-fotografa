<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database\Platform;

use Kadr\Domain\Persistence\Database;
use Kadr\Domain\Tenancy\StudioStore;
use Kadr\Domain\Shared\Ulid;
use Kadr\Infrastructure\Database\Schema\Tables;

/**
 * Repozytorium POZA kontekstem tenanta.
 *
 * `TenantRepository` konstrukcyjnie uniemożliwia zapytanie bez `tenant_id`
 * — i słusznie, bo to na tym stoi izolacja danych. Ale tenant musi skądś
 * powstać, a w chwili rejestracji jeszcze nie istnieje.
 *
 * Dlatego jest to jedyna dopuszczona furtka i leży w osobnej przestrzeni
 * nazw `Platform\`, żeby było widać w imporcie, że kod wychodzi poza tenanta
 * (docs/ARCHITECTURE.md §2). Klasa nie wystawia ani jednej metody, która
 * czytałaby dane NALEŻĄCE do tenanta — wyłącznie jego własny wiersz
 * i przypisania użytkowników.
 */
final readonly class TenantStore implements StudioStore {

	public function __construct( private Database $db ) {}

	/**
	 * Czy slug studia jest wolny.
	 *
	 * Slug jest globalny, bo trafia do publicznych adresów rezerwacji (`/b/{studio}`).
	 */
	public function slugTaken( string $slug ): bool {
		return null !== $this->db->selectOne(
			sprintf( 'SELECT id FROM `%s` WHERE slug = ? LIMIT 1', $this->db->table( Tables::TENANTS ) ),
			array( $slug )
		);
	}

	/**
	 * Tenant, do którego należy użytkownik WordPressa.
	 *
	 * @return array<string, mixed>|null
	 */
	public function forWpUser( int $wpUserId ): ?array {
		return $this->db->selectOne(
			sprintf(
				'SELECT tenant_id, role FROM `%s` WHERE wp_user_id = ? AND deleted_at IS NULL LIMIT 1',
				$this->db->table( Tables::TENANT_USERS )
			),
			array( $wpUserId )
		);
	}

	/**
	 * Utworzenie studia wraz z przypisaniem właściciela.
	 *
	 * @return array{id: int, public_id: string}
	 */
	public function createStudio( string $name, string $slug, string $contactEmail, int $ownerWpUserId ): array {
		$publicId = (string) Ulid::generate();
		$now      = gmdate( 'Y-m-d H:i:s' );

		$tenantId = $this->db->insert(
			$this->db->table( Tables::TENANTS ),
			array(
				'public_id'     => $publicId,
				'name'          => $name,
				'slug'          => $slug,
				'status'        => 'active',
				'plan'          => 'free',
				'timezone'      => 'Europe/Warsaw',
				'currency'      => 'PLN',
				'contact_email' => $contactEmail,
				'created_at'    => $now,
				'updated_at'    => $now,
			)
		);

		$this->db->insert(
			$this->db->table( Tables::TENANT_USERS ),
			array(
				'tenant_id'   => $tenantId,
				'public_id'   => (string) Ulid::generate(),
				'wp_user_id'  => $ownerWpUserId,
				'role'        => 'owner',
				'accepted_at' => $now,
				'created_at'  => $now,
				'updated_at'  => $now,
			)
		);

		// Powstanie studia jest zdarzeniem platformy, nie tenanta — ale
		// zapisujemy je w jego historii, bo to pierwszy wpis, od którego
		// zaczyna się audyt konta (docs/ARCHITECTURE.md §2).
		$this->db->insert(
			$this->db->table( Tables::AUDIT_LOG ),
			array(
				'tenant_id'   => $tenantId,
				'action'      => 'studio.created',
				'actor_type'  => 'user',
				'actor_id'    => $ownerWpUserId,
				'entity_type' => 'tenant',
				'entity_id'   => $publicId,
				'created_at'  => $now,
				'updated_at'  => $now,
			)
		);

		return array(
			'id'        => $tenantId,
			'public_id' => $publicId,
		);
	}
}
