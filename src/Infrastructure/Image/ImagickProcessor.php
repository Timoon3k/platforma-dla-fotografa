<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Image;

use Kadr\Domain\Storage\ImageFailure;
use Kadr\Domain\Storage\ImageFormat;
use Kadr\Domain\Storage\ImageMetadata;
use Kadr\Domain\Storage\ImageProcessor;
use Kadr\Domain\Storage\VariantSpec;

/**
 * Przetwarzanie obrazów przez Imagick — ścieżka produkcyjna.
 *
 * Przewaga nad GD, która uzasadnia wymaganie rozszerzenia:
 *  - `stripImage()` usuwa WSZYSTKIE profile i metadane jawnie, zamiast polegać
 *    na tym, że biblioteka „i tak ich nie kopiuje”,
 *  - `setSize()` przed wczytaniem pozwala JPEG-owi zdekodować się od razu
 *    w mniejszej skali, co przy zdjęciu 6000×4000 oszczędza dziesiątki MB,
 *  - filtr Lanczos daje wyraźnie lepsze zmniejszenie niż resampling GD,
 *  - `autoOrient()` obsługuje wszystkie osiem orientacji EXIF, nie trzy.
 *
 * ⚠️ Kod nieprzetestowany automatycznie — środowisko, w którym powstał,
 * nie ma rozszerzenia Imagick. Testy pokrywają GD oraz wybór implementacji
 * przez fabrykę. Weryfikacja tej ścieżki jest pozycją w checkliście wydania.
 */
final class ImagickProcessor implements ImageProcessor {

	public function isAvailable(): bool {
		return extension_loaded( 'imagick' ) && class_exists( \Imagick::class );
	}

	public function name(): string {
		return 'Imagick';
	}

	public function readMetadata( string $sourcePath ): ImageMetadata {
		if ( ! is_readable( $sourcePath ) ) {
			throw ImageFailure::unreadable( 'Plik nie istnieje lub jest nieczytelny.' );
		}

		try {
			$image = new \Imagick();
			$image->pingImage( $sourcePath );

			$properties = $image->getImageProperties( 'exif:*', true );
			$orientation = $this->orientationFrom( $image );

			$takenAt = null;
			$raw     = $properties['exif:DateTimeOriginal'] ?? $properties['exif:DateTime'] ?? null;

			if ( is_string( $raw ) ) {
				$parsed  = \DateTimeImmutable::createFromFormat( 'Y:m:d H:i:s', $raw, new \DateTimeZone( 'UTC' ) );
				$takenAt = false !== $parsed ? $parsed : null;
			}

			$hasGps = false;
			foreach ( array_keys( $properties ) as $key ) {
				if ( str_starts_with( (string) $key, 'exif:GPS' ) ) {
					$hasGps = true;
					break;
				}
			}

			$metadata = new ImageMetadata(
				width:       $image->getImageWidth(),
				height:      $image->getImageHeight(),
				mimeType:    (string) ( $image->getImageMimeType() ?: 'application/octet-stream' ),
				bytes:       (int) filesize( $sourcePath ),
				takenAt:     $takenAt,
				hasGps:      $hasGps,
				hasExif:     array() !== $properties,
				orientation: $orientation,
			);

			$image->clear();

			return $metadata;
		} catch ( \ImagickException $e ) {
			throw ImageFailure::unreadable( $e->getMessage() );
		}
	}

	public function createVariant(
		string $sourcePath,
		string $targetPath,
		VariantSpec $spec,
		ImageFormat $format,
		ImageMetadata $metadata
	): int {
		if ( ! in_array( $format, $this->supportedFormats(), true ) ) {
			throw ImageFailure::unsupportedFormat( $format, $this->name() );
		}

		try {
			$image = new \Imagick();

			// Podpowiedź dla dekodera JPEG: wczytaj od razu mniejszy obraz.
			// Przy 6000×4000 to różnica rzędu dziesiątek megabajtów pamięci.
			$image->setSize( $spec->width * 2, $spec->width * 2 );
			$image->readImage( $sourcePath );

			// Obsługuje wszystkie osiem orientacji EXIF.
			$image->autoOrient();

			$sourceWidth = $image->getImageWidth();
			$targetWidth = min( $spec->width, $sourceWidth );

			// 0 jako wysokość = zachowaj proporcje. Lanczos daje ostrzejsze
			// zmniejszenie niż domyślny filtr.
			$image->resizeImage( $targetWidth, 0, \Imagick::FILTER_LANCZOS, 1, true );

			// Usunięcie WSZYSTKICH metadanych i profili — jawnie, nie przez
			// przypadek. Publiczny podgląd nie niesie EXIF ani GPS.
			$image->stripImage();

			$image->setImageFormat( $format->value );
			$image->setImageCompressionQuality( $format->quality() );

			if ( ImageFormat::Jpeg === $format ) {
				$image->setImageBackgroundColor( new \ImagickPixel( 'white' ) );
				$image = $image->flattenImages();
				$image->setInterlaceScheme( \Imagick::INTERLACE_PLANE );
			}

			$directory = dirname( $targetPath );

			if ( ! is_dir( $directory ) && ! mkdir( $directory, 0o755, true ) && ! is_dir( $directory ) ) {
				throw ImageFailure::processingFailed( 'Nie udało się utworzyć katalogu docelowego.' );
			}

			if ( ! $image->writeImage( $targetPath ) ) {
				throw ImageFailure::processingFailed( 'Zapis pliku nie powiódł się.' );
			}

			$image->clear();

			return (int) filesize( $targetPath );
		} catch ( \ImagickException $e ) {
			throw ImageFailure::processingFailed( $e->getMessage() );
		}
	}

	public function supportedFormats(): array {
		if ( ! $this->isAvailable() ) {
			return array();
		}

		$available = array_map( 'strtolower', \Imagick::queryFormats() );
		$formats   = array();

		foreach ( ImageFormat::cases() as $format ) {
			$needle = ImageFormat::Jpeg === $format ? 'jpeg' : $format->value;

			if ( in_array( $needle, $available, true ) ) {
				$formats[] = $format;
			}
		}

		return $formats;
	}

	private function orientationFrom( \Imagick $image ): int {
		$orientation = $image->getImageOrientation();

		return $orientation > 0 ? $orientation : 1;
	}
}
