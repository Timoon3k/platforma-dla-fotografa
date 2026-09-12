<?php
declare( strict_types=1 );

namespace Kadr\Domain\Billing;

use Kadr\Domain\Shared\Money;

/**
 * Plan abonamentowy wraz z pełnym zestawem entitlementów.
 *
 * Plany żyją w kodzie, nie w bazie (ADR-008). W bazie zapisujemy wyłącznie
 * odstępstwa od planu (`entitlement_overrides`).
 */
final readonly class Plan {

	/**
	 * @param array<string, scalar|null> $entitlements
	 */
	public function __construct(
		public string $key,
		public string $name,
		public Money $monthly,
		public Money $yearly,
		public array $entitlements,
		public bool $recommended = false,
	) {}

	public function isFree(): bool {
		return $this->monthly->isZero();
	}

	/**
	 * Oszczędność przy płatności rocznej, wyrażona w kwocie.
	 */
	public function yearlySaving(): Money {
		return $this->monthly->multiply( 12 )->subtract( $this->yearly );
	}

	/**
	 * Oszczędność roczna w procentach, zaokrąglona do pełnych procent.
	 */
	public function yearlySavingPercent(): int {
		$full = $this->monthly->multiply( 12 );

		if ( $full->isZero() ) {
			return 0;
		}

		return (int) round( $this->yearlySaving()->minor / $full->minor * 100 );
	}

	/**
	 * Efektywna cena miesięczna przy rozliczeniu rocznym.
	 */
	public function yearlyPerMonth(): Money {
		return Money::fromMinor( (int) round( $this->yearly->minor / 12 ), $this->yearly->currency );
	}

	public function has( string $key ): bool {
		return array_key_exists( $key, $this->entitlements );
	}

	public function allows( string $key ): bool {
		return true === ( $this->entitlements[ $key ] ?? false );
	}

	/**
	 * UWAGA: `null` w definicji planu oznacza BRAK LIMITU, a nie brak wartości.
	 * Dlatego sprawdzamy obecność klucza przez array_key_exists, nie przez `??` —
	 * operator `??` reaguje na null i zamieniłby „bez limitu” na limit zerowy.
	 * Nieznany klucz daje limit 0 (bezpieczna odmowa), nie nieskończoność.
	 */
	public function limit( string $key ): Limit {
		if ( ! $this->has( $key ) ) {
			return Limit::of( 0 );
		}

		$value = $this->entitlements[ $key ];

		return null === $value ? Limit::unlimited() : Limit::of( (int) $value );
	}

	public function value( string $key ): string|int|float|bool|null {
		return $this->entitlements[ $key ] ?? null;
	}
}
