<?php
declare( strict_types=1 );

namespace Kadr\Tests\Image;

use Kadr\Domain\Storage\ImageFailure;
use Kadr\Domain\Storage\ImageFormat;
use Kadr\Domain\Storage\VariantSpec;
use Kadr\Infrastructure\Image\GdProcessor;
use Kadr\Tests\TestCase;

/**
 * Pipeline obrazów — testy na PRAWDZIWYCH plikach, nie na atrapach.
 */
final class GdProcessorTest extends TestCase {

	private string $workdir = '';

	private function dir(): string {
		if ( '' === $this->workdir ) {
			$this->workdir = sys_get_temp_dir() . '/kadr-img-' . bin2hex( random_bytes( 6 ) );
			mkdir( $this->workdir, 0o755, true );
		}

		return $this->workdir;
	}

	/**
	 * Tworzy prawdziwy JPEG o zadanych wymiarach.
	 */
	private function makeJpeg( int $width, int $height ): string {
		$path  = $this->dir() . '/source-' . $width . 'x' . $height . '.jpg';
		$image = imagecreatetruecolor( $width, $height );

		// Gradient zamiast jednolitego koloru — jednolity kolor kompresuje się
		// do kilkuset bajtów i nie mówi nic o jakości skalowania.
		for ( $x = 0; $x < $width; $x += 8 ) {
			$colour = imagecolorallocate( $image, ( $x * 3 ) % 256, ( $x * 7 ) % 256, ( $x * 11 ) % 256 );
			imagefilledrectangle( $image, $x, 0, $x + 8, $height, (int) $colour );
		}

		imagejpeg( $image, $path, 92 );
		imagedestroy( $image );

		return $path;
	}

	public function testProcessorIsAvailableAndNamed(): void {
		$processor = new GdProcessor();

		$this->assertTrue( $processor->isAvailable() );
		$this->assertSame( 'GD', $processor->name() );
	}

	public function testReadsRealDimensions(): void {
		$processor = new GdProcessor();
		$metadata  = $processor->readMetadata( $this->makeJpeg( 1600, 900 ) );

		$this->assertSame( 1600, $metadata->width );
		$this->assertSame( 900, $metadata->height );
		$this->assertSame( 'image/jpeg', $metadata->mimeType );
		$this->assertTrue( $metadata->bytes > 0 );
		$this->assertTrue( $metadata->isLandscape() );
	}

	public function testRefusesNonImages(): void {
		$processor = new GdProcessor();
		$path      = $this->dir() . '/not-an-image.jpg';

		// Plik z rozszerzeniem .jpg, ale treścią PHP — dokładnie to,
		// co próbuje przemycić atakujący (docs/SECURITY.md §4).
		file_put_contents( $path, "<?php echo 'przejęte'; ?>" );

		$this->assertThrows( ImageFailure::class, static fn() => $processor->readMetadata( $path ) );
	}

	public function testRefusesMissingFile(): void {
		$processor = new GdProcessor();

		$this->assertThrows(
			ImageFailure::class,
			static fn() => $processor->readMetadata( '/nie/ma/takiego/pliku.jpg' )
		);
	}

	public function testCreatesVariantAtTheRequestedWidth(): void {
		$processor = new GdProcessor();
		$source    = $this->makeJpeg( 3000, 2000 );
		$metadata  = $processor->readMetadata( $source );
		$target    = $this->dir() . '/grid.webp';

		$bytes = $processor->createVariant( $source, $target, VariantSpec::grid(), ImageFormat::Webp, $metadata );

		$this->assertTrue( $bytes > 0 );

		$result = getimagesize( $target );
		$this->assertSame( 900, $result[0] );
		// Proporcje 3:2 zachowane.
		$this->assertSame( 600, $result[1] );
		$this->assertSame( 'image/webp', $result['mime'] );
	}

	public function testVariantIsSmallerThanTheOriginal(): void {
		$processor = new GdProcessor();
		$source    = $this->makeJpeg( 3000, 2000 );
		$metadata  = $processor->readMetadata( $source );
		$target    = $this->dir() . '/thumb.webp';

		$bytes = $processor->createVariant( $source, $target, VariantSpec::thumb(), ImageFormat::Webp, $metadata );

		$this->assertTrue(
			$bytes < $metadata->bytes,
			sprintf( 'Miniatura (%d B) nie jest mniejsza od oryginału (%d B).', $bytes, $metadata->bytes )
		);
	}

	/**
	 * Powiększanie małego zdjęcia daje gorszy plik i zajmuje miejsce bez powodu.
	 */
	public function testNeverUpscales(): void {
		$processor = new GdProcessor();
		$source    = $this->makeJpeg( 600, 400 );
		$metadata  = $processor->readMetadata( $source );
		$target    = $this->dir() . '/view.webp';

		$processor->createVariant( $source, $target, VariantSpec::view(), ImageFormat::Webp, $metadata );

		$result = getimagesize( $target );
		// Wariant „view” ma 1800 px, ale oryginał ma 600 — zostaje 600.
		$this->assertSame( 600, $result[0] );
	}

	/**
	 * Wariant publiczny NIGDY nie niesie EXIF ani GPS.
	 *
	 * Zdjęcie z sesji newborn zawiera lokalizację domu klienta — to jest
	 * realne zagrożenie dla tej rodziny, nie teoria (docs/SECURITY.md §4).
	 */
	public function testVariantsCarryNoMetadata(): void {
		$processor = new GdProcessor();
		$source    = $this->makeJpeg( 2400, 1600 );
		$metadata  = $processor->readMetadata( $source );

		foreach ( array( ImageFormat::Webp, ImageFormat::Jpeg ) as $format ) {
			$target = $this->dir() . '/clean.' . $format->value;
			$processor->createVariant( $source, $target, VariantSpec::grid(), $format, $metadata );

			$exif = @exif_read_data( $target );

			// Brak danych EXIF albo wyłącznie pola techniczne wyliczone
			// przez PHP z samego pliku — nigdy GPS.
			$this->assertFalse(
				is_array( $exif ) && isset( $exif['GPSLatitude'] ),
				sprintf( 'Wariant %s zawiera współrzędne GPS.', $format->value )
			);
		}
	}

	public function testSupportsModernFormats(): void {
		$processor = new GdProcessor();
		$formats   = array_map( static fn( ImageFormat $f ): string => $f->value, $processor->supportedFormats() );

		$this->assertTrue( in_array( 'webp', $formats, true ) );
		$this->assertTrue( in_array( 'jpeg', $formats, true ) );
	}

	/**
	 * Sześć wariantów na zdjęcie musi realnie zmieścić się w budżecie storage'u:
	 * suma derywatów ma być ułamkiem oryginału (ADR-011).
	 */
	public function testAllVariantsTogetherStayFarBelowTheOriginal(): void {
		$processor = new GdProcessor();
		$source    = $this->makeJpeg( 4000, 2667 );
		$metadata  = $processor->readMetadata( $source );

		$total = 0;

		foreach ( VariantSpec::standard() as $spec ) {
			foreach ( array( ImageFormat::Webp ) as $format ) {
				$target = sprintf( '%s/%s.%s', $this->dir(), $spec->name, $format->value );
				$total += $processor->createVariant( $source, $target, $spec, $format, $metadata );
			}
		}

		$this->assertTrue(
			$total < $metadata->bytes,
			sprintf( 'Warianty (%d B) ważą więcej niż oryginał (%d B).', $total, $metadata->bytes )
		);
	}

	public function testHandlesPortraitOrientation(): void {
		$processor = new GdProcessor();
		$source    = $this->makeJpeg( 2000, 3000 );
		$metadata  = $processor->readMetadata( $source );
		$target    = $this->dir() . '/portrait.webp';

		$processor->createVariant( $source, $target, VariantSpec::grid(), ImageFormat::Webp, $metadata );

		$result = getimagesize( $target );
		$this->assertSame( 900, $result[0] );
		$this->assertSame( 1350, $result[1] );
		$this->assertFalse( $metadata->isLandscape() );
	}
}

/**
 * Wybór implementacji przetwarzania obrazów.
 */
final class ProcessorFactoryTest extends TestCase {

	public function testPicksTheFirstAvailableProcessor(): void {
		$processor = ( new \Kadr\Infrastructure\Image\ProcessorFactory() )->create();

		$this->assertTrue( $processor->isAvailable() );
		// W tym środowisku nie ma Imagicka, więc padnie na GD — i to jest
		// dokładnie zachowanie fallbacku, które chcemy mieć sprawdzone.
		$this->assertSame( 'GD', $processor->name() );
	}

	public function testReportsDegradedModeWhenImagickIsMissing(): void {
		$factory = new \Kadr\Infrastructure\Image\ProcessorFactory();

		$this->assertSame( ! extension_loaded( 'imagick' ), $factory->isDegraded() );
	}

	public function testFailsLoudlyWhenNoProcessorIsAvailable(): void {
		$factory = new \Kadr\Infrastructure\Image\ProcessorFactory( array() );

		$this->assertThrows( \RuntimeException::class, static fn() => $factory->create() );
	}
}
