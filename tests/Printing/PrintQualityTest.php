<?php
declare( strict_types=1 );

namespace Kadr\Tests\Printing;

use Kadr\Domain\Printing\CropPreview;
use Kadr\Domain\Printing\Grade;
use Kadr\Domain\Printing\PrintFormat;
use Kadr\Domain\Printing\PrintQuality;
use Kadr\Tests\TestCase;

/**
 * Czy plik wystarczy na ten format.
 *
 * Drugie źródło reklamacji, zaraz po kadrowaniu: różnicy między ostrą
 * a rozmytą odbitką nie widać na ekranie telefonu, więc nikt jej sam
 * nie zauważy. Trzeba ją policzyć i powiedzieć wprost.
 */
final class PrintQualityTest extends TestCase {

	/**
	 * Zdjęcie z lustrzanki (24 Mpix) w 10×15 — bez zarzutu.
	 */
	public function testAFullSizePhotoIsPerfectForASmallPrint(): void {
		$quality = PrintQuality::of( 6000, 4000, PrintFormat::ofMillimetres( 100, 150 ) );

		$this->assertSame( Grade::Good, $quality->grade );
		$this->assertTrue( $quality->isPrintable() );
	}

	/**
	 * To samo zdjęcie w 50×70 nadal daje dobry wydruk — 6000 px na 70 cm
	 * to ponad 200 DPI.
	 */
	public function testAFullSizePhotoStillWorksForALargePrint(): void {
		$this->assertTrue(
			PrintQuality::of( 6000, 4000, PrintFormat::ofMillimetres( 500, 700 ) )->isPrintable()
		);
	}

	/**
	 * Mały kadr w dużym formacie wyjdzie rozmyty — i to jest dokładnie
	 * ten przypadek, w którym klientka musi usłyszeć „nie".
	 */
	public function testASmallCropInALargeFormatIsRefused(): void {
		$quality = PrintQuality::of( 900, 600, PrintFormat::ofMillimetres( 500, 700 ) );

		$this->assertSame( Grade::Poor, $quality->grade );
		$this->assertFalse( $quality->isPrintable() );
		$this->assertTrue( str_contains( $quality->describe(), 'rozmyta' ) );
	}

	/**
	 * Komunikat nie używa słowa „DPI".
	 *
	 * Ta liczba nic klientce nie mówi, a „wyjdzie rozmyta" mówi wszystko
	 * (skill photography-workflow §9).
	 */
	public function testTheMessageNeverMentionsDpi(): void {
		foreach ( array( 300, 1200, 6000 ) as $width ) {
			$quality = PrintQuality::of( $width, (int) ( $width * 2 / 3 ), PrintFormat::ofMillimetres( 300, 400 ) );

			$this->assertFalse( str_contains( mb_strtolower( $quality->describe() ), 'dpi' ) );
			$this->assertTrue( '' !== $quality->describe() );
		}
	}

	/**
	 * Liczymy DPI PO PRZYCIĘCIU, nie z oryginału.
	 *
	 * Kadrowanie zabiera piksele, a przy dużych formatach przycięcie bywa
	 * największe — więc ocena z oryginału byłaby zawyżona dokładnie tam,
	 * gdzie najbardziej szkodzi.
	 */
	public function testQualityIsJudgedAfterCropping(): void {
		$format = PrintFormat::ofMillimetres( 300, 400 );
		$crop   = CropPreview::of( 1400, 1400, $format );
		$pixels = $crop->pixelsIn( 1400, 1400 );

		$fromOriginal = PrintQuality::of( 1400, 1400, $format );
		$fromCrop     = PrintQuality::of( $pixels['width'], $pixels['height'], $format );

		// Kwadrat w 30×40 traci 25% wysokości, więc ocena po kadrowaniu
		// nie może być lepsza niż ocena z oryginału.
		$this->assertTrue( $fromCrop->dpi <= $fromOriginal->dpi );
	}

	/**
	 * Między „bez zarzutu" a „rozmyte" jest stan pośredni: da się wydrukować,
	 * ale to już granica. Klientka ma o tym wiedzieć, zamiast dostać
	 * zero-jedynkową odmowę.
	 */
	public function testThereIsAMiddleGroundBetweenGoodAndUnprintable(): void {
		// 1200 px na dłuższym boku formatu 20×30 to ≈101 DPI — za mało.
		// 2000 px daje ≈169 DPI — do przyjęcia.
		$acceptable = PrintQuality::of( 2000, 1333, PrintFormat::ofMillimetres( 200, 300 ) );

		$this->assertSame( Grade::Acceptable, $acceptable->grade );
		$this->assertTrue( $acceptable->isPrintable() );
		$this->assertTrue( str_contains( $acceptable->describe(), 'największy format' ) );
	}

	/**
	 * Format jest ustawiany pod zdjęcie, więc dłuższy bok zdjęcia trafia
	 * na dłuższy bok formatu — także przy zdjęciu pionowym.
	 */
	public function testAPortraitPhotoIsJudgedAgainstTheLongSideToo(): void {
		$landscape = PrintQuality::of( 6000, 4000, PrintFormat::ofMillimetres( 300, 400 ) );
		$portrait  = PrintQuality::of( 4000, 6000, PrintFormat::ofMillimetres( 300, 400 ) );

		$this->assertSame( $landscape->dpi, $portrait->dpi );
	}
}
