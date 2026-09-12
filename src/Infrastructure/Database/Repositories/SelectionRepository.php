<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database\Repositories;

use Kadr\Domain\Shared\Ulid;
use Kadr\Infrastructure\Database\Schema\Table;
use Kadr\Infrastructure\Database\Schema\Tables;
use Kadr\Infrastructure\Database\TenantRepository;

/**
 * Wybór zdjęć przez klienta — sedno produktu.
 */
final class SelectionRepository extends TenantRepository {

	protected function table(): Table {
		return Tables::selections()[0];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function forGallery( int $galleryId ): ?array {
		return $this->findOneBy( array( 'gallery_id' => $galleryId ) );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function findByPublicId( Ulid $id ): ?array {
		return $this->findOneBy( array( 'public_id' => (string) $id ) );
	}

	public function open( int $galleryId, ?int $clientId = null ): Ulid {
		$id = Ulid::generate();

		$this->insertRow(
			array(
				'public_id'  => (string) $id,
				'gallery_id' => $galleryId,
				'client_id'  => $clientId,
				'status'     => 'open',
			)
		);

		return $id;
	}

	public function submit( Ulid $id, int $includedCount, int $extraCount ): int {
		return $this->updateBy(
			array( 'public_id' => (string) $id ),
			array(
				'status'         => 'submitted',
				'included_count' => $includedCount,
				'extra_count'    => $extraCount,
				'submitted_at'   => gmdate( 'Y-m-d H:i:s' ),
			)
		);
	}

	/**
	 * Ponowne otwarcie wyboru.
	 *
	 * Klientka się rozmyśli — to nie jest przypadek brzegowy, tylko normalny
	 * bieg sprawy, więc jest osobną, wspieraną operacją.
	 */
	public function reopen( Ulid $id ): int {
		return $this->updateBy(
			array( 'public_id' => (string) $id ),
			array(
				'status'       => 'reopened',
				'submitted_at' => null,
			)
		);
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function pending(): array {
		return $this->findAllBy( array( 'status' => 'submitted' ), 'submitted_at', 'DESC' );
	}
}
