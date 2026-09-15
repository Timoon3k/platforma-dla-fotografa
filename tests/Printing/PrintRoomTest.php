<?php
declare( strict_types=1 );

namespace Kadr\Tests\Printing;

use Kadr\Application\Printing\PrintRoom;
use Kadr\Domain\Shared\Ulid;
use Kadr\Infrastructure\Database\Repositories\AssetRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Infrastructure\Database\Repositories\ProductRepository;
use Kadr\Infrastructure\Database\Repositories\ProductVariantRepository;
use Kadr\Tests\Support\TestDatabase;
use Kadr\Tests\TestCase;

/**
 * Print Room na prawdziwych danych.
 *
 * Etap ④ z CLAUDE.md §1 — dziś odbitek nie sprzedaje się wcale.
 * Testy pilnują, że oferta niesie to, czego klientka nie policzy sama:
 * jak zostanie przycięta, czy plik wystarczy i ile to kosztuje.
 */
final class PrintRoomTest extends TestCase {

	public function testTheOfferCarriesCropQualityAndPriceForEveryVariant(): void {
		$w = $this->world();
		$w->catalogue();

		$offer = $w->room->forAsset( $w->photo )->value;

		$this->assertSame( 1, count( $offer['products'] ) );

		foreach ( $offer['products'][0]['variants'] as $variant ) {
			$this->assertTrue( $variant['price'] > 0, $variant['label'] );
			$this->assertNotNull( $variant['crop'], $variant['label'] );
			$this->assertTrue( '' !== $variant['crop']['trim_text'] );
			$this->assertTrue( '' !== $variant['crop']['quality_text'] );
		}
	}

	/**
	 * Zdjęcie 3:2 w 10×15 jest pełne, a w 13×18 traci boki.
	 * To jest bramka tej sesji, sprawdzona na danych z bazy.
	 */
	public function testTheGateCaseComesThroughTheWholeStack(): void {
		$w = $this->world();
		$w->catalogue();

		$variants = $w->variantsByLabel();

		$this->assertTrue( $variants['10×15']['crop']['full_frame'] );
		$this->assertSame( 0, $variants['10×15']['crop']['lost_percent'] );

		$this->assertFalse( $variants['13×18']['crop']['full_frame'] );
		$this->assertSame( 8, $variants['13×18']['crop']['lost_percent'] );
		$this->assertSame( 'sides', $variants['13×18']['crop']['trim'] );
	}

	/**
	 * Mały kadr w dużym formacie musi powiedzieć „nie".
	 */
	public function testASmallPhotoIsRefusedForTheLargestFormat(): void {
		$w = $this->world( 900, 600 );
		$w->catalogue();

		$variants = $w->variantsByLabel();

		$this->assertTrue( $variants['10×15']['crop']['printable'] );
		$this->assertFalse( $variants['50×70']['crop']['printable'] );
		$this->assertTrue( str_contains( $variants['50×70']['crop']['quality_text'], 'rozmyta' ) );
	}

	/**
	 * Wariant bez wymiarów (album) nie dostaje podglądu kadrowania —
	 * album ma liczbę stron, nie proporcje.
	 */
	public function testAVariantWithoutDimensionsHasNoCropPreview(): void {
		$w = $this->world();
		$album = $w->products->create( 'album', 'Album' );
		$w->variants->create(
			(int) $w->products->findByPublicId( $album )['id'],
			'20 stron',
			29900
		);

		$offer = $w->room->forAsset( $w->photo )->value;

		$this->assertNull( $offer['products'][0]['variants'][0]['crop'] );
	}

	/**
	 * Produkt bez czynnych wariantów nie jest ofertą. Pusta sekcja
	 * to obietnica bez pokrycia.
	 */
	public function testAProductWithoutActiveVariantsIsNotShown(): void {
		$w = $this->world();
		$id = $w->products->create( 'print', 'Odbitki' );
		$row = $w->products->findByPublicId( $id );
		$variant = $w->variants->create( (int) $row['id'], '10×15', 200, 100, 150 );

		$w->variants->update( $variant, array( 'active' => 0 ) );

		$this->assertSame( array(), $w->room->forAsset( $w->photo )->value['products'] );
	}

	public function testAnInactiveProductIsNotShownEither(): void {
		$w = $this->world();
		$w->catalogue();

		foreach ( $w->products->all() as $product ) {
			$w->products->update(
				Ulid::fromString( (string) $product['public_id'] ),
				array( 'active' => 0 )
			);
		}

		$this->assertSame( array(), $w->room->forAsset( $w->photo )->value['products'] );
	}

	/**
	 * Zdjęcie bez wymiarów czeka jeszcze na przetworzenie — nie da się
	 * policzyć ani kadru, ani rozdzielczości.
	 */
	public function testAPhotoStillBeingProcessedSaysSoInsteadOfGuessing(): void {
		$w = $this->world( 0, 0 );
		$w->catalogue();

		$result = $w->room->forAsset( $w->photo );

		$this->assertFalse( $result->ok );
		$this->assertSame( 'kadr_asset_pending', $result->code );
	}

	/**
	 * Kadr musi należeć DO TEJ galerii. Inaczej jeden link pozwalałby
	 * zamawiać odbitki z cudzej sesji tego samego fotografa.
	 */
	public function testAPhotoFromAnotherGalleryIsNotFound(): void {
		$w = $this->world();
		$w->catalogue();

		$otherGallery = $w->galleries->create( 'Inna sesja', 'inna' );
		$otherRow     = $w->galleries->findByPublicId( $otherGallery );

		$result = $w->room->forAsset( $w->photo, (int) $otherRow['id'] );

		$this->assertFalse( $result->ok );
		$this->assertSame( 'kadr_not_found', $result->code );
	}

	/**
	 * Izolacja tenantów (ryzyko R2): katalog i zdjęcia drugiego fotografa
	 * nie istnieją.
	 */
	public function testAnotherPhotographersPhotoIsInvisible(): void {
		$db    = TestDatabase::migrated();
		$mine  = $this->worldOn( $db, 1, 'moja' );
		$other = $this->worldOn( $db, 2, 'cudza' );

		$other->catalogue();

		$result = $mine->room->forAsset( $other->photo );

		$this->assertFalse( $result->ok );
		$this->assertSame( 'kadr_not_found', $result->code );
	}

	private function world( int $width = 6000, int $height = 4000 ): object {
		return $this->worldOn( TestDatabase::migrated(), 1, 'wesele', $width, $height );
	}

	private function worldOn(
		mixed $db,
		int $tenantId,
		string $slug = 'wesele',
		int $width = 6000,
		int $height = 4000
	): object {
		$tenant    = TestDatabase::tenant( $tenantId );
		$galleries = new GalleryRepository( $db, $tenant );
		$assets    = new AssetRepository( $db, $tenant );
		$products  = new ProductRepository( $db, $tenant );
		$variants  = new ProductVariantRepository( $db, $tenant );

		$gallery = $galleries->create( 'Sesja', $slug );
		$row     = $galleries->findByPublicId( $gallery );

		$photo = $assets->create(
			(int) $row['id'],
			array(
				'original_name' => 'kadr.jpg',
				'storage_path'  => "private/$slug/kadr.jpg",
				'content_hash'  => hash( 'sha256', $slug ),
				'bytes'         => 4_200_000,
				'width'         => $width,
				'height'        => $height,
				'sort_order'    => 0,
				'status'        => 'ready',
			)
		);

		return new class(
			$photo,
			$galleries,
			$products,
			$variants,
			new PrintRoom( $assets, $products, $variants )
		) {
			public function __construct(
				public Ulid $photo,
				public GalleryRepository $galleries,
				public ProductRepository $products,
				public ProductVariantRepository $variants,
				public PrintRoom $room,
			) {}

			/**
			 * Typowy cennik z polskiego rynku (skill photography-workflow §5).
			 */
			public function catalogue(): void {
				$id  = $this->products->create( 'print', 'Odbitki' );
				$row = $this->products->findByPublicId( $id );

				$formats = array(
					array( '10×15', 100, 150, 200 ),
					array( '13×18', 130, 180, 350 ),
					array( '15×21', 150, 210, 500 ),
					array( '20×30', 200, 300, 1500 ),
					array( '30×40', 300, 400, 3500 ),
					array( '50×70', 500, 700, 9900 ),
				);

				foreach ( $formats as $index => [$label, $w, $h, $price] ) {
					$this->variants->create( (int) $row['id'], $label, $price, $w, $h, 'mat', $index );
				}
			}

			/**
			 * @return array<string, array<string, mixed>>
			 */
			public function variantsByLabel(): array {
				$byLabel = array();

				foreach ( $this->room->forAsset( $this->photo )->value['products'] as $product ) {
					foreach ( $product['variants'] as $variant ) {
						$byLabel[ $variant['label'] ] = $variant;
					}
				}

				return $byLabel;
			}
		};
	}
}
