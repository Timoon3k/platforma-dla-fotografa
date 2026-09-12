<?php
declare( strict_types=1 );

namespace Kadr\Domain\Shared;

/**
 * Kwota pieniężna przechowywana w jednostkach podrzędnych (groszach).
 *
 * Nigdy nie używamy liczb zmiennoprzecinkowych do pieniędzy (docs/DATABASE.md §2).
 * Klasa należy do warstwy Domain — nie zna WordPressa.
 */
final readonly class Money {

	private function __construct(
		public int $minor,
		public string $currency,
	) {}

	public static function fromMinor( int $minor, string $currency = 'PLN' ): self {
		return new self( $minor, strtoupper( $currency ) );
	}

	public static function fromMajor( int|float $major, string $currency = 'PLN' ): self {
		return new self( (int) round( $major * 100 ), strtoupper( $currency ) );
	}

	public static function zero( string $currency = 'PLN' ): self {
		return new self( 0, strtoupper( $currency ) );
	}

	public function add( self $other ): self {
		$this->assertSameCurrency( $other );

		return new self( $this->minor + $other->minor, $this->currency );
	}

	public function subtract( self $other ): self {
		$this->assertSameCurrency( $other );

		return new self( $this->minor - $other->minor, $this->currency );
	}

	public function multiply( int $factor ): self {
		return new self( $this->minor * $factor, $this->currency );
	}

	/**
	 * Odejmuje procent, zaokrąglając w dół do pełnej jednostki podrzędnej.
	 */
	public function minusPercent( float $percent ): self {
		return new self( (int) floor( $this->minor * ( 1 - $percent / 100 ) ), $this->currency );
	}

	public function isZero(): bool {
		return 0 === $this->minor;
	}

	public function equals( self $other ): bool {
		return $this->minor === $other->minor && $this->currency === $other->currency;
	}

	public function major(): float {
		return $this->minor / 100;
	}

	private function assertSameCurrency( self $other ): void {
		if ( $this->currency !== $other->currency ) {
			throw new \InvalidArgumentException(
				sprintf( 'Nie można łączyć kwot w różnych walutach: %s i %s.', $this->currency, $other->currency )
			);
		}
	}
}
