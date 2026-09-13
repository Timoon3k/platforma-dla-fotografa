<?php
declare( strict_types=1 );

namespace Kadr\Domain\Delivery;

/**
 * Co trafia do paczki.
 *
 * Dwa zakresy, bo fotograf oddaje pliki w dwóch różnych momentach:
 * po wyborze — te kadry, które klientka wskazała do obróbki; na koniec
 * współpracy albo dla siebie — całą sesję.
 */
enum ArchiveScope: string {

	case Selected = 'selected';
	case Everything = 'everything';

	public function label(): string {
		return match ( $this ) {
			self::Selected   => 'Wybrane zdjęcia',
			self::Everything => 'Cała galeria',
		};
	}
}
