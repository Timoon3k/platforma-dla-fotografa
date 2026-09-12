<?php
declare( strict_types=1 );

namespace Kadr\Tests\Domain;

use Kadr\Domain\Selection\PackageTally;
use Kadr\Domain\Selection\SelectionState;
use Kadr\Domain\Shared\Money;
use Kadr\Tests\TestCase;

/**
 * Arytmetyka dopłaty — etap, na którym fotograf faktycznie zarabia.
 *
 * Błąd tutaj oznacza, że klient widzi inną kwotę niż zapłaci, więc te testy
 * są ważniejsze niż wszystko inne w warstwie domenowej.
 */
final class PackageTallyTest extends TestCase {

	private function price( int $zloty = 60 ): Money {
		return Money::fromMajor( $zloty );
	}

	/**
	 * Przykład z sekcji „Wybór i dopłata” na stronie: pakiet 20, wybrane 28.
	 */
	public function testTheHeadlineExample(): void {
		$tally = PackageTally::calculate( 28, 20, $this->price( 60 ) );

		$this->assertSame( 20, $tally->included );
		$this->assertSame( 8, $tally->extra );
		$this->assertSame( 48000, $tally->total->minor );
		$this->assertSame( 480.0, $tally->total->major() );
		$this->assertTrue( $tally->requiresPayment() );
	}

	public function testNoSurchargeWithinThePackage(): void {
		$tally = PackageTally::calculate( 15, 20, $this->price() );

		$this->assertSame( 15, $tally->included );
		$this->assertSame( 0, $tally->extra );
		$this->assertTrue( $tally->total->isZero() );
		$this->assertFalse( $tally->requiresPayment() );
		$this->assertSame( 5, $tally->remainingInPackage() );
	}

	public function testExactlyAtTheLimitCostsNothing(): void {
		$tally = PackageTally::calculate( 20, 20, $this->price() );

		$this->assertSame( 0, $tally->extra );
		$this->assertFalse( $tally->requiresPayment() );
		$this->assertSame( 0, $tally->remainingInPackage() );
		// Moment, w którym warto uprzedzić klienta przed kolejnym kliknięciem.
		$this->assertTrue( $tally->isAtPackageLimit() );
	}

	public function testOnePhotoOverTheLimit(): void {
		$tally = PackageTally::calculate( 21, 20, $this->price() );

		$this->assertSame( 1, $tally->extra );
		$this->assertSame( 6000, $tally->total->minor );
		$this->assertFalse( $tally->isAtPackageLimit() );
	}

	/**
	 * Pakiet bez limitu — np. sesja rozliczona ryczałtem.
	 * Dopłata nie powstaje nigdy, niezależnie od liczby wybranych zdjęć.
	 */
	public function testUnlimitedPackageNeverCharges(): void {
		$tally = PackageTally::calculate( 500, null, $this->price() );

		$this->assertSame( 500, $tally->included );
		$this->assertSame( 0, $tally->extra );
		$this->assertTrue( $tally->total->isZero() );
		$this->assertFalse( $tally->requiresPayment() );
		$this->assertNull( $tally->remainingInPackage() );
		$this->assertFalse( $tally->isAtPackageLimit() );
	}

	/**
	 * Pakiet zerowy: każde zdjęcie jest płatne. Tak wygląda galeria
	 * sprzedażowa bez zdjęć w cenie.
	 */
	public function testZeroPackageChargesForEveryPhoto(): void {
		$tally = PackageTally::calculate( 3, 0, $this->price( 40 ) );

		$this->assertSame( 0, $tally->included );
		$this->assertSame( 3, $tally->extra );
		$this->assertSame( 12000, $tally->total->minor );
	}

	public function testNothingSelectedCostsNothing(): void {
		$tally = PackageTally::calculate( 0, 20, $this->price() );

		$this->assertSame( 0, $tally->extra );
		$this->assertTrue( $tally->total->isZero() );
		$this->assertSame( 20, $tally->remainingInPackage() );
	}

	/**
	 * Dane wejściowe pochodzą z żądania HTTP, więc ujemna liczba jest możliwa.
	 * Musi dać zero, a nie ujemną kwotę do zapłaty.
	 */
	public function testNegativeInputNeverProducesNegativeMoney(): void {
		$tally = PackageTally::calculate( -5, 20, $this->price() );

		$this->assertSame( 0, $tally->selected );
		$this->assertSame( 0, $tally->extra );
		$this->assertTrue( $tally->total->isZero() );
	}

	public function testZeroPriceMeansNoPaymentEvenOverTheLimit(): void {
		$tally = PackageTally::calculate( 40, 20, Money::zero() );

		$this->assertSame( 20, $tally->extra );
		$this->assertTrue( $tally->total->isZero() );
		// Dwadzieścia zdjęć ponad pakiet, ale fotograf nie ustawił ceny —
		// nie wystawiamy zamówienia na zero złotych.
		$this->assertFalse( $tally->requiresPayment() );
	}

	public function testFractionalPriceStaysExact(): void {
		// 19,99 zł × 7 = 139,93 zł — bez błędu zaokrąglenia float.
		$tally = PackageTally::calculate( 27, 20, Money::fromMajor( 19.99 ) );

		$this->assertSame( 13993, $tally->total->minor );
	}

	public function testOnlySelectedStateCountsTowardsThePackage(): void {
		$this->assertTrue( SelectionState::Selected->countsTowardsPackage() );
		// Serduszko to jeszcze nie decyzja zakupowa.
		$this->assertFalse( SelectionState::Favorite->countsTowardsPackage() );
		$this->assertFalse( SelectionState::Rejected->countsTowardsPackage() );
	}
}
