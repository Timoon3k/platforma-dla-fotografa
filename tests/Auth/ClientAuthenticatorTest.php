<?php
declare( strict_types=1 );

namespace Kadr\Tests\Auth;

use Kadr\Application\Auth\ClientAuthenticator;
use Kadr\Domain\Security\RateLimit;
use Kadr\Domain\Shared\FrozenClock;
use Kadr\Infrastructure\Database\Repositories\ClientRepository;
use Kadr\Infrastructure\Database\Repositories\ClientSessionRepository;
use Kadr\Infrastructure\Security\InMemoryThrottle;
use Kadr\Tests\Support\TestDatabase;
use Kadr\Tests\TestCase;

/**
 * Uwierzytelnianie klienta — magic link i sesje (ADR-003).
 */
final class ClientAuthenticatorTest extends TestCase {

	private FrozenClock $clock;
	private ClientRepository $clients;
	private ClientSessionRepository $sessions;
	private InMemoryThrottle $throttle;

	private function makeAuth( int $tenantId = 1 ): ClientAuthenticator {
		$db             = $this->db ??= TestDatabase::migrated();
		$this->clock    = $this->clock ?? new FrozenClock( '2026-04-01 10:00:00' );
		$tenant         = TestDatabase::tenant( $tenantId );
		$this->clients  = new ClientRepository( $db, $tenant );
		$this->sessions = new ClientSessionRepository( $db, $tenant );
		$this->throttle = $this->throttle ?? new InMemoryThrottle( $this->clock );

		return new ClientAuthenticator( $this->clients, $this->sessions, $this->throttle, $this->clock );
	}

	private mixed $db = null;

	private function reset(): void {
		$this->db       = null;
		$this->clock    = new FrozenClock( '2026-04-01 10:00:00' );
		$this->throttle = new InMemoryThrottle( $this->clock );
	}

	public function testMagicLinkLogsTheClientIn(): void {
		$this->reset();
		$auth = $this->makeAuth();
		$this->clients->create( 'Kasia', 'kasia@example.test' );

		$requested = $auth->requestMagicLink( 'kasia@example.test' );
		$this->assertTrue( $requested->ok );

		$redeemed = $auth->redeemMagicLink( $requested->value['token'] );
		$this->assertTrue( $redeemed->ok );

		$clientId = $auth->authenticate( $redeemed->value['session_token'] );
		$this->assertSame( $redeemed->value['client_id'], $clientId );
	}

	/**
	 * Formularz „wyślij link” nie może stać się wyszukiwarką klientów fotografa.
	 */
	public function testDoesNotRevealWhetherTheClientExists(): void {
		$this->reset();
		$auth = $this->makeAuth();

		$unknown = $auth->requestMagicLink( 'nieznany@example.test' );

		// Sukces, ale bez tokenu — wywołujący pokazuje ten sam komunikat
		// co przy istniejącym koncie.
		$this->assertTrue( $unknown->ok );
		$this->assertNull( $unknown->value );
	}

	/**
	 * Link z historii przeglądarki albo z przeskanowanej skrzynki
	 * nie może zalogować po raz drugi.
	 */
	public function testMagicLinkWorksOnlyOnce(): void {
		$this->reset();
		$auth = $this->makeAuth();
		$this->clients->create( 'Kasia', 'kasia@example.test' );

		$token = $auth->requestMagicLink( 'kasia@example.test' )->value['token'];

		$this->assertTrue( $auth->redeemMagicLink( $token )->ok );

		$second = $auth->redeemMagicLink( $token );
		$this->assertFalse( $second->ok );
		$this->assertSame( 'kadr_invalid_link', $second->code );
	}

	public function testExpiredMagicLinkIsRefused(): void {
		$this->reset();
		$auth = $this->makeAuth();
		$this->clients->create( 'Kasia', 'kasia@example.test' );

		$token = $auth->requestMagicLink( 'kasia@example.test' )->value['token'];

		$this->clock->advance( '+16 minutes' );

		$this->assertFalse( $auth->redeemMagicLink( $token )->ok );
	}

	/**
	 * Wszystkie powody odmowy dają ten sam komunikat — nieważny, wygasły
	 * i zużyty link są dla atakującego nie do odróżnienia.
	 */
	public function testAllRefusalsLookTheSame(): void {
		$this->reset();
		$auth = $this->makeAuth();
		$this->clients->create( 'Kasia', 'kasia@example.test' );

		$used = $auth->requestMagicLink( 'kasia@example.test' )->value['token'];
		$auth->redeemMagicLink( $used );

		$messages = array(
			$auth->redeemMagicLink( $used )->message,
			$auth->redeemMagicLink( str_repeat( 'A', 43 ) )->message,
			$auth->redeemMagicLink( 'krótki' )->message,
		);

		$this->assertSame( 1, count( array_unique( $messages ) ) );
	}

	public function testSessionExpires(): void {
		$this->reset();
		$auth = $this->makeAuth();
		$this->clients->create( 'Kasia', 'kasia@example.test' );

		$token   = $auth->requestMagicLink( 'kasia@example.test' )->value['token'];
		$session = $auth->redeemMagicLink( $token )->value['session_token'];

		$this->assertTrue( null !== $auth->authenticate( $session ) );

		$this->clock->advance( '+31 days' );
		$this->assertNull( $auth->authenticate( $session ) );
	}

	public function testLogoutRevokesTheSessionImmediately(): void {
		$this->reset();
		$auth = $this->makeAuth();
		$this->clients->create( 'Kasia', 'kasia@example.test' );

		$token   = $auth->requestMagicLink( 'kasia@example.test' )->value['token'];
		$session = $auth->redeemMagicLink( $token )->value['session_token'];

		$auth->logout( $session );

		$this->assertNull( $auth->authenticate( $session ) );
	}

	public function testLogoutEverywhereRevokesAllSessions(): void {
		$this->reset();
		$auth = $this->makeAuth();
		$this->clients->create( 'Kasia', 'kasia@example.test' );

		$sessions = array();

		for ( $i = 0; $i < 3; $i++ ) {
			$token      = $auth->requestMagicLink( 'kasia@example.test' )->value['token'];
			$sessions[] = $auth->redeemMagicLink( $token )->value['session_token'];
			$this->throttle->clear( RateLimit::magicLink( 'kasia@example.test' ) );
		}

		$clientId = $auth->authenticate( $sessions[0] );
		$auth->logoutEverywhere( (int) $clientId );

		foreach ( $sessions as $session ) {
			$this->assertNull( $auth->authenticate( $session ) );
		}
	}

	/**
	 * Magic link nie jest tokenem sesji i odwrotnie — inaczej link z maila
	 * dawałby dostęp bezterminowo.
	 */
	public function testMagicLinkCannotBeUsedAsASessionToken(): void {
		$this->reset();
		$auth = $this->makeAuth();
		$this->clients->create( 'Kasia', 'kasia@example.test' );

		$token = $auth->requestMagicLink( 'kasia@example.test' )->value['token'];

		$this->assertNull( $auth->authenticate( $token ) );
	}

	public function testSessionTokenCannotBeRedeemedAsAMagicLink(): void {
		$this->reset();
		$auth = $this->makeAuth();
		$this->clients->create( 'Kasia', 'kasia@example.test' );

		$token   = $auth->requestMagicLink( 'kasia@example.test' )->value['token'];
		$session = $auth->redeemMagicLink( $token )->value['session_token'];

		$this->assertFalse( $auth->redeemMagicLink( $session )->ok );
	}

	public function testThrottlingStopsMagicLinkFlooding(): void {
		$this->reset();
		$auth = $this->makeAuth();
		$this->clients->create( 'Kasia', 'kasia@example.test' );

		for ( $i = 0; $i < 3; $i++ ) {
			$this->assertTrue( $auth->requestMagicLink( 'kasia@example.test' )->ok );
		}

		$blocked = $auth->requestMagicLink( 'kasia@example.test' );

		$this->assertFalse( $blocked->ok );
		$this->assertSame( 'kadr_rate_limited', $blocked->code );
		$this->assertTrue( $blocked->details['retry_after'] > 0 );
	}

	public function testThrottleWindowReopensAfterTheLockout(): void {
		$this->reset();
		$auth = $this->makeAuth();
		$this->clients->create( 'Kasia', 'kasia@example.test' );

		for ( $i = 0; $i < 4; $i++ ) {
			$auth->requestMagicLink( 'kasia@example.test' );
		}

		$this->clock->advance( '+16 minutes' );

		$this->assertTrue( $auth->requestMagicLink( 'kasia@example.test' )->ok );
	}

	/**
	 * Sesja klienta jednego fotografa nie działa u drugiego.
	 */
	public function testSessionsAreScopedToTheTenant(): void {
		$this->reset();
		$authA = $this->makeAuth( 1 );
		$this->clients->create( 'Kasia', 'kasia@example.test' );

		$token   = $authA->requestMagicLink( 'kasia@example.test' )->value['token'];
		$session = $authA->redeemMagicLink( $token )->value['session_token'];

		$authB = $this->makeAuth( 2 );

		$this->assertNull( $authB->authenticate( $session ) );
	}
}
