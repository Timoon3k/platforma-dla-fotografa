<?php
declare( strict_types=1 );

namespace Kadr\Domain\Security;

/**
 * Token dostępu: link do galerii, magic link, token pobrania.
 *
 * Reguły, których ta klasa pilnuje (docs/SECURITY.md §3):
 *  - losowość z `random_bytes`, nigdy z `rand`, `uniqid` ani `time`,
 *  - w bazie trzymamy WYŁĄCZNIE hash — wyciek bazy nie daje działających linków,
 *  - porównanie hashy w czasie stałym, żeby nie dało się ich odgadywać pomiarem,
 *  - jawna wartość istnieje tylko raz, w momencie utworzenia.
 *
 * Wartość jawna jest `readonly` i nie ma settera — po wysłaniu linku klientowi
 * nie da się jej odtworzyć z bazy. To jest cecha, nie niedogodność.
 */
final readonly class SecureToken implements \Stringable {

	/** 32 bajty = 256 bitów entropii. */
	private const BYTES = 32;

	private function __construct(
		public string $plain,
		public string $hash,
	) {}

	public static function generate(): self {
		$plain = self::encode( random_bytes( self::BYTES ) );

		return new self( $plain, self::hash( $plain ) );
	}

	/**
	 * Odtworzenie z wartości otrzymanej w żądaniu — po to, żeby policzyć hash
	 * i poszukać go w bazie.
	 */
	public static function fromPlain( string $plain ): self {
		return new self( $plain, self::hash( trim( $plain ) ) );
	}

	public static function hash( string $plain ): string {
		return hash( 'sha256', trim( $plain ) );
	}

	/**
	 * Porównanie odporne na atak czasowy.
	 */
	public static function matches( string $plain, string $storedHash ): bool {
		return hash_equals( $storedHash, self::hash( $plain ) );
	}

	/**
	 * Czy ciąg w ogóle wygląda jak nasz token.
	 *
	 * Tania bramka przed odpytaniem bazy — chroni przed zasypywaniem
	 * zapytaniami przy próbie zgadywania.
	 */
	public static function looksValid( string $plain ): bool {
		$plain = trim( $plain );

		return 43 === strlen( $plain ) && 1 === preg_match( '/^[A-Za-z0-9_-]+$/', $plain );
	}

	public function __toString(): string {
		return $this->plain;
	}

	/**
	 * base64url bez wypełnienia — bezpieczne w URL-u i w nazwie pliku.
	 */
	private static function encode( string $bytes ): string {
		return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
	}
}
