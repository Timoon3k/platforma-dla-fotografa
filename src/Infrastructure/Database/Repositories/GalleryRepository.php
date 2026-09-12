<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database\Repositories;

use Kadr\Domain\Shared\Ulid;
use Kadr\Infrastructure\Database\Schema\Table;
use Kadr\Infrastructure\Database\Schema\Tables;
use Kadr\Infrastructure\Database\TenantRepository;

/**
 * Galerie fotografa.
 */
final class GalleryRepository extends TenantRepository {

	protected function table(): Table {
		return Tables::galleries()[0];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function findByPublicId( Ulid $id ): ?array {
		return $this->findOneBy( array( 'public_id' => (string) $id ) );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function all( ?string $status = null, int $limit = 50, int $offset = 0 ): array {
		$conditions = null !== $status ? array( 'status' => $status ) : array();

		return $this->findAllBy( $conditions, 'created_at', 'DESC', $limit, $offset );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function forClient( int $clientId ): array {
		return $this->findAllBy( array( 'client_id' => $clientId ), 'created_at', 'DESC' );
	}

	/**
	 * Liczba galerii liczących się do limitu planu.
	 *
	 * Zarchiwizowane nie liczą się — dzięki temu archiwizacja realnie zwalnia
	 * miejsce w planie, zamiast być wyłącznie operacją na plikach (ADR-011).
	 */
	public function countActive(): int {
		return $this->countBy( array( 'lifecycle_state' => 'active' ) );
	}

	public function create( string $title, string $slug, ?int $clientId = null ): Ulid {
		$id = Ulid::generate();

		$this->insertRow(
			array(
				'public_id' => (string) $id,
				'title'     => $title,
				'slug'      => $slug,
				'client_id' => $clientId,
				'status'    => 'draft',
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

	public function publish( Ulid $id ): int {
		return $this->updateBy(
			array( 'public_id' => (string) $id ),
			array(
				'status'       => 'published',
				'published_at' => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}

	public function delete( Ulid $id ): int {
		return $this->softDeleteBy( array( 'public_id' => (string) $id ) );
	}

	public function restore( Ulid $id ): int {
		return $this->restoreBy( array( 'public_id' => (string) $id ) );
	}
}
