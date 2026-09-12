<?php
declare( strict_types=1 );

namespace Kadr\Tests\Domain;

use Kadr\Domain\Billing\Entitlements;
use Kadr\Domain\Billing\PlanRegistry;
use Kadr\Tests\TestCase;

final class EntitlementsTest extends TestCase {

	public function testFallsBackToThePlanWhenNoOverrideExists(): void {
		$e = new Entitlements( PlanRegistry::get( 'studio' ) );

		$this->assertTrue( $e->allows( 'products_prints' ) );
		$this->assertFalse( $e->allows( 'custom_domain' ) );
		$this->assertSame( 150, $e->limit( 'gallery_limit' )->value );
	}

	/**
	 * Dodatki (storage, seaty, domena) działają przez odstępstwa,
	 * nigdy przez podmianę planu — docs/BILLING.md §4.
	 */
	public function testOverrideWinsOverThePlan(): void {
		$e = new Entitlements(
			PlanRegistry::get( 'starter' ),
			array( 'custom_domain' => true, 'team_seats' => 3 )
		);

		$this->assertTrue( $e->allows( 'custom_domain' ) );
		$this->assertSame( 3, $e->limit( 'team_seats' )->value );
		// Entitlementy nieobjęte odstępstwem pozostają z planu.
		$this->assertFalse( $e->allows( 'products_prints' ) );
	}

	public function testOverrideCanGrantUnlimited(): void {
		$e = new Entitlements( PlanRegistry::get( 'starter' ), array( 'gallery_limit' => null ) );

		$this->assertTrue( $e->limit( 'gallery_limit' )->isUnlimited() );
	}

	public function testHasRoomForRespectsTheBoundary(): void {
		$e = new Entitlements( PlanRegistry::get( 'free' ) );

		$this->assertTrue( $e->hasRoomFor( 'gallery_limit', 4 ) );
		$this->assertFalse( $e->hasRoomFor( 'gallery_limit', 5 ) );
		$this->assertFalse( $e->hasRoomFor( 'gallery_limit', 3, 3 ) );
	}

	public function testUnknownKeyDenies(): void {
		$e = new Entitlements( PlanRegistry::get( 'pro' ) );

		$this->assertFalse( $e->allows( 'funkcja_ktorej_nie_ma' ) );
		$this->assertFalse( $e->hasRoomFor( 'limit_ktorego_nie_ma', 0 ) );
	}
}
