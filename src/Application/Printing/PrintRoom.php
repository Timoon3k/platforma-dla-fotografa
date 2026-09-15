<?php
declare( strict_types=1 );

namespace Kadr\Application\Printing;

use Kadr\Domain\Printing\CropPreview;
use Kadr\Domain\Printing\PrintFormat;
use Kadr\Domain\Printing\PrintQuality;
use Kadr\Domain\Shared\Result;
use Kadr\Domain\Shared\Ulid;
use Kadr\Infrastructure\Database\Repositories\AssetRepository;
use Kadr\Infrastructure\Database\Repositories\ProductRepository;
use Kadr\Infrastructure\Database\Repositories\ProductVariantRepository;

/**
 * Print Room — co klientka może zamówić z TEGO kadru i jak to będzie wyglądać.
 *
 * ETAP ④ Z CLAUDE.md §1: dziś odbitek nie sprzedaje się wcale. Klientka
 * dostaje pliki i na tym współpraca się kończy, choć połowa z nich chętnie
 * powiesiłaby coś na ścianie — po prostu nikt jej tego nie zaproponował
 * w momencie, w którym patrzy na swoje zdjęcia.
 *
 * Każda pozycja w odpowiedzi niesie TRZY rzeczy, których klientka nie
 * policzy sama:
 *
 *  1. **jak zostanie przycięta** — bo zdjęcie 3:2 w 13×18 straci boki,
 *     a reklamację za obciętą głowę dostanie fotograf (skill §5);
 *  2. **czy plik wystarczy** — bo różnicy między ostrą a rozmytą odbitką
 *     nie widać na ekranie telefonu;
 *  3. **ile to kosztuje**.
 *
 * Bez pierwszych dwóch to jest sklep, który sprzedaje reklamacje.
 */
final readonly class PrintRoom {

	public function __construct(
		private AssetRepository $assets,
		private ProductRepository $products,
		private ProductVariantRepository $variants,
	) {}

	/**
	 * Oferta dla jednego kadru.
	 *
	 * @param int|null $galleryId Gdy podany, kadr musi należeć do TEJ galerii
	 *                            — inaczej jeden link pozwalałby zamawiać
	 *                            odbitki z cudzej sesji tego samego fotografa.
	 */
	public function forAsset( Ulid $assetId, ?int $galleryId = null ): Result {
		$asset = $this->assets->findByPublicId( $assetId );

		if ( null === $asset ) {
			return Result::failure( 'kadr_not_found', 'Nie znaleziono zdjęcia.' );
		}

		if ( null !== $galleryId && (int) $asset['gallery_id'] !== $galleryId ) {
			return Result::failure( 'kadr_not_found', 'Nie znaleziono zdjęcia.' );
		}

		$width  = (int) $asset['width'];
		$height = (int) $asset['height'];

		if ( $width <= 0 || $height <= 0 ) {
			// Zdjęcie bez wymiarów czeka jeszcze na przetworzenie — nie da
			// się policzyć ani kadru, ani rozdzielczości.
			return Result::failure( 'kadr_asset_pending', 'To zdjęcie jest jeszcze przygotowywane.' );
		}

		$products = $this->products->all( true );

		if ( array() === $products ) {
			return Result::success( array( 'products' => array() ) );
		}

		$byProduct = $this->variants->forProducts(
			array_map( static fn( array $p ): int => (int) $p['id'], $products )
		);

		$offer = array();

		foreach ( $products as $product ) {
			$items = $this->variantsFor( $byProduct[ (int) $product['id'] ] ?? array(), $width, $height );

			// Produkt bez czynnych wariantów nie jest ofertą — pokazywanie
			// pustej sekcji to obietnica bez pokrycia.
			if ( array() === $items ) {
				continue;
			}

			$offer[] = array(
				'id'          => (string) $product['public_id'],
				'type'        => (string) $product['type'],
				'name'        => (string) $product['name'],
				'description' => (string) ( $product['description'] ?? '' ),
				'variants'    => $items,
			);
		}

		return Result::success(
			array(
				'photo'    => array(
					'id'     => (string) $asset['public_id'],
					'width'  => $width,
					'height' => $height,
				),
				'products' => $offer,
			)
		);
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 * @return list<array<string, mixed>>
	 */
	private function variantsFor( array $rows, int $width, int $height ): array {
		$items = array();

		foreach ( $rows as $row ) {
			$item = array(
				'id'    => (string) $row['public_id'],
				'label' => (string) $row['label'],
				'paper' => $row['paper'],
				'price' => (int) $row['price'],
				'crop'  => null,
			);

			$widthMm  = null === $row['width_mm'] ? 0 : (int) $row['width_mm'];
			$heightMm = null === $row['height_mm'] ? 0 : (int) $row['height_mm'];

			// Podgląd kadrowania ma sens tylko tam, gdzie wariant ma format.
			// Album nie ma proporcji w tym sensie — ma liczbę stron.
			if ( $widthMm > 0 && $heightMm > 0 ) {
				$item['crop'] = $this->cropFor( $widthMm, $heightMm, $width, $height );
			}

			$items[] = $item;
		}

		return $items;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function cropFor( int $widthMm, int $heightMm, int $photoWidth, int $photoHeight ): array {
		$format = PrintFormat::ofMillimetres( $widthMm, $heightMm );
		$crop   = CropPreview::of( $photoWidth, $photoHeight, $format );
		$pixels = $crop->pixelsIn( $photoWidth, $photoHeight );

		// Rozdzielczość liczymy PO PRZYCIĘCIU: kadrowanie zabiera piksele,
		// a przy dużych formatach przycięcie bywa największe.
		$quality = PrintQuality::of( $pixels['width'], $pixels['height'], $format );

		return array(
			'format'       => $format->label(),
			// Ułamki, nie piksele — podgląd w przeglądarce ma inny rozmiar
			// niż plik, a laboratorium jeszcze inny.
			'kept_width'   => round( $crop->keptWidth, 4 ),
			'kept_height'  => round( $crop->keptHeight, 4 ),
			'lost_percent' => $crop->lostPercent(),
			'full_frame'   => $crop->isFullFrame(),
			'warn'         => $crop->needsWarning(),
			'trim'         => $crop->trim->value,
			'trim_text'    => $crop->trim->describe(),
			'quality'      => $quality->grade->value,
			'quality_text' => $quality->describe(),
			'printable'    => $quality->isPrintable(),
		);
	}
}
