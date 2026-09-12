<?php
declare( strict_types=1 );

namespace Kadr\Tests\Domain;

use Kadr\Domain\Billing\Entitlements;
use Kadr\Domain\Billing\PlanRegistry;
use Kadr\Tests\TestCase;

final class PlanTest extends TestCase {

	/**
	 * Regresja: `null` w definicji planu znaczy „bez limitu”.
	 * Wcześniejsza implementacja używała `?? 0`, a operator `??` reaguje na null —
	 * przez co plan Pro raportował limit 0 galerii i blokowałby najdroższych klientów.
	 */
	public function testNullMeansUnlimitedNotZero(): void {
		$pro = PlanRegistry::get( 'pro' );

		$this->assertTrue( $pro->limit( 'gallery_limit' )->isUnlimited() );
		$this->assertFalse( $pro->limit( 'gallery_limit' )->isReachedBy( 100000 ) );
		$this->assertNull( $pro->limit( 'gallery_limit' )->remaining( 100000 ) );
	}

	/**
	 * Nieznany klucz to bezpieczna odmowa, nie przypadkowa nieskończoność.
	 */
	public function testUnknownKeyDeniesRatherThanAllows(): void {
		$starter = PlanRegistry::get( 'starter' );

		$this->assertSame( 0, $starter->limit( 'nieistniejacy_klucz' )->value );
		$this->assertTrue( $starter->limit( 'nieistniejacy_klucz' )->isReachedBy( 0 ) );
		$this->assertFalse( $starter->allows( 'nieistniejacy_klucz' ) );
	}

	public function testFiniteLimitsAreEnforcedAtTheBoundary(): void {
		$starter = PlanRegistry::get( 'starter' );

		$this->assertFalse( $starter->limit( 'gallery_limit' )->isReachedBy( 29 ) );
		$this->assertTrue( $starter->limit( 'gallery_limit' )->isReachedBy( 30 ) );
		$this->assertSame( 1, $starter->limit( 'gallery_limit' )->remaining( 29 ) );
	}

	public function testEveryPlanDefinesEveryEntitlementKey(): void {
		$keys = array_keys( PlanRegistry::get( 'pro' )->entitlements );

		foreach ( PlanRegistry::all() as $key => $plan ) {
			foreach ( $keys as $entitlement ) {
				$this->assertTrue(
					$plan->has( $entitlement ),
					sprintf( 'Plan "%s" nie definiuje entitlementu "%s".', $key, $entitlement )
				);
			}
		}
	}

	public function testYearlySavingIsTwoMonthsFree(): void {
		foreach ( PlanRegistry::paid() as $key => $plan ) {
			$this->assertSame(
				17,
				$plan->yearlySavingPercent(),
				sprintf( 'Plan "%s" nie daje deklarowanych ~17%% oszczędności rocznie.', $key )
			);
		}
	}

	public function testFreePlanCostsNothingAndIsNotRecommended(): void {
		$free = PlanRegistry::get( 'free' );

		$this->assertTrue( $free->isFree() );
		$this->assertSame( 0, $free->yearlySavingPercent() );
		$this->assertFalse( $free->recommended );
	}

	/**
	 * Free tier MA włączoną sprzedaż dodatkowych zdjęć (ADR-008).
	 * To nie jest przeoczenie — fotograf musi przeżyć moment, w którym klient dopłaca.
	 */
	public function testFreePlanCanSellExtraPhotos(): void {
		$this->assertTrue( PlanRegistry::get( 'free' )->allows( 'sell_extra_photos' ) );
	}

	public function testExactlyOnePlanIsRecommended(): void {
		$recommended = array_filter( PlanRegistry::all(), static fn( $p ) => $p->recommended );

		$this->assertSame( 1, count( $recommended ) );
		$this->assertSame( 'studio', array_key_first( $recommended ) );
	}

	public function testPricesMatchTheDocumentedTable(): void {
		$expected = array(
			'free'    => array( 0, 0 ),
			'starter' => array( 69, 690 ),
			'studio'  => array( 149, 1490 ),
			'pro'     => array( 299, 2990 ),
		);

		foreach ( $expected as $key => [$monthly, $yearly] ) {
			$plan = PlanRegistry::get( $key );
			$this->assertSame( (float) $monthly, $plan->monthly->major(), "Cena miesięczna planu $key" );
			$this->assertSame( (float) $yearly, $plan->yearly->major(), "Cena roczna planu $key" );
		}
	}
}
