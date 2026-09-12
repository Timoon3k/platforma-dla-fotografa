<?php
declare( strict_types=1 );

namespace Kadr\Domain\Billing;

/**
 * Uprawnienia obowiązujące konkretnego tenanta: plan + odstępstwa.
 *
 * JEDYNY dopuszczalny sposób sprawdzania, co wolno użytkownikowi.
 * Konstrukcja `if ( $plan === 'pro' )` jest w tym projekcie zakazana
 * (CLAUDE.md §9, docs/BILLING.md §1) — nazwa planu nie jest regułą biznesową.
 */
final readonly class Entitlements {

	/**
	 * @param array<string, scalar|null> $overrides Odstępstwa z `entitlement_overrides`.
	 */
	public function __construct(
		private Plan $plan,
		private array $overrides = array(),
	) {}

	public function plan(): Plan {
		return $this->plan;
	}

	public function allows( string $key ): bool {
		return true === $this->resolve( $key );
	}

	/**
	 * `null` oznacza brak limitu. Nieznany klucz oznacza limit 0 —
	 * bezpieczna odmowa zamiast przypadkowej nieskończoności.
	 */
	public function limit( string $key ): Limit {
		if ( ! $this->has( $key ) ) {
			return Limit::of( 0 );
		}

		$value = $this->resolve( $key );

		return null === $value ? Limit::unlimited() : Limit::of( (int) $value );
	}

	public function has( string $key ): bool {
		return array_key_exists( $key, $this->overrides ) || $this->plan->has( $key );
	}

	public function value( string $key ): string|int|float|bool|null {
		return $this->resolve( $key );
	}

	/**
	 * Czy operacja jest dopuszczalna przy aktualnym zużyciu.
	 */
	public function hasRoomFor( string $limitKey, int $current, int $additional = 1 ): bool {
		return ! $this->limit( $limitKey )->isReachedBy( $current + $additional - 1 );
	}

	private function resolve( string $key ): string|int|float|bool|null {
		return array_key_exists( $key, $this->overrides )
			? $this->overrides[ $key ]
			: $this->plan->value( $key );
	}
}
