<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database\Repositories;

use Kadr\Domain\Shared\Ulid;
use Kadr\Infrastructure\Database\Schema\Table;
use Kadr\Infrastructure\Database\Schema\Tables;
use Kadr\Infrastructure\Database\TenantRepository;

/**
 * Warianty produktów — konkretne rzeczy do kupienia.
 *
 * „10×15, mat, 2 zł" to jeden wiersz. Formaty są danymi, nie kodem
 * (skill photography-workflow §5).
 */
final class ProductVariantRepository extends TenantRepository {

	protected function table(): Table {
		return Tables::byName( Tables::PRODUCT_VARIANTS );
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
	public function forProduct( int $productId, bool $onlyActive = false ): array {
		$conditions = array( 'product_id' => $productId );

		if ( $onlyActive ) {
			$conditions['active'] = 1;
		}

		return $this->findAllBy( $conditions, 'sort_order', 'ASC', 200 );
	}

	/**
	 * Warianty wielu produktów naraz — jedno zapytanie.
	 *
	 * Print Room pokazuje cały katalog przy jednym zdjęciu; pytanie
	 * o warianty osobno dla każdego produktu byłoby N+1
	 * (docs/PERFORMANCE.md §5).
	 *
	 * @param list<int> $productIds
	 * @return array<int, list<array<string, mixed>>> product_id => warianty
	 */
	public function forProducts( array $productIds, bool $onlyActive = true ): array {
		$grouped = array();

		foreach ( $this->findAllIn( 'product_id', $productIds, array(), 'sort_order', 'ASC' ) as $row ) {
			if ( $onlyActive && 1 !== (int) $row['active'] ) {
				continue;
			}

			$grouped[ (int) $row['product_id'] ][] = $row;
		}

		return $grouped;
	}

	public function create(
		int $productId,
		string $label,
		int $price,
		?int $widthMm = null,
		?int $heightMm = null,
		?string $paper = null,
		int $sortOrder = 0
	): Ulid {
		$id = Ulid::generate();

		$this->insertRow(
			array(
				'public_id'  => (string) $id,
				'product_id' => $productId,
				'label'      => $label,
				'width_mm'   => $widthMm,
				'height_mm'  => $heightMm,
				'paper'      => $paper,
				'price'      => $price,
				'active'     => 1,
				'sort_order' => $sortOrder,
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

	public function countForProduct( int $productId ): int {
		return $this->countBy( array( 'product_id' => $productId ) );
	}
}
