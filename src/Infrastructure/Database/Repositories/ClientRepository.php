<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database\Repositories;

use Kadr\Domain\Shared\Ulid;
use Kadr\Infrastructure\Database\Schema\Table;
use Kadr\Infrastructure\Database\Schema\Tables;
use Kadr\Infrastructure\Database\TenantRepository;

/**
 * Klienci fotografa.
 *
 * Klienci żyją poza `wp_users` (ADR-003): ta sama osoba może być klientką
 * dwóch różnych fotografów, a `wp_users.user_email` jest unikalny globalnie.
 */
final class ClientRepository extends TenantRepository {

	protected function table(): Table {
		return Tables::clients()[0];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function findByPublicId( Ulid $id ): ?array {
		return $this->findOneBy( array( 'public_id' => (string) $id ) );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function findByEmail( string $email ): ?array {
		return $this->findOneBy( array( 'email' => strtolower( trim( $email ) ) ) );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function all( int $limit = 50, int $offset = 0 ): array {
		return $this->findAllBy( array(), 'last_name', 'ASC', $limit, $offset );
	}

	public function count(): int {
		return $this->countBy();
	}

	public function create( string $firstName, string $email, ?string $lastName = null, ?string $phone = null ): Ulid {
		$id = Ulid::generate();

		$this->insertRow(
			array(
				'public_id'  => (string) $id,
				'first_name' => $firstName,
				'last_name'  => $lastName,
				'email'      => strtolower( trim( $email ) ),
				'phone'      => $phone,
			)
		);

		return $id;
	}

	/**
	 * @param array<string, scalar|null> $data
	 */
	public function update( Ulid $id, array $data ): int {
		return $this->updateBy( array( 'public_id' => (string) $id ), $data );
	}

	public function delete( Ulid $id ): int {
		return $this->softDeleteBy( array( 'public_id' => (string) $id ) );
	}

	public function restore( Ulid $id ): int {
		return $this->restoreBy( array( 'public_id' => (string) $id ) );
	}
}
