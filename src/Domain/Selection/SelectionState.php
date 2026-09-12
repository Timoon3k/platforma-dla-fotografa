<?php
declare( strict_types=1 );

namespace Kadr\Domain\Selection;

/**
 * Stan zdjęcia w wyborze klienta.
 *
 * `Favorite` i `Selected` to dwie różne rzeczy: klientka najpierw przechodzi
 * galerię i serduszkuje kadry, które jej się podobają, a dopiero potem zawęża
 * je do finalnego wyboru. Sklejenie tych stanów w jeden zmusiłoby ją do
 * podejmowania decyzji zakupowej przy pierwszym przejrzeniu — a to jest
 * dokładnie ten moment tarcia, który produkt ma usuwać.
 */
enum SelectionState: string {

	case Favorite = 'favorite';
	case Selected = 'selected';
	case Rejected = 'rejected';

	/**
	 * Czy ten stan liczy się do pakietu i do dopłaty.
	 */
	public function countsTowardsPackage(): bool {
		return self::Selected === $this;
	}

	public function label(): string {
		return match ( $this ) {
			self::Favorite => 'Ulubione',
			self::Selected => 'Wybrane',
			self::Rejected => 'Odrzucone',
		};
	}
}
