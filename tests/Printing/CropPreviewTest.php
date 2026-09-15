<?php
declare( strict_types=1 );

namespace Kadr\Tests\Printing;

use Kadr\Domain\Printing\CropPreview;
use Kadr\Domain\Printing\PrintFormat;
use Kadr\Domain\Printing\Trim;
use Kadr\Tests\TestCase;

/**
 * Podgląd kadrowania.
 *
 * To jest bramka tej sesji i jednocześnie szczegół, który decyduje
 * o zwrotach: klientka, która nie zobaczyła, jak zostanie przycięte
 * jej zdjęcie, dostanie odbitkę z obciętą głową.
 *
 * Testy używają PRAWDZIWYCH formatów z polskiego rynku, bo to na nich
 * ten produkt ma działać.
 */
final class CropPreviewTest extends TestCase {

	/**
	 * Zdjęcie 3:2 w formacie 10×15 (też 3:2) jest pełne.
	 * To jest przypadek, w którym klientka NIE MOŻE zobaczyć ostrzeżenia.
	 */
	public function testAThreeToTwoPhotoFitsTenByFifteenExactly(): void {
		$crop = CropPreview::of( 6000, 4000, PrintFormat::ofMillimetres( 100, 150 ) );

		$this->assertTrue( $crop->isFullFrame() );
		$this->assertSame( Trim::None, $crop->trim );
		$this->assertSame( 0, $crop->lostPercent() );
		$this->assertFalse( $crop->needsWarning() );
	}

	/**
	 * To samo zdjęcie w 13×18 zostanie przycięte — i to jest DOKŁADNIE
	 * ten przypadek ze skilla, dla którego cała funkcja powstała.
	 */
	public function testTheSamePhotoLosesItsSidesInThirteenByEighteen(): void {
		$crop = CropPreview::of( 6000, 4000, PrintFormat::ofMillimetres( 130, 180 ) );

		$this->assertFalse( $crop->isFullFrame() );
		$this->assertSame( Trim::Sides, $crop->trim );
		// 3:2 to 1,5; 18:13 to ≈1,3846. Zostaje ≈92,3% szerokości.
		$this->assertSame( 8, $crop->lostPercent() );
	}

	/**
	 * Format 10×15 dla zdjęcia PIONOWEGO znaczy 10 w poziomie i 15 w pionie.
	 *
	 * Laboratorium nie drukuje portretu na leżąco. Bez obrócenia formatu
	 * każde zdjęcie pionowe wyglądałoby na przycięte w połowie, a klientka
	 * zrezygnowałaby z zamówienia.
	 */
	public function testAPortraitPhotoGetsAPortraitFormat(): void {
		$crop = CropPreview::of( 4000, 6000, PrintFormat::ofMillimetres( 100, 150 ) );

		$this->assertTrue( $crop->isFullFrame() );
	}

	public function testAPortraitPhotoInThirteenByEighteenLosesTopAndBottom(): void {
		$crop = CropPreview::of( 4000, 6000, PrintFormat::ofMillimetres( 130, 180 ) );

		$this->assertSame( Trim::TopAndBottom, $crop->trim );
		$this->assertSame( 8, $crop->lostPercent() );
	}

	/**
	 * Kwadrat w formacie panoramicznym traci połowę kadru.
	 *
	 * Ucina GÓRĘ I DÓŁ, nie boki: format 10×20 jest szeroki, więc z kwadratu
	 * zostaje poziomy pasek ze środka. Odruch podpowiada odwrotnie i na tym
	 * właśnie poległ pierwszy szkic tego testu.
	 */
	public function testASquarePhotoInAPanoramicFormatWarnsLoudly(): void {
		$crop = CropPreview::of( 4000, 4000, PrintFormat::ofMillimetres( 100, 200 ) );

		$this->assertTrue( $crop->needsWarning() );
		$this->assertSame( 50, $crop->lostPercent() );
		$this->assertSame( Trim::TopAndBottom, $crop->trim );
	}

	/**
	 * Próg ostrzeżenia: 10% powierzchni. Poniżej obcięcie widać
	 * na podglądzie, ale ostrzeżenie pojawiające się prawie zawsze
	 * przestaje cokolwiek znaczyć.
	 */
	public function testSmallLossesDoNotRaiseAWarning(): void {
		// 3:2 w 13×18 traci 8% — widoczne na podglądzie, bez ostrzeżenia.
		$this->assertFalse(
			CropPreview::of( 6000, 4000, PrintFormat::ofMillimetres( 130, 180 ) )->needsWarning()
		);
	}

	public function testTenPercentIsAlreadyWorthAWarning(): void {
		// 15×21 dla zdjęcia 3:2: 1,5 → 1,4; strata ≈6,7%. Bierzemy więc
		// format, który przekracza próg: 4:3 w kwadracie to 25%.
		$this->assertTrue(
			CropPreview::of( 4000, 3000, PrintFormat::ofMillimetres( 300, 300 ) )->needsWarning()
		);
	}

	/**
	 * Proporcje równe co do ułamka nie mogą dawać „zostanie przycięte".
	 *
	 * 3/2 i 150/100 to ta sama proporcja, ale nie ten sam `double`.
	 * Bez tolerancji klientka dostawałaby ostrzeżenie o stracie rzędu
	 * 0,00000001% tam, gdzie nic się nie dzieje.
	 */
	public function testRoundingNeverInventsATrim(): void {
		foreach ( array( array( 6000, 4000 ), array( 3000, 2000 ), array( 1500, 1000 ) ) as [$w, $h] ) {
			$this->assertTrue(
				CropPreview::of( $w, $h, PrintFormat::ofMillimetres( 100, 150 ) )->isFullFrame(),
				sprintf( 'Zdjęcie %d×%d', $w, $h )
			);
		}
	}

	/**
	 * Kadrujemy ze środka — fotograf komponuje centralnie częściej
	 * niż jakkolwiek inaczej.
	 */
	public function testTheCropIsTakenFromTheCentre(): void {
		$crop   = CropPreview::of( 1000, 1000, PrintFormat::ofMillimetres( 100, 200 ) );
		$pixels = $crop->pixelsIn( 1000, 1000 );

		// Z kwadratu zostaje poziomy pasek: pełna szerokość, połowa wysokości.
		$this->assertSame( 1000, $pixels['width'] );
		$this->assertSame( 500, $pixels['height'] );
		// Po 250 px z góry i z dołu.
		$this->assertSame( 0, $pixels['left'] );
		$this->assertSame( 250, $pixels['top'] );
	}

	public function testAFullFrameCropKeepsEveryPixel(): void {
		$pixels = CropPreview::of( 6000, 4000, PrintFormat::ofMillimetres( 100, 150 ) )
			->pixelsIn( 6000, 4000 );

		$this->assertSame( 6000, $pixels['width'] );
		$this->assertSame( 4000, $pixels['height'] );
		$this->assertSame( 0, $pixels['left'] );
	}

	/**
	 * Każdy rodzaj przycięcia ma zdanie po polsku — konkretne, nie
	 * „zdjęcie zostanie dopasowane do formatu".
	 */
	public function testEveryTrimExplainsItselfConcretely(): void {
		foreach ( Trim::cases() as $trim ) {
			$this->assertTrue( '' !== $trim->describe(), $trim->value );
			$this->assertFalse( str_contains( $trim->describe(), 'dopasowane' ), $trim->value );
		}
	}

	public function testAPhotoWithoutDimensionsIsRejected(): void {
		$this->assertThrows(
			\InvalidArgumentException::class,
			static fn () => CropPreview::of( 0, 4000, PrintFormat::ofMillimetres( 100, 150 ) )
		);
	}

	public function testAFormatWithoutDimensionsIsRejected(): void {
		$this->assertThrows(
			\InvalidArgumentException::class,
			static fn () => PrintFormat::ofMillimetres( 0, 150 )
		);
	}

	/**
	 * Nazwa handlowa zawsze krótszym bokiem do przodu — inaczej ta sama
	 * odbitka figurowałaby raz jako 10×15, raz jako 15×10.
	 */
	public function testFormatLabelsAlwaysPutTheShortSideFirst(): void {
		$this->assertSame( '10×15', PrintFormat::ofMillimetres( 100, 150 )->label() );
		$this->assertSame( '10×15', PrintFormat::ofMillimetres( 150, 100 )->label() );
		$this->assertSame( '50×70', PrintFormat::ofMillimetres( 500, 700 )->label() );
	}

	public function testHalfCentimetreFormatsReadNaturally(): void {
		$this->assertSame( '10,5×14,8', PrintFormat::ofMillimetres( 105, 148 )->label() );
	}
}
