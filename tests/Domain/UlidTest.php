<?php
declare( strict_types=1 );

namespace Kadr\Tests\Domain;

use Kadr\Domain\Shared\FrozenClock;
use Kadr\Domain\Shared\Ulid;
use Kadr\Tests\TestCase;

final class UlidTest extends TestCase {

	public function testHasTwentySixCharacters(): void {
		$this->assertSame( 26, strlen( (string) Ulid::generate() ) );
	}

	public function testIsUniqueAcrossManyGenerations(): void {
		$seen = array();

		for ( $i = 0; $i < 2000; $i++ ) {
			$seen[ (string) Ulid::generate() ] = true;
		}

		$this->assertSame( 2000, count( $seen ) );
	}

	/**
	 * Sortowalność czasowa to powód wyboru ULID zamiast UUIDv4 (docs/DATABASE.md §2):
	 * indeks nie fragmentuje się przy wstawianiu.
	 */
	public function testIsTimeSortable(): void {
		$clock = new FrozenClock( '2026-01-01 00:00:00' );
		$early  = (string) Ulid::generate( $clock );

		$clock->advance( '+1 day' );
		$later = (string) Ulid::generate( $clock );

		$this->assertTrue( $early < $later );
	}

	public function testRejectsMalformedInput(): void {
		$this->assertNull( Ulid::tryFrom( 'za-krotki' ) );
		// I, L, O i U nie należą do alfabetu Crockforda.
		$this->assertNull( Ulid::tryFrom( str_repeat( 'I', 26 ) ) );
		$this->assertThrows( \InvalidArgumentException::class, static fn() => Ulid::fromString( 'x' ) );
	}

	public function testAcceptsItsOwnOutput(): void {
		$id = Ulid::generate();

		$this->assertTrue( Ulid::fromString( (string) $id )->equals( $id ) );
		// Odporność na dane z URL-a: spacje i małe litery.
		$this->assertTrue( Ulid::fromString( ' ' . strtolower( (string) $id ) . ' ' )->equals( $id ) );
	}
}
