<?php
declare( strict_types=1 );

namespace Kadr\Tests\Domain;

use Kadr\Domain\Shared\Money;
use Kadr\Tests\TestCase;

final class MoneyTest extends TestCase {

	public function testStoresAmountInMinorUnits(): void {
		$this->assertSame( 6900, Money::fromMajor( 69 )->minor );
		$this->assertSame( 69.0, Money::fromMajor( 69 )->major() );
	}

	public function testRoundsFractionalMajorAmounts(): void {
		// 0.1 + 0.2 w float nie daje 0.3 — dlatego pieniądze trzymamy w groszach.
		$this->assertSame( 1999, Money::fromMajor( 19.99 )->minor );
		$this->assertSame( 12425, Money::fromMajor( 124.245 )->minor );
	}

	public function testAddsAndSubtracts(): void {
		$a = Money::fromMajor( 149 );
		$b = Money::fromMajor( 29 );

		$this->assertSame( 17800, $a->add( $b )->minor );
		$this->assertSame( 12000, $a->subtract( $b )->minor );
	}

	public function testRefusesToMixCurrencies(): void {
		$this->assertThrows(
			\InvalidArgumentException::class,
			static fn() => Money::fromMajor( 10, 'PLN' )->add( Money::fromMajor( 10, 'EUR' ) )
		);
	}

	public function testIsImmutable(): void {
		$original = Money::fromMajor( 100 );
		$original->add( Money::fromMajor( 50 ) );

		$this->assertSame( 10000, $original->minor );
	}

	public function testMinusPercentRoundsDown(): void {
		$this->assertSame( 8283, Money::fromMajor( 99.8 )->minusPercent( 17 )->minor );
	}
}
