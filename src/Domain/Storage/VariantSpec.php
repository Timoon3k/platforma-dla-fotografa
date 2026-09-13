<?php
declare( strict_types=1 );

namespace Kadr\Domain\Storage;

/**
 * Specyfikacja wariantu zdjęcia.
 *
 * Sześć plików na zdjęcie, nie dwadzieścia (ADR-011). Każdy dodatkowy wariant
 * mnoży się przez 18 mln zdjęć, więc musi mieć uzasadnienie biznesowe.
 */
final readonly class VariantSpec {

	private function __construct(
		public string $name,
		public int $width,
		public bool $watermarked = false,
	) {}

	/** Siatka na telefonie. */
	public static function thumb(): self {
		return new self( 'thumb', 400 );
	}

	/** Siatka desktop oraz retina na telefonie. */
	public static function grid(): self {
		return new self( 'grid', 900 );
	}

	/** Lightbox i pełny ekran. */
	public static function view(): self {
		return new self( 'view', 1800 );
	}

	/**
	 * Miniatura wpisywana wprost w HTML galerii klienta.
	 *
	 * 24 piksele szerokości to kilkaset bajtów — tyle, żeby kadr miał kolor
	 * i kształt, zanim dojdzie prawdziwy plik. Nie trafia do magazynu:
	 * jej sens polega na tym, że jest W dokumencie, a nie za kolejnym
	 * żądaniem sieciowym.
	 */
	public static function lqip(): self {
		return new self( 'lqip', 24 );
	}

	/** Podgląd ze znakiem wodnym — generowany tylko, gdy fotograf go włączył. */
	public static function watermarked(): self {
		return new self( 'wm', 1800, true );
	}

    /**
     * Warianty generowane zawsze.
     *
     * @return list<self>
     */
	public static function standard(): array {
		return array( self::thumb(), self::grid(), self::view() );
	}

	/**
	 * @return list<self>
	 */
	public static function forGallery( bool $withWatermark ): array {
		$specs = self::standard();

		if ( $withWatermark ) {
			$specs[] = self::watermarked();
		}

		return $specs;
	}

	public function isThumbnail(): bool {
		return 'thumb' === $this->name;
	}

	public function isPlaceholder(): bool {
		return 'lqip' === $this->name;
	}

	/**
	 * Wysokość przy zachowaniu proporcji oryginału.
	 */
	public function heightFor( int $originalWidth, int $originalHeight ): int {
		if ( $originalWidth <= 0 ) {
			return 0;
		}

		return (int) max( 1, round( $originalHeight * ( $this->width / $originalWidth ) ) );
	}

	/**
	 * Czy w ogóle warto generować ten wariant.
	 *
	 * Powiększanie małego zdjęcia do 1800 px daje gorszy plik niż oryginał
	 * i zajmuje miejsce bez powodu.
	 */
	public function appliesTo( int $originalWidth ): bool {
		// Miniatura zastępcza ma sens nawet dla małego zdjęcia: jej zadaniem
		// jest wypełnić kadr kolorem, a nie zastąpić plik.
		return $this->isPlaceholder() || $originalWidth > $this->width;
	}
}
