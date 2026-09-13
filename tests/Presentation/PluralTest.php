<?php
declare( strict_types=1 );

namespace Kadr\Tests\Presentation;

use Kadr\Presentation\Support\Plural;
use Kadr\Tests\TestCase;

/**
 * Polska odmiana liczebnika.
 *
 * Licznik pakietu jest najczęściej czytanym tekstem w produkcie — widzi go
 * każda klientka przy każdym kliknięciu. „Wybrałaś 4 zdjęć" jest w nim
 * błędem widocznym dla wszystkich.
 */
final class PluralTest extends TestCase {

	public function testSingularForOne(): void {
		$this->assertSame( 'jeden', Plural::pick( 1, 'jeden', 'kilka', 'wiele' ) );
	}

	public function testFewForTwoToFour(): void {
		foreach ( array( 2, 3, 4, 22, 23, 24, 102, 1004 ) as $count ) {
			$this->assertSame( 'kilka', Plural::pick( $count, 'jeden', 'kilka', 'wiele' ), "liczba $count" );
		}
	}

	public function testManyForFiveAndAbove(): void {
		foreach ( array( 0, 5, 6, 9, 10, 11, 25, 100, 1000 ) as $count ) {
			$this->assertSame( 'wiele', Plural::pick( $count, 'jeden', 'kilka', 'wiele' ), "liczba $count" );
		}
	}

	/**
	 * Pułapka, o którą łatwo się potknąć: nastki idą do formy dopełniaczowej
	 * mimo końcówki 2–4 — „dwanaście zdjęć", nie „dwanaście zdjęcia".
	 */
	public function testTeensUseTheGenitiveForm(): void {
		foreach ( array( 12, 13, 14, 112, 113, 114 ) as $count ) {
			$this->assertSame( 'wiele', Plural::pick( $count, 'jeden', 'kilka', 'wiele' ), "liczba $count" );
		}
	}
}
