<?php
declare( strict_types=1 );

namespace Kadr\Tests\Selection;

use Kadr\Application\Selection\SelectionRoom;
use Kadr\Domain\Selection\SelectionState;
use Kadr\Domain\Shared\Ulid;
use Kadr\Infrastructure\Database\Repositories\AssetRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Infrastructure\Database\Repositories\SelectionItemRepository;
use Kadr\Infrastructure\Database\Repositories\SelectionRepository;
use Kadr\Tests\Support\TestDatabase;
use Kadr\Tests\TestCase;

/**
 * Wybór zdjęć i rozliczenie pakietu.
 *
 * To jest etap, na którym produkt zarabia, więc testy opisują REGUŁY PRODUKTU,
 * a nie kształt API: kiedy powstaje dopłata, kiedy nie, i co się dzieje,
 * gdy klientka się rozmyśli.
 */
final class SelectionRoomTest extends TestCase {

	/**
	 * Scenariusz z bramki sesji: pakiet 20, wybrane 28, cena 60 zł.
	 */
	public function testTwentyEightSelectedInAPackageOfTwentyCostsFourHundredEighty(): void {
		$world = $this->world( 20, 6000, 30 );

		foreach ( array_slice( $world->assets, 0, 28 ) as $asset ) {
			$world->room->mark( $world->gallery, $asset, SelectionState::Selected );
		}

		$tally = $world->room->state( $world->gallery )->value['tally'];

		$this->assertSame( 28, $tally['selected'] );
		$this->assertSame( 20, $tally['included'] );
		$this->assertSame( 8, $tally['extra'] );
		// 8 × 60 zł = 480 zł, w groszach.
		$this->assertSame( 48000, $tally['total'] );
		$this->assertTrue( $tally['needs_payment'] );
	}

	/**
	 * Dopłata pojawia się dopiero PONAD pakietem, nie od pierwszego zdjęcia.
	 */
	public function testSelectionWithinThePackageCostsNothing(): void {
		$world = $this->world( 20, 6000, 30 );

		foreach ( array_slice( $world->assets, 0, 20 ) as $asset ) {
			$world->room->mark( $world->gallery, $asset, SelectionState::Selected );
		}

		$tally = $world->room->state( $world->gallery )->value['tally'];

		$this->assertSame( 0, $tally['extra'] );
		$this->assertSame( 0, $tally['total'] );
		$this->assertFalse( $tally['needs_payment'] );
		// Dokładnie na granicy — moment, w którym warto uprzedzić klientkę.
		$this->assertTrue( $tally['at_limit'] );
		$this->assertSame( 0, $tally['remaining'] );
	}

	public function testRemainingCountsDownWhileChoosing(): void {
		$world = $this->world( 20, 6000, 30 );

		$world->room->mark( $world->gallery, $world->assets[0], SelectionState::Selected );
		$world->room->mark( $world->gallery, $world->assets[1], SelectionState::Selected );

		$this->assertSame( 18, $world->room->state( $world->gallery )->value['tally']['remaining'] );
	}

	/**
	 * Ulubione to NIE to samo, co wybrane.
	 *
	 * Klientka najpierw przechodzi galerię i serduszkuje, a dopiero potem
	 * zawęża. Gdyby serduszko liczyło się do pakietu, musiałaby podejmować
	 * decyzję zakupową przy pierwszym przejrzeniu.
	 */
	public function testFavouritesDoNotCountTowardsThePackage(): void {
		$world = $this->world( 20, 6000, 30 );

		foreach ( array_slice( $world->assets, 0, 25 ) as $asset ) {
			$world->room->mark( $world->gallery, $asset, SelectionState::Favorite );
		}

		$state = $world->room->state( $world->gallery )->value;

		$this->assertSame( 0, $state['tally']['selected'] );
		$this->assertSame( 0, $state['tally']['total'] );
		$this->assertSame( 25, $state['favorites'] );
	}

	public function testRejectedDoesNotCountEither(): void {
		$world = $this->world( 20, 6000, 30 );

		$world->room->mark( $world->gallery, $world->assets[0], SelectionState::Rejected );

		$state = $world->room->state( $world->gallery )->value;

		$this->assertSame( 0, $state['tally']['selected'] );
		$this->assertSame( 1, $state['rejected'] );
	}

	/**
	 * Kliknięcie w zaznaczone zdjęcie ma je odznaczać.
	 */
	public function testClearingAStateRemovesItFromTheTally(): void {
		$world = $this->world( 20, 6000, 30 );

		$world->room->mark( $world->gallery, $world->assets[0], SelectionState::Selected );
		$this->assertSame( 1, $world->room->state( $world->gallery )->value['tally']['selected'] );

		$world->room->mark( $world->gallery, $world->assets[0], null );
		$this->assertSame( 0, $world->room->state( $world->gallery )->value['tally']['selected'] );
	}

	public function testChangingStateDoesNotDuplicateTheEntry(): void {
		$world = $this->world( 20, 6000, 30 );

		$world->room->mark( $world->gallery, $world->assets[0], SelectionState::Favorite );
		$world->room->mark( $world->gallery, $world->assets[0], SelectionState::Selected );
		$world->room->mark( $world->gallery, $world->assets[0], SelectionState::Rejected );

		$state = $world->room->state( $world->gallery )->value;

		$this->assertSame( 0, $state['tally']['selected'] );
		$this->assertSame( 0, $state['favorites'] );
		$this->assertSame( 1, $state['rejected'] );
	}

	/**
	 * Pakiet bez limitu nie generuje dopłaty nigdy.
	 */
	public function testUnlimitedPackageNeverProducesASurcharge(): void {
		$world = $this->world( null, 6000, 30 );

		foreach ( $world->assets as $asset ) {
			$world->room->mark( $world->gallery, $asset, SelectionState::Selected );
		}

		$tally = $world->room->state( $world->gallery )->value['tally'];

		$this->assertSame( 30, $tally['selected'] );
		$this->assertSame( 0, $tally['extra'] );
		$this->assertSame( 0, $tally['total'] );
		$this->assertNull( $tally['remaining'] );
	}

	/**
	 * Zatwierdzenie zapisuje liczby POLICZONE NA SERWERZE.
	 */
	public function testSubmitStoresTheCountsItComputedItself(): void {
		$world = $this->world( 20, 6000, 30 );

		foreach ( array_slice( $world->assets, 0, 24 ) as $asset ) {
			$world->room->mark( $world->gallery, $asset, SelectionState::Selected );
		}

		$submitted = $world->room->submit( $world->gallery );

		$this->assertTrue( $submitted->ok );
		$this->assertSame( 'submitted', $submitted->value['status'] );

		$row = $world->selections->forGallery( $world->galleryRow['id'] );

		$this->assertSame( 'submitted', (string) $row['status'] );
		$this->assertSame( 20, (int) $row['included_count'] );
		$this->assertSame( 4, (int) $row['extra_count'] );
		$this->assertTrue( null !== $row['submitted_at'] );
	}

	public function testEmptySelectionCannotBeSubmitted(): void {
		$world = $this->world( 20, 6000, 30 );

		$world->room->mark( $world->gallery, $world->assets[0], SelectionState::Favorite );

		$result = $world->room->submit( $world->gallery );

		$this->assertTrue( $result->isFailure() );
		$this->assertSame( 'kadr_selection_empty', $result->code );
	}

	/**
	 * Po zatwierdzeniu wybór jest zamknięty — inaczej klientka zmienia go
	 * po tym, jak fotograf zaczął obróbkę.
	 */
	public function testSubmittedSelectionIsClosedForChanges(): void {
		$world = $this->world( 20, 6000, 30 );

		$world->room->mark( $world->gallery, $world->assets[0], SelectionState::Selected );
		$world->room->submit( $world->gallery );

		$result = $world->room->mark( $world->gallery, $world->assets[1], SelectionState::Selected );

		$this->assertTrue( $result->isFailure() );
		$this->assertSame( 'kadr_selection_closed', $result->code );
		$this->assertSame( 1, $world->room->state( $world->gallery )->value['tally']['selected'] );
	}

	public function testSubmittingTwiceIsRefused(): void {
		$world = $this->world( 20, 6000, 30 );

		$world->room->mark( $world->gallery, $world->assets[0], SelectionState::Selected );
		$world->room->submit( $world->gallery );

		$this->assertSame( 'kadr_selection_closed', $world->room->submit( $world->gallery )->code );
	}

	/**
	 * Klientka zawsze się rozmyśli — to normalny bieg sprawy.
	 */
	public function testPhotographerCanReopenAndTheChoicesSurvive(): void {
		$world = $this->world( 20, 6000, 30 );

		foreach ( array_slice( $world->assets, 0, 22 ) as $asset ) {
			$world->room->mark( $world->gallery, $asset, SelectionState::Selected );
		}

		$world->room->submit( $world->gallery );
		$world->room->reopen( $world->gallery );

		$state = $world->room->state( $world->gallery )->value;

		$this->assertSame( 'reopened', $state['status'] );
		// Wybór nie znika — fotograf otwiera go, żeby klientka coś poprawiła,
		// a nie żeby zaczynała od zera.
		$this->assertSame( 22, $state['tally']['selected'] );

		$this->assertTrue( $world->room->mark( $world->gallery, $world->assets[22], SelectionState::Selected )->ok );
		$this->assertSame( 23, $world->room->state( $world->gallery )->value['tally']['selected'] );
	}

	/**
	 * Kadr z innej galerii nie da się zaznaczyć tym linkiem.
	 */
	public function testAssetFromAnotherGalleryIsRefused(): void {
		$db    = TestDatabase::migrated();
		$world = $this->worldOn( $db, 20, 6000, 5 );
		$other = $this->worldOn( $db, 20, 6000, 5, 'inna' );

		$result = $world->room->mark( $world->gallery, $other->assets[0], SelectionState::Selected );

		$this->assertTrue( $result->isFailure() );
		$this->assertSame( 'kadr_not_found', $result->code );
	}

	/**
	 * Wybór jednego fotografa jest niewidoczny dla drugiego.
	 */
	public function testSelectionNeverCrossesTenants(): void {
		$db    = TestDatabase::migrated();
		$mine  = $this->worldOn( $db, 20, 6000, 5, 'moja', 1 );
		$other = $this->worldOn( $db, 20, 6000, 5, 'obca', 2 );

		$mine->room->mark( $mine->gallery, $mine->assets[0], SelectionState::Selected );

		$this->assertSame( 1, $mine->room->state( $mine->gallery )->value['tally']['selected'] );
		$this->assertSame( 'kadr_not_found', $other->room->state( $mine->gallery )->code );
	}

	private function world( ?int $packageLimit, int $price, int $photos ): object {
		return $this->worldOn( TestDatabase::migrated(), $packageLimit, $price, $photos );
	}

	private function worldOn(
		object $db,
		?int $packageLimit,
		int $price,
		int $photos,
		string $slug = 'wesele',
		int $tenantId = 1
	): object {
		$tenant     = TestDatabase::tenant( $tenantId );
		$galleries  = new GalleryRepository( $db, $tenant );
		$assets     = new AssetRepository( $db, $tenant );
		$selections = new SelectionRepository( $db, $tenant );
		$items      = new SelectionItemRepository( $db, $tenant );

		$gallery = $galleries->create( 'Wesele', $slug );
		$galleries->update(
			$gallery,
			array(
				'package_limit'     => $packageLimit,
				'extra_photo_price' => $price,
			)
		);

		$galleryRow = $galleries->findByPublicId( $gallery );
		$ids        = array();

		for ( $i = 0; $i < $photos; $i++ ) {
			$ids[] = $assets->create(
				(int) $galleryRow['id'],
				array(
					'original_name' => "kadr-$i.jpg",
					'storage_path'  => "private/$slug/kadr-$i.jpg",
					'content_hash'  => str_repeat( dechex( $i % 16 ), 64 ),
					'bytes'         => 2048,
					'width'         => 3000,
					'height'        => 2000,
					'sort_order'    => $i,
				)
			);
		}

		return new class(
			$gallery,
			$galleryRow,
			$ids,
			$selections,
			new SelectionRoom( $galleries, $assets, $selections, $items )
		) {
			/**
			 * @param list<Ulid> $assets
			 */
			public function __construct(
				public Ulid $gallery,
				public array $galleryRow,
				public array $assets,
				public SelectionRepository $selections,
				public SelectionRoom $room,
			) {}
		};
	}
}
