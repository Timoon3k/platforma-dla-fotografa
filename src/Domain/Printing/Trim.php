<?php
declare( strict_types=1 );

namespace Kadr\Domain\Printing;

/**
 * Z której strony zniknie kadr.
 */
enum Trim: string {

	case None         = 'none';
	case Sides        = 'sides';
	case TopAndBottom = 'top-bottom';

	/**
	 * Zdanie dla klientki — konkretne, nie „zdjęcie zostanie dopasowane".
	 */
	public function describe(): string {
		return match ( $this ) {
			self::None         => 'Całe zdjęcie zmieści się w tym formacie.',
			self::Sides        => 'W tym formacie zniknie lewa i prawa krawędź zdjęcia.',
			self::TopAndBottom => 'W tym formacie zniknie góra i dół zdjęcia.',
		};
	}
}
