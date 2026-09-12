<?php
declare( strict_types=1 );

namespace Kadr\Domain\Storage;

/**
 * Metadane zdjęcia odczytane przy wysyłaniu.
 *
 * Obecność współrzędnych GPS jest tu osobnym polem, a nie szczegółem —
 * zdjęcie z sesji newborn niesie lokalizację domu klienta. Oryginał może
 * je zachować, publiczny podgląd nigdy (docs/SECURITY.md §4).
 */
final readonly class ImageMetadata {

	public function __construct(
		public int $width,
		public int $height,
		public string $mimeType,
		public int $bytes,
		public ?\DateTimeImmutable $takenAt = null,
		public bool $hasGps = false,
		public bool $hasExif = false,
		public int $orientation = 1,
	) {}

	public function isLandscape(): bool {
		return $this->width > $this->height;
	}

	public function aspectRatio(): float {
		return $this->height > 0 ? $this->width / $this->height : 0.0;
	}

	/**
	 * Czy obrót z EXIF wymaga fizycznej korekty pikseli.
	 *
	 * Przeglądarki honorują znacznik orientacji niejednolicie, a wariant
	 * podglądu ma być poprawny wszędzie — więc obracamy przy generowaniu.
	 */
	public function needsRotation(): bool {
		return in_array( $this->orientation, array( 3, 6, 8 ), true );
	}

	public function rotationDegrees(): int {
		return match ( $this->orientation ) {
			3       => 180,
			6       => -90,
			8       => 90,
			default => 0,
		};
	}

	/**
	 * Czy wymiary zamieniają się miejscami po korekcie obrotu.
	 */
	public function isRotatedSideways(): bool {
		return in_array( $this->orientation, array( 5, 6, 7, 8 ), true );
	}

	public function effectiveWidth(): int {
		return $this->isRotatedSideways() ? $this->height : $this->width;
	}

	public function effectiveHeight(): int {
		return $this->isRotatedSideways() ? $this->width : $this->height;
	}
}
