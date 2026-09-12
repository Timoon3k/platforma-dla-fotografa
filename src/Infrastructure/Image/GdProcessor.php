<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Image;

use Kadr\Domain\Storage\ImageFailure;
use Kadr\Domain\Storage\ImageFormat;
use Kadr\Domain\Storage\ImageMetadata;
use Kadr\Domain\Storage\ImageProcessor;
use Kadr\Domain\Storage\VariantSpec;

/**
 * Przetwarzanie obrazów przez GD — fallback, gdy nie ma Imagicka.
 *
 * GD wczytuje cały obraz do pamięci jako bitmapę, więc zdjęcie 6000×4000
 * to ~96 MB niezależnie od rozmiaru pliku. Dlatego przetwarzanie odbywa się
 * w kolejce, a nie w żądaniu użytkownika.
 *
 * GD **nie kopiuje metadanych** przy zapisie, więc usunięcie EXIF i GPS
 * z wariantów dzieje się tu z natury implementacji. To wygodne, ale nie
 * polegamy na tym w milczeniu — test to sprawdza.
 */
final class GdProcessor implements ImageProcessor {

	public function isAvailable(): bool {
		return extension_loaded( 'gd' ) && function_exists( 'imagecreatetruecolor' );
	}

	public function name(): string {
		return 'GD';
	}

	public function readMetadata( string $sourcePath ): ImageMetadata {
		if ( ! is_readable( $sourcePath ) ) {
			throw ImageFailure::unreadable( 'Plik nie istnieje lub jest nieczytelny.' );
		}

		$size = @getimagesize( $sourcePath );

		if ( false === $size ) {
			throw ImageFailure::unreadable( 'Zawartość nie jest obrazem.' );
		}

		$orientation = 1;
		$takenAt     = null;
		$hasGps      = false;
		$hasExif     = false;

		if ( function_exists( 'exif_read_data' ) && in_array( $size[2], array( IMAGETYPE_JPEG, IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM ), true ) ) {
			$exif = @exif_read_data( $sourcePath, 'ANY_TAG', true );

			if ( is_array( $exif ) ) {
				$hasExif     = true;
				$orientation = (int) ( $exif['IFD0']['Orientation'] ?? $exif['COMPUTED']['Orientation'] ?? 1 );
				$hasGps      = isset( $exif['GPS'] ) && array() !== (array) $exif['GPS'];

				$taken = $exif['EXIF']['DateTimeOriginal'] ?? $exif['IFD0']['DateTime'] ?? null;

				if ( is_string( $taken ) ) {
					$parsed  = \DateTimeImmutable::createFromFormat( 'Y:m:d H:i:s', $taken, new \DateTimeZone( 'UTC' ) );
					$takenAt = false !== $parsed ? $parsed : null;
				}
			}
		}

		return new ImageMetadata(
			width:       (int) $size[0],
			height:      (int) $size[1],
			mimeType:    (string) ( $size['mime'] ?? 'application/octet-stream' ),
			bytes:       (int) filesize( $sourcePath ),
			takenAt:     $takenAt,
			hasGps:      $hasGps,
			hasExif:     $hasExif,
			orientation: $orientation,
		);
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

		$source = $this->load( $sourcePath );

		try {
			if ( $metadata->needsRotation() ) {
				$rotated = imagerotate( $source, (float) $metadata->rotationDegrees(), 0 );

				if ( false !== $rotated ) {
					imagedestroy( $source );
					$source = $rotated;
				}
			}

			$sourceWidth  = imagesx( $source );
			$sourceHeight = imagesy( $source );

			// Nie powiększamy — wariant szerszy niż oryginał byłby gorszy
			// od oryginału i zajmował miejsce bez powodu.
			$targetWidth  = min( $spec->width, $sourceWidth );
			$targetHeight = (int) max( 1, round( $sourceHeight * ( $targetWidth / $sourceWidth ) ) );

			$target = imagecreatetruecolor( $targetWidth, $targetHeight );

			if ( false === $target ) {
				throw ImageFailure::processingFailed( 'Brak pamięci na obraz docelowy.' );
			}

			// Zachowanie przezroczystości dla PNG i WebP.
			imagealphablending( $target, false );
			imagesavealpha( $target, true );

			if ( ! imagecopyresampled( $target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight ) ) {
				throw ImageFailure::processingFailed( 'Skalowanie nie powiodło się.' );
			}

			$directory = dirname( $targetPath );

			if ( ! is_dir( $directory ) && ! mkdir( $directory, 0o755, true ) && ! is_dir( $directory ) ) {
				throw ImageFailure::processingFailed( 'Nie udało się utworzyć katalogu docelowego.' );
			}

			$written = $this->write( $target, $targetPath, $format );

			imagedestroy( $target );

			return $written;
		} finally {
			if ( is_object( $source ) ) {
				imagedestroy( $source );
			}
		}
	}

	public function supportedFormats(): array {
		$formats = array();
		$info    = function_exists( 'gd_info' ) ? gd_info() : array();

		if ( ! empty( $info['AVIF Support'] ) && function_exists( 'imageavif' ) ) {
			$formats[] = ImageFormat::Avif;
		}

		if ( ! empty( $info['WebP Support'] ) && function_exists( 'imagewebp' ) ) {
			$formats[] = ImageFormat::Webp;
		}

		if ( ! empty( $info['JPEG Support'] ) ) {
			$formats[] = ImageFormat::Jpeg;
		}

		return $formats;
	}

	private function load( string $path ): \GdImage {
		$size = @getimagesize( $path );

		if ( false === $size ) {
			throw ImageFailure::unreadable( 'Zawartość nie jest obrazem.' );
		}

		$image = match ( $size[2] ) {
			IMAGETYPE_JPEG => @imagecreatefromjpeg( $path ),
			IMAGETYPE_PNG  => @imagecreatefrompng( $path ),
			IMAGETYPE_WEBP => @imagecreatefromwebp( $path ),
			IMAGETYPE_AVIF => function_exists( 'imagecreatefromavif' ) ? @imagecreatefromavif( $path ) : false,
			default        => false,
		};

		if ( false === $image ) {
			throw ImageFailure::unreadable( 'Nieobsługiwany typ obrazu.' );
		}

		return $image;
	}

	private function write( \GdImage $image, string $path, ImageFormat $format ): int {
		$quality = $format->quality();

		$ok = match ( $format ) {
			ImageFormat::Avif => imageavif( $image, $path, $quality ),
			ImageFormat::Webp => imagewebp( $image, $path, $quality ),
			ImageFormat::Jpeg => $this->writeJpeg( $image, $path, $quality ),
		};

		if ( ! $ok || ! is_file( $path ) ) {
			throw ImageFailure::processingFailed( sprintf( 'Zapis w formacie %s nie powiódł się.', $format->value ) );
		}

		return (int) filesize( $path );
	}

	/**
	 * JPEG nie ma kanału alfa — przezroczystość zamieniamy na biel,
	 * inaczej powstałby czarny prostokąt zamiast tła.
	 */
	private function writeJpeg( \GdImage $image, string $path, int $quality ): bool {
		$flattened = imagecreatetruecolor( imagesx( $image ), imagesy( $image ) );

		if ( false === $flattened ) {
			return false;
		}

		$white = imagecolorallocate( $flattened, 255, 255, 255 );
		imagefilledrectangle( $flattened, 0, 0, imagesx( $image ), imagesy( $image ), (int) $white );
		imagecopy( $flattened, $image, 0, 0, 0, 0, imagesx( $image ), imagesy( $image ) );

		$ok = imagejpeg( $flattened, $path, $quality );
		imagedestroy( $flattened );

		return $ok;
	}
}
