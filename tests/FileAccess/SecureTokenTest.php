<?php
declare( strict_types=1 );

namespace Kadr\Tests\FileAccess;

use Kadr\Domain\Security\AccessGrant;
use Kadr\Domain\Security\SecureToken;
use Kadr\Domain\Shared\FrozenClock;
use Kadr\Tests\TestCase;

final class SecureTokenTest extends TestCase {

	public function testTokensAreUnique(): void {
		$seen = array();

		for ( $i = 0; $i < 1000; $i++ ) {
			$seen[ (string) SecureToken::generate() ] = true;
		}

		$this->assertSame( 1000, count( $seen ) );
	}

	/**
	 * W bazie trzymamy hash, nigdy token. Wyciek bazy nie daje działających linków.
	 */
	public function testHashIsNotTheToken(): void {
		$token = SecureToken::generate();

		$this->assertFalse( $token->plain === $token->hash );
		$this->assertSame( 64, strlen( $token->hash ) );
	}

	public function testHashIsReproducibleFromThePlainValue(): void {
		$token = SecureToken::generate();

		$this->assertSame( $token->hash, SecureToken::fromPlain( $token->plain )->hash );
		$this->assertTrue( SecureToken::matches( $token->plain, $token->hash ) );
	}

	public function testWrongTokenDoesNotMatch(): void {
		$token = SecureToken::generate();

		$this->assertFalse( SecureToken::matches( (string) SecureToken::generate(), $token->hash ) );
		$this->assertFalse( SecureToken::matches( '', $token->hash ) );
	}

	public function testShapeCheckRejectsGuessesBeforeTouchingTheDatabase(): void {
		$this->assertTrue( SecureToken::looksValid( (string) SecureToken::generate() ) );
		$this->assertFalse( SecureToken::looksValid( 'abc' ) );
		$this->assertFalse( SecureToken::looksValid( str_repeat( 'a', 43 ) . '!' ) );
		$this->assertFalse( SecureToken::looksValid( '' ) );
	}

	public function testTokenIsUrlSafe(): void {
		for ( $i = 0; $i < 200; $i++ ) {
			$plain = (string) SecureToken::generate();

			$this->assertSame( $plain, rawurlencode( $plain ) );
		}
	}
}

/**
 * Trzy niezależne powody odmowy dostępu.
 */
final class AccessGrantTest extends TestCase {

	public function testValidGrantIsUsable(): void {
		$clock = new FrozenClock( '2026-03-01 12:00:00' );
		$grant = new AccessGrant( 'hash', new \DateTimeImmutable( '2026-03-02 12:00:00' ) );

		$this->assertTrue( $grant->isUsableAt( $clock ) );
		$this->assertNull( $grant->denialReason( $clock ) );
	}

	public function testExpiredGrantIsRefused(): void {
		$clock = new FrozenClock( '2026-03-03 12:00:00' );
		$grant = new AccessGrant( 'hash', new \DateTimeImmutable( '2026-03-02 12:00:00' ) );

		$this->assertFalse( $grant->isUsableAt( $clock ) );
		$this->assertSame( 'expired', $grant->denialReason( $clock ) );
	}

	public function testRevocationBeatsEverythingElse(): void {
		$clock = new FrozenClock( '2026-03-01 12:00:00' );
		$grant = new AccessGrant(
			'hash',
			new \DateTimeImmutable( '2030-01-01 00:00:00' ),
			new \DateTimeImmutable( '2026-02-01 00:00:00' )
		);

		$this->assertSame( 'revoked', $grant->denialReason( $clock ) );
	}

	public function testUseLimitIsEnforced(): void {
		$clock = new FrozenClock( '2026-03-01 12:00:00' );
		$far   = new \DateTimeImmutable( '2030-01-01 00:00:00' );

		$this->assertTrue( ( new AccessGrant( 'h', $far, null, 3, 2 ) )->isUsableAt( $clock ) );
		$this->assertSame( 'exhausted', ( new AccessGrant( 'h', $far, null, 3, 3 ) )->denialReason( $clock ) );
		$this->assertSame( 1, ( new AccessGrant( 'h', $far, null, 3, 2 ) )->remainingUses() );
	}

	public function testUnlimitedUsesAreAllowed(): void {
		$clock = new FrozenClock( '2026-03-01 12:00:00' );
		$grant = new AccessGrant( 'h', new \DateTimeImmutable( '2030-01-01 00:00:00' ), null, null, 9999 );

		$this->assertTrue( $grant->isUsableAt( $clock ) );
		$this->assertNull( $grant->remainingUses() );
	}

	public function testGrantWithoutExpiryIsStillRevocable(): void {
		$clock = new FrozenClock( '2026-03-01 12:00:00' );

		$this->assertTrue( ( new AccessGrant( 'h', null ) )->isUsableAt( $clock ) );
		$this->assertSame(
			'revoked',
			( new AccessGrant( 'h', null, new \DateTimeImmutable( '2026-01-01 00:00:00' ) ) )->denialReason( $clock )
		);
	}

	public function testMagicLinkLifetimeIsShort(): void {
		// Magic link trafia do skrzynki e-mail — im krócej żyje, tym lepiej.
		$this->assertSame( 15, AccessGrant::magicLinkLifetimeMinutes() );
		$this->assertSame( 24, AccessGrant::downloadLifetimeHours() );
	}
}
