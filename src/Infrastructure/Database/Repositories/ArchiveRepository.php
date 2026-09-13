<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database\Repositories;

use Kadr\Domain\Delivery\ArchiveScope;
use Kadr\Domain\Shared\Ulid;
use Kadr\Infrastructure\Database\Schema\Table;
use Kadr\Infrastructure\Database\Schema\Tables;
use Kadr\Infrastructure\Database\TenantRepository;

/**
 * Paczki plików przygotowywane w tle.
 *
 * Wiersz jest jednocześnie stanem zadania i stanem widocznym dla fotografa:
 * „pakuję 340 z 1200". Bez tego przycisk „Przygotuj pliki" byłby kliknięciem
 * w próżnię — a pakowanie wesela trwa kilkanaście minut i fotograf zdąży
 * zamknąć kartę.
 */
final class ArchiveRepository extends TenantRepository {

	protected function table(): Table {
		return Tables::byName( Tables::ARCHIVES );
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
	public function forGallery( int $galleryId, ArchiveScope $scope ): ?array {
		return $this->findOneBy(
			array(
				'gallery_id' => $galleryId,
				'scope'      => $scope->value,
			)
		);
	}

	/**
	 * Zlecenie nowej paczki albo odświeżenie istniejącej.
	 *
	 * Jedna paczka na galerię i zakres (UNIQUE w schemacie): ponowne zlecenie
	 * zaczyna pakowanie od zera zamiast mnożyć kopie tych samych 60 GB.
	 */
	public function request( int $galleryId, ArchiveScope $scope, int $totalItems, string $storagePath ): Ulid {
		$existing = $this->forGallery( $galleryId, $scope );

		if ( null !== $existing ) {
			$this->updateBy(
				array( 'id' => (int) $existing['id'] ),
				array(
					'status'       => 'pending',
					'storage_path' => $storagePath,
					'total_items'  => $totalItems,
					'packed_items' => 0,
					'bytes'        => 0,
					'ready_at'     => null,
					'expires_at'   => null,
					'last_error'   => null,
				)
			);

			return Ulid::fromString( (string) $existing['public_id'] );
		}

		$id = Ulid::generate();

		$this->insertRow(
			array(
				'public_id'    => (string) $id,
				'gallery_id'   => $galleryId,
				'scope'        => $scope->value,
				'status'       => 'pending',
				'storage_path' => $storagePath,
				'total_items'  => $totalItems,
				'packed_items' => 0,
				'bytes'        => 0,
			)
		);

		return $id;
	}

	/**
	 * Postęp po spakowaniu porcji.
	 */
	public function advance( Ulid $id, int $packedItems, int $bytes ): int {
		return $this->updateBy(
			array( 'public_id' => (string) $id ),
			array(
				'status'       => 'packing',
				'packed_items' => $packedItems,
				'bytes'        => $bytes,
			)
		);
	}

	public function markReady( Ulid $id, int $bytes, string $expiresAt ): int {
		return $this->updateBy(
			array( 'public_id' => (string) $id ),
			array(
				'status'     => 'ready',
				'bytes'      => $bytes,
				'ready_at'   => gmdate( 'Y-m-d H:i:s' ),
				'expires_at' => $expiresAt,
				'last_error' => null,
			)
		);
	}

	public function markFailed( Ulid $id, string $error ): int {
		return $this->updateBy(
			array( 'public_id' => (string) $id ),
			array(
				'status' => 'failed',
				// Komunikat jest dla fotografa, nie dla debuggera — obcinamy,
				// żeby ślad stosu nie trafił do interfejsu ani do bazy.
				'last_error' => mb_substr( $error, 0, 300 ),
			)
		);
	}

	/**
	 * Paczki, które wygasły — do sprzątnięcia przez zadanie w tle.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function expired( string $now, int $limit = 50 ): array {
		$stale = array();

		foreach ( $this->findAllBy( array( 'status' => 'ready' ), 'id', 'ASC', $limit ) as $row ) {
			if ( is_string( $row['expires_at'] ) && $row['expires_at'] <= $now ) {
				$stale[] = $row;
			}
		}

		return $stale;
	}

	public function delete( Ulid $id ): int {
		return $this->forceDeleteBy( array( 'public_id' => (string) $id ) );
	}
}
