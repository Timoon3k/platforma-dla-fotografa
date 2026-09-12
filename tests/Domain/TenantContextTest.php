<?php
declare( strict_types=1 );

namespace Kadr\Tests\Domain;

use Kadr\Domain\Tenancy\AccessDenied;
use Kadr\Domain\Tenancy\Capability;
use Kadr\Domain\Tenancy\Role;
use Kadr\Domain\Tenancy\TenantContext;
use Kadr\Domain\Tenancy\TenantId;
use Kadr\Tests\TestCase;

final class TenantContextTest extends TestCase {

	public function testOwnerHasEveryCapabilityInTheirOwnTenant(): void {
		$owner = TenantContext::for( TenantId::fromInt( 1 ), 10, Role::Owner );

		foreach ( Capability::cases() as $capability ) {
			$this->assertTrue( $owner->can( $capability ) );
		}
	}

	public function testMemberDoesNotSeeBillingOrTeam(): void {
		$member = TenantContext::for( TenantId::fromInt( 1 ), 11, Role::Member );

		$this->assertTrue( $member->can( Capability::ManageGalleries ) );
		$this->assertTrue( $member->can( Capability::ManageClients ) );
		// Asystentka biura odpowiada klientom, ale nie widzi przychodów (persona P3).
		$this->assertFalse( $member->can( Capability::ManageBilling ) );
		$this->assertFalse( $member->can( Capability::ViewAnalytics ) );
		$this->assertFalse( $member->can( Capability::ManageTeam ) );
	}

	public function testBillingAndTeamCannotBeDelegated(): void {
		$this->assertFalse( Capability::ManageBilling->isDelegatable() );
		$this->assertFalse( Capability::ManageTeam->isDelegatable() );
		$this->assertTrue( Capability::ManageGalleries->isDelegatable() );
	}

	public function testRequireThrowsWhenCapabilityIsMissing(): void {
		$member = TenantContext::for( TenantId::fromInt( 1 ), 11, Role::Member );

		$this->assertThrows( AccessDenied::class, static fn() => $member->require( Capability::ManageBilling ) );
	}

	public function testOwnershipIsLimitedToTheirOwnTenant(): void {
		$owner = TenantContext::for( TenantId::fromInt( 1 ), 10, Role::Owner );

		$this->assertTrue( $owner->owns( TenantId::fromInt( 1 ) ) );
		// Właściciel ma pełnię praw, ale wyłącznie w swoim tenancie.
		$this->assertFalse( $owner->owns( TenantId::fromInt( 2 ) ) );
	}

	public function testTenantIdRejectsNonPositiveValues(): void {
		$this->assertThrows( \InvalidArgumentException::class, static fn() => TenantId::fromInt( 0 ) );
		$this->assertThrows( \InvalidArgumentException::class, static fn() => TenantId::fromInt( -1 ) );
	}

	public function testExplicitCapabilityListOverridesRoleDefaults(): void {
		$limited = TenantContext::for(
			TenantId::fromInt( 1 ),
			12,
			Role::Member,
			array( Capability::AccessApp )
		);

		$this->assertTrue( $limited->can( Capability::AccessApp ) );
		$this->assertFalse( $limited->can( Capability::ManageGalleries ) );
	}
}
