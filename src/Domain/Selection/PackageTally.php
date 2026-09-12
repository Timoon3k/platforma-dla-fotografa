<?php
declare( strict_types=1 );

namespace Kadr\Domain\Selection;

use Kadr\Domain\Shared\Money;

/**
 * Rozliczenie wyboru względem pakietu.
 *
 * To jest arytmetyka, na której stoi cała wartość ekonomiczna produktu:
 * klientka wybiera więcej zdjęć, niż obejmuje pakiet, a system pokazuje
 * kwotę dopłaty od razu, zamiast zostawiać fotografa z niezręczną rozmową.
 *
 * Klasa należy do warstwy Domain — nie zna WordPressa, bazy ani HTTP,
 * więc da się ją przetestować w całości.
 */
final readonly class PackageTally {

	private function __construct(
		public int $selected,
		public ?int $packageLimit,
		public int $included,
		public int $extra,
		public Money $extraUnitPrice,
		public Money $total,
	) {}

	/**
	 * @param int|null $packageLimit `null` oznacza pakiet bez limitu —
	 *                               wtedy dopłata nie powstaje nigdy.
	 */
	public static function calculate( int $selected, ?int $packageLimit, Money $extraUnitPrice ): self {
		$selected = max( 0, $selected );

		if ( null === $packageLimit ) {
			return new self(
				selected:       $selected,
				packageLimit:   null,
				included:       $selected,
				extra:          0,
				extraUnitPrice: $extraUnitPrice,
				total:          Money::zero( $extraUnitPrice->currency ),
			);
		}

		$packageLimit = max( 0, $packageLimit );
		$included     = min( $selected, $packageLimit );
		$extra        = max( 0, $selected - $packageLimit );

		return new self(
			selected:       $selected,
			packageLimit:   $packageLimit,
			included:       $included,
			extra:          $extra,
			extraUnitPrice: $extraUnitPrice,
			total:          $extraUnitPrice->multiply( $extra ),
		);
	}

	public function requiresPayment(): bool {
		return $this->extra > 0 && ! $this->total->isZero();
	}

	/**
	 * Ile zdjęć zostało jeszcze w pakiecie. `null` przy pakiecie bez limitu.
	 */
	public function remainingInPackage(): ?int {
		if ( null === $this->packageLimit ) {
			return null;
		}

		return max( 0, $this->packageLimit - $this->selected );
	}

	/**
	 * Czy klient jest dokładnie na granicy pakietu — moment, w którym warto
	 * go uprzedzić, zanim kliknie kolejne zdjęcie.
	 */
	public function isAtPackageLimit(): bool {
		return null !== $this->packageLimit && $this->selected === $this->packageLimit;
	}
}
