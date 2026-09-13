<?php
declare( strict_types=1 );

namespace Kadr\Tests\Gallery;

use Kadr\Application\Gallery\ArrangeGallery;
use Kadr\Domain\Shared\Ulid;
use Kadr\Infrastructure\Database\Repositories\AssetRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Tests\Support\TestDatabase;
use Kadr\Tests\TestCase;

/**
 * Układanie kolejności zdjęć.
 *
 * Klientka ogląda galerię od góry i pierwsze dwadzieścia kadrów decyduje,
 * czy przewinie dalej — więc kolejność jest funkcją produktu, nie kosmetyką.
 */
final class ArrangeGalleryTest extends TestCase {

	public function testMovingOnePhotoBeforeAnotherPutsItThere(): void {
		$world = $this->world( 5 );

		$world->arrange->move( $world->gallery, array( $world->assets[3] ), $world->assets[1] );

		$this->assertSame( array( 0, 3, 1, 2, 4 ), $world->order() );
	}

	/**
	 * Zaznaczenie wielu kadrów zachowuje ich wzajemną kolejność.
	 */
	public function testMovingSeveralPhotosKeepsTheirOrder(): void {
		$world = $this->world( 6 );

		$world->arrange->move(
			$world->gallery,
			array( $world->assets[4], $world->assets[5] ),
			$world->assets[1]
		);

		$this->assertSame( array( 0, 4, 5, 1, 2, 3 ), $world->order() );
	}

	/**
	 * Najczęstszy ruch w praktyce: wybrane kadry na sam początek galerii.
	 */
	public function testMovingToTheFrontOfTheGallery(): void {
		$world = $this->world( 5 );

		$world->arrange->move(
			$world->gallery,
			array( $world->assets[2], $world->assets[4] ),
			$world->assets[0]
		);

		$this->assertSame( array( 2, 4, 0, 1, 3 ), $world->order() );
	}

	/**
	 * Brak `before` oznacza koniec galerii.
	 */
	public function testMovingWithoutATargetPutsPhotosAtTheEnd(): void {
		$world = $this->world( 4 );

		$world->arrange->move( $world->gallery, array( $world->assets[0] ), null );

		$this->assertSame( array( 1, 2, 3, 0 ), $world->order() );
	}

	/**
	 * Zapisujemy tylko wiersze, które faktycznie się przesunęły.
	 *
	 * Fotograf układa galerię dziesiątkami ruchów pod rząd; przepisywanie
	 * tysiąca wierszy przy każdym przeciągnięciu byłoby marnotrawstwem.
	 */
	public function testOnlyRowsThatActuallyMovedAreWritten(): void {
		$world = $this->world( 10 );

		$result = $world->arrange->move( $world->gallery, array( $world->assets[8] ), $world->assets[6] );

		// Przesunięcie 8 → przed 6 zmienia pozycje kadrów 6, 7 i 8. Nic więcej.
		$this->assertSame( 3, $result->value['moved'] );
	}

	public function testMovingAPhotoOntoItselfChangesNothing(): void {
		$world = $this->world( 4 );

		$result = $world->arrange->move( $world->gallery, array( $world->assets[2] ), $world->assets[2] );

		$this->assertTrue( $result->ok );
		$this->assertSame( 0, $result->value['moved'] );
		$this->assertSame( array( 0, 1, 2, 3 ), $world->order() );
	}

	/**
	 * Zdjęcie z innej galerii nie może przestawić tej.
	 *
	 * Bez tej kontroli jedno żądanie zerowałoby `sort_order` w cudzej sesji
	 * tego samego fotografa.
	 */
	public function testAPhotoFromAnotherGalleryIsRejected(): void {
		$db     = TestDatabase::migrated();
		$first  = $this->worldOn( $db, 3, 'pierwsza' );
		$second = $this->worldOn( $db, 3, 'druga' );

		$result = $first->arrange->move( $first->gallery, array( $second->assets[0] ), $first->assets[0] );

		$this->assertFalse( $result->ok );
		$this->assertSame( 'kadr_not_found', $result->code );
		// Kolejność w obu galeriach zostaje nietknięta.
		$this->assertSame( array( 0, 1, 2 ), $first->order() );
		$this->assertSame( array( 0, 1, 2 ), $second->order() );
	}

	public function testEmptySelectionIsRejected(): void {
		$world = $this->world( 3 );

		$result = $world->arrange->move( $world->gallery, array(), null );

		$this->assertFalse( $result->ok );
		$this->assertSame( 'kadr_nothing_to_move', $result->code );
	}

	public function testAnUnknownTargetIsRejected(): void {
		$world = $this->world( 3 );

		$result = $world->arrange->move( $world->gallery, array( $world->assets[0] ), Ulid::generate() );

		$this->assertFalse( $result->ok );
		$this->assertSame( 'kadr_not_found', $result->code );
	}

	/**
	 * Izolacja tenantów (ryzyko R2): galeria drugiego fotografa nie istnieje.
	 */
	public function testAnotherPhotographersGalleryCannotBeRearranged(): void {
		$db    = TestDatabase::migrated();
		$mine  = $this->worldOn( $db, 3, 'moja', 1 );
		$other = $this->worldOn( $db, 3, 'cudza', 2 );

		$result = $mine->arrange->move( $other->gallery, array( $other->assets[0] ), null );

		$this->assertFalse( $result->ok );
		$this->assertSame( 'kadr_not_found', $result->code );
		$this->assertSame( array( 0, 1, 2 ), $other->order() );
	}

	private function world( int $photos ): object {
		return $this->worldOn( TestDatabase::migrated(), $photos );
	}

	private function worldOn( object $db, int $photos, string $slug = 'galeria', int $tenantId = 1 ): object {
		$tenant    = TestDatabase::tenant( $tenantId );
		$galleries = new GalleryRepository( $db, $tenant );
		$assets    = new AssetRepository( $db, $tenant );

		$gallery = $galleries->create( 'Galeria', $slug );
		$row     = $galleries->findByPublicId( $gallery );
		$ids     = array();

		for ( $i = 0; $i < $photos; $i++ ) {
			$ids[] = $assets->create(
				(int) $row['id'],
				array(
					'original_name' => "kadr-$i.jpg",
					'storage_path'  => "private/$slug/kadr-$i.jpg",
					'content_hash'  => hash( 'sha256', "$slug-$i" ),
					'bytes'         => 2048,
					'width'         => 3000,
					'height'        => 2000,
					'sort_order'    => $i,
				)
			);
		}

		return new class(
			$gallery,
			(int) $row['id'],
			$ids,
			$assets,
			new ArrangeGallery( $galleries, $assets )
		) {
			/**
			 * @param list<Ulid> $assets
			 */
			public function __construct(
				public Ulid $gallery,
				public int $galleryRowId,
				public array $assets,
				public AssetRepository $repository,
				public ArrangeGallery $arrange,
			) {}

			/**
			 * Kolejność wyrażona pierwotnymi numerami kadrów — czytelniej
			 * niż ciąg dwudziestosześcioznakowych ULID-ów.
			 *
			 * @return list<int>
			 */
			public function order(): array {
				$positions = array_flip( array_map( 'strval', $this->assets ) );

				return array_map(
					fn( string $id ): int => $positions[ $id ],
					$this->repository->orderedIdsFor( $this->galleryRowId )
				);
			}
		};
	}
}
