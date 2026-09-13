<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database\Repositories;

use Kadr\Infrastructure\Database\Schema\Table;
use Kadr\Infrastructure\Database\Schema\Tables;
use Kadr\Infrastructure\Database\TenantRepository;

/**
 * Warianty zdjęć — sześć plików na kadr (ADR-011).
 */
final class AssetVariantRepository extends TenantRepository {

	protected function table(): Table {
		return Tables::galleries()[2];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function forAsset( int $assetId ): array {
		return $this->findAllBy( array( 'asset_id' => $assetId ), 'width', 'ASC' );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	/**
	 * Warianty dla wielu zdjęć naraz, pogrupowane po zdjęciu.
	 *
	 * Siatka pokazuje sto kadrów na stronę; pytanie o warianty każdego
	 * z osobna to sto zapytań zamiast jednego.
	 *
	 * @param list<int> $assetIds
	 * @return array<int, list<array<string, mixed>>>
	 */
	public function forAssets( array $assetIds ): array {
		$grouped = array();

		foreach ( $this->findAllIn( 'asset_id', $assetIds ) as $row ) {
			$grouped[ (int) $row['asset_id'] ][] = $row;
		}

		return $grouped;
	}

	public function find( int $assetId, string $variant, string $format ): ?array {
		return $this->findOneBy(
			array(
				'asset_id' => $assetId,
				'variant'  => $variant,
				'format'   => $format,
			)
		);
	}

	/**
	 * Zapis wariantu. Ponowne przetworzenie tego samego zdjęcia nadpisuje
	 * istniejący wiersz zamiast tworzyć duplikat — zadanie w kolejce może
	 * zostać ponowione po awarii.
	 */
	public function upsert(
		int $assetId,
		string $variant,
		string $format,
		string $storagePath,
		int $bytes,
		int $width
	): void {
		$existing = $this->find( $assetId, $variant, $format );

		if ( null !== $existing ) {
			$this->updateBy(
				array( 'asset_id' => $assetId, 'variant' => $variant, 'format' => $format ),
				array( 'storage_path' => $storagePath, 'bytes' => $bytes, 'width' => $width )
			);

			return;
		}

		$this->insertRow(
			array(
				'asset_id'     => $assetId,
				'variant'      => $variant,
				'format'       => $format,
				'storage_path' => $storagePath,
				'bytes'        => $bytes,
				'width'        => $width,
			)
		);
	}

	public function deleteForAsset( int $assetId ): int {
		return $this->forceDeleteBy( array( 'asset_id' => $assetId ) );
	}

	public function countForAsset( int $assetId ): int {
		return $this->countBy( array( 'asset_id' => $assetId ) );
	}
}
