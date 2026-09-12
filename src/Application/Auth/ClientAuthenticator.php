<?php
declare( strict_types=1 );

namespace Kadr\Application\Auth;

use Kadr\Domain\Security\AccessGrant;
use Kadr\Domain\Security\RateLimit;
use Kadr\Domain\Security\SecureToken;
use Kadr\Domain\Security\Throttle;
use Kadr\Domain\Shared\Clock;
use Kadr\Domain\Shared\Result;
use Kadr\Infrastructure\Database\Repositories\ClientRepository;
use Kadr\Infrastructure\Database\Repositories\ClientSessionRepository;

/**
 * Uwierzytelnianie klienta fotografa.
 *
 * Domyślną ścieżką jest magic link, nie hasło. Klientka z persony P4 nie
 * założy konta z hasłem o 22:30 na telefonie — a zmuszanie jej do tego
 * kosztuje fotografa wybór zdjęć, czyli pieniądze.
 *
 * Reguły pilnowane tutaj:
 *  - magic link jest JEDNORAZOWY i żyje 15 minut,
 *  - w bazie leży wyłącznie hash tokenu,
 *  - brak konta o danym adresie NIE jest rozróżnialny od sukcesu —
 *    inaczej formularz staje się wyszukiwarką klientów fotografa,
 *  - każda ścieżka przechodzi przez throttling.
 */
final readonly class ClientAuthenticator {

	public const SESSION_DAYS = 30;

	public function __construct(
		private ClientRepository $clients,
		private ClientSessionRepository $sessions,
		private Throttle $throttle,
		private Clock $clock,
	) {}

	/**
	 * Wysłanie magic linku.
	 *
	 * Zwraca token do wysyłki mailem WYŁĄCZNIE wtedy, gdy klient istnieje.
	 * Wywołujący w obu wypadkach pokazuje ten sam komunikat — patrz test
	 * `testDoesNotRevealWhetherTheClientExists`.
	 */
	public function requestMagicLink( string $email, ?string $ipHash = null ): Result {
		$limit = RateLimit::magicLink( $email );

		if ( ! $this->throttle->isAllowed( $limit ) ) {
			return Result::failure(
				'kadr_rate_limited',
				'Zbyt wiele prób. Spróbuj ponownie za chwilę.',
				array( 'retry_after' => $this->throttle->retryAfter( $limit ) )
			);
		}

		$this->throttle->record( $limit );

		$client = $this->clients->findByEmail( $email );

		if ( null === $client ) {
			// Cisza zamiast błędu: odpowiedź musi wyglądać identycznie
			// jak przy istniejącym koncie.
			return Result::success( null );
		}

		$token = SecureToken::generate();

		$this->sessions->create(
			clientId:  (int) $client['id'],
			tokenHash: $token->hash,
			purpose:   ClientSessionRepository::PURPOSE_MAGIC,
			expiresAt: $this->in( AccessGrant::magicLinkLifetimeMinutes() * 60 ),
			ipHash:    $ipHash,
		);

		return Result::success(
			array(
				'token'  => $token->plain,
				'client' => $client,
			)
		);
	}

	/**
	 * Wymiana magic linku na sesję.
	 */
	public function redeemMagicLink( string $plainToken, ?string $ipHash = null ): Result {
		if ( ! SecureToken::looksValid( $plainToken ) ) {
			// Tania bramka przed odpytaniem bazy przy zgadywaniu.
			return $this->invalidLink();
		}

		$hash = SecureToken::hash( $plainToken );
		$row  = $this->sessions->findByTokenHash( $hash );

		if ( null === $row || ClientSessionRepository::PURPOSE_MAGIC !== ( $row['purpose'] ?? '' ) ) {
			return $this->invalidLink();
		}

		// Jednorazowość: wykorzystany link przestaje działać natychmiast.
		if ( null !== ( $row['used_at'] ?? null ) ) {
			return $this->invalidLink();
		}

		$grant = AccessGrant::fromRow( $row );

		if ( ! $grant->isUsableAt( $this->clock ) ) {
			return $this->invalidLink();
		}

		$this->sessions->markUsed( $hash, $this->now() );

		$session = SecureToken::generate();

		$this->sessions->create(
			clientId:  (int) $row['client_id'],
			tokenHash: $session->hash,
			purpose:   ClientSessionRepository::PURPOSE_SESSION,
			expiresAt: $this->in( self::SESSION_DAYS * 86400 ),
			ipHash:    $ipHash,
		);

		return Result::success(
			array(
				'session_token' => $session->plain,
				'client_id'     => (int) $row['client_id'],
			)
		);
	}

	/**
	 * Sprawdzenie tokenu sesji przy każdym żądaniu.
	 *
	 * @return int|null Identyfikator klienta albo null.
	 */
	public function authenticate( string $plainToken ): ?int {
		if ( ! SecureToken::looksValid( $plainToken ) ) {
			return null;
		}

		$row = $this->sessions->findByTokenHash( SecureToken::hash( $plainToken ) );

		if ( null === $row || ClientSessionRepository::PURPOSE_SESSION !== ( $row['purpose'] ?? '' ) ) {
			return null;
		}

		if ( ! AccessGrant::fromRow( $row )->isUsableAt( $this->clock ) ) {
			return null;
		}

		return (int) $row['client_id'];
	}

	public function logout( string $plainToken ): void {
		if ( ! SecureToken::looksValid( $plainToken ) ) {
			return;
		}

		$this->sessions->revoke( SecureToken::hash( $plainToken ), $this->now() );
	}

	public function logoutEverywhere( int $clientId ): int {
		return $this->sessions->revokeAllFor( $clientId, $this->now() );
	}

	private function invalidLink(): Result {
		// Jeden komunikat na wszystkie powody odmowy — nieważny, wygasły
		// i już wykorzystany link są dla atakującego nie do odróżnienia.
		return Result::failure( 'kadr_invalid_link', 'Link wygasł lub został już użyty. Poproś o nowy.' );
	}

	private function now(): string {
		return $this->clock->now()->format( 'Y-m-d H:i:s' );
	}

	private function in( int $seconds ): string {
		return $this->clock->now()->modify( sprintf( '+%d seconds', $seconds ) )->format( 'Y-m-d H:i:s' );
	}
}
