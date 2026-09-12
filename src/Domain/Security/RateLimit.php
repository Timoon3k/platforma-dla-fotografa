<?php
declare( strict_types=1 );

namespace Kadr\Domain\Security;

/**
 * Reguła ograniczania liczby prób.
 *
 * Wartości pochodzą z docs/SECURITY.md §5. Trzymamy je w jednym miejscu,
 * żeby nie rozjechały się między kontrolerem a dokumentacją.
 */
final readonly class RateLimit {

	private function __construct(
		public string $key,
		public int $maxAttempts,
		public int $windowSeconds,
		public int $lockoutSeconds,
	) {}

	/** Logowanie fotografa — 5 prób na 15 minut na konto. */
	public static function photographerLogin( string $identifier ): self {
		return new self( 'login:' . self::normalise( $identifier ), 5, 900, 900 );
	}

	/** Logowanie po adresie IP — szerszy limit, żeby nie blokować biura. */
	public static function loginByAddress( string $addressHash ): self {
		return new self( 'login-ip:' . $addressHash, 20, 900, 900 );
	}

	/** Magic link — 3 wysyłki na 15 minut na adres. */
	public static function magicLink( string $email ): self {
		return new self( 'magic:' . self::normalise( $email ), 3, 900, 900 );
	}

	/**
	 * PIN galerii — 10 prób na godzinę na token.
	 *
	 * Po przekroczeniu blokujemy na godzinę i powiadamiamy fotografa:
	 * ktoś próbuje zgadnąć PIN do galerii jego klienta.
	 */
	public static function galleryPin( string $tokenHash ): self {
		return new self( 'pin:' . $tokenHash, 10, 3600, 3600 );
	}

	/** Pobranie pliku — ochrona przed zasysaniem całej galerii cudzym tokenem. */
	public static function download( string $tokenHash ): self {
		return new self( 'download:' . $tokenHash, 120, 3600, 600 );
	}

	/** Odczyty API dla zalogowanego fotografa. */
	public static function apiReads( int $tenantId ): self {
		return new self( 'api:' . $tenantId, 600, 60, 60 );
	}

	private static function normalise( string $value ): string {
		return hash( 'sha256', strtolower( trim( $value ) ) );
	}
}
