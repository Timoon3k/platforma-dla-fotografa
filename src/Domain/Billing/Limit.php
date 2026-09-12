<?php
declare( strict_types=1 );

namespace Kadr\Domain\Billing;

/**
 * Limit ilościowy wynikający z planu. `null` oznacza brak limitu.
 */
final readonly class Limit {

	private function __construct( public ?int $value ) {}

	public static function of( int $value ): self {
		return new self( $value );
	}

	public static function unlimited(): self {
		return new self( null );
	}

	public function isUnlimited(): bool {
		return null === $this->value;
	}

	public function isReachedBy( int $current ): bool {
		return ! $this->isUnlimited() && $current >= $this->value;
	}

	public function remaining( int $current ): ?int {
		return $this->isUnlimited() ? null : max( 0, $this->value - $current );
	}
}
