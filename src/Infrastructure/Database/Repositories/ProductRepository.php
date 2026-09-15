<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database\Repositories;

use Kadr\Domain\Shared\Ulid;
use Kadr\Infrastructure\Database\Schema\Table;
use Kadr\Infrastructure\Database\Schema\Tables;
use Kadr\Infrastructure\Database\TenantRepository;

/**
 * Produkty w katalogu fotografa.
 */
final class ProductRepository extends TenantRepository {

	protected function table(): Table {
		return Tables::byName( Tables::PRODUCTS );
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
	public function findById( int $id ): ?array {
		return $this->findOneBy( array( 'id' => $id ) );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function all( bool $onlyActive = false ): array {
		$conditions = $onlyActive ? array( 'active' => 1 ) : array();

		return $this->findAllBy( $conditions, 'sort_order', 'ASC', 100 );
	}

	public function create( string $type, string $name, string $description = '', int $sortOrder = 0 ): Ulid {
		$id = Ulid::generate();

		$this->insertRow(
			array(
				'public_id'   => (string) $id,
				'type'        => $type,
				'name'        => $name,
				'description' => '' === $description ? null : $description,
				'active'      => 1,
				'sort_order'  => $sortOrder,
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
}
