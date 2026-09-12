<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database\Repositories;

use Kadr\Domain\Selection\SelectionState;
use Kadr\Infrastructure\Database\Schema\Table;
use Kadr\Infrastructure\Database\Schema\Tables;
use Kadr\Infrastructure\Database\TenantRepository;

/**
 * Pozycje wyboru: ulubione, wybrane, odrzucone.
 */
final class SelectionItemRepository extends TenantRepository {

	protected function table(): Table {
		return Tables::selections()[1];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function forSelection( int $selectionId, ?SelectionState $state = null ): array {
		$conditions = array( 'selection_id' => $selectionId );

		if ( null !== $state ) {
			$conditions['state'] = $state->value;
		}

		return $this->findAllBy( $conditions, 'position', 'ASC' );
	}

	public function countInState( int $selectionId, SelectionState $state ): int {
		return $this->countBy(
			array(
				'selection_id' => $selectionId,
				'state'        => $state->value,
			)
		);
	}

	/**
	 * Ustawia stan zdjęcia w wyborze.
	 *
	 * Klucz unikalny (selection_id, asset_id) gwarantuje brak podwójnego wpisu
	 * dla tego samego kadru nawet przy równoległych żądaniach z dwóch urządzeń.
	 */
	public function setState( int $selectionId, int $assetId, SelectionState $state, int $position = 0 ): void {
		$existing = $this->findOneBy(
			array(
				'selection_id' => $selectionId,
				'asset_id'     => $assetId,
			)
		);

		if ( null !== $existing ) {
			$this->updateBy(
				array(
					'selection_id' => $selectionId,
					'asset_id'     => $assetId,
				),
				array( 'state' => $state->value )
			);

			return;
		}

		$this->insertRow(
			array(
				'selection_id' => $selectionId,
				'asset_id'     => $assetId,
				'state'        => $state->value,
				'position'     => $position,
			)
		);
	}

	public function clear( int $selectionId, int $assetId ): int {
		return $this->forceDeleteBy(
			array(
				'selection_id' => $selectionId,
				'asset_id'     => $assetId,
			)
		);
	}
}
