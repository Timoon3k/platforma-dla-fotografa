<?php
declare( strict_types=1 );

namespace Kadr\Domain\Shared;

/**
 * ULID — publiczny identyfikator encji.
 *
 * Dlaczego nie sekwencyjne ID: identyfikator trafia do URL-i i do API,
 * a sekwencyjny numer pozwala policzyć, ilu masz klientów, i zgadnąć cudzy
 * zasób (docs/SECURITY.md, zagrożenie T3).
 *
 * Dlaczego nie UUIDv4: ULID jest sortowalny czasowo, więc indeks nie
 * fragmentuje się przy wstawianiu, a kolejność wstawiania jest odtwarzalna.
 *
 * 26 znaków w alfabecie Crockford base32: 48 bitów znacznika czasu (ms)
 * + 80 bitów losowości.
 */
final readonly class Ulid implements \Stringable {

	private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
	private const LENGTH   = 26;

	private function __construct( public string $value ) {}

	public static function generate( ?Clock $clock = null ): self {
		$clock = $clock ?? new SystemClock();

		return new self(
			self::encodeTime( $clock->nowMilliseconds() ) . self::encodeRandom()
		);
	}

	/**
	 * @throws \InvalidArgumentException Gdy ciąg nie jest poprawnym ULID-em.
	 */
	public static function fromString( string $value ): self {
		$value = strtoupper( trim( $value ) );

		if ( ! self::isValid( $value ) ) {
			throw new \InvalidArgumentException( 'Niepoprawny ULID.' );
		}

		return new self( $value );
	}

	/**
	 * Bezpieczna wersja dla danych z żądania — zwraca null zamiast rzucać.
	 */
	public static function tryFrom( string $value ): ?self {
		$value = strtoupper( trim( $value ) );

		return self::isValid( $value ) ? new self( $value ) : null;
	}

	public static function isValid( string $value ): bool {
		if ( self::LENGTH !== strlen( $value ) ) {
			return false;
		}

		return 1 === preg_match( '/^[0-9ABCDEFGHJKMNPQRSTVWXYZ]{26}$/', $value );
	}

	public function equals( self $other ): bool {
		return $this->value === $other->value;
	}

	public function __toString(): string {
		return $this->value;
	}

	private static function encodeTime( int $milliseconds ): string {
		$encoded = '';

		for ( $i = 0; $i < 10; $i++ ) {
			$encoded      = self::ALPHABET[ $milliseconds % 32 ] . $encoded;
			$milliseconds = intdiv( $milliseconds, 32 );
		}

		return $encoded;
	}

	private static function encodeRandom(): string {
		$encoded = '';

		for ( $i = 0; $i < 16; $i++ ) {
			$encoded .= self::ALPHABET[ random_int( 0, 31 ) ];
		}

		return $encoded;
	}
}
