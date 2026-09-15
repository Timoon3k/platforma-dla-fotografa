<?php
declare( strict_types=1 );

namespace Kadr\Domain\Printing;

/**
 * Format odbitki — wymiary w milimetrach.
 *
 * FORMATY SĄ DANYMI, NIE KODEM (skill photography-workflow §5). Nie ma tu
 * enuma z listą 10×15, 13×18, 15×21. Fotograf pracuje z laboratorium, które
 * ma własną ofertę: jeden robi 60×90, drugi kwadraty 30×30, trzeci odbitki
 * w calach. Lista zakodowana na sztywno oznaczałaby, że każdy z nich musi
 * poczekać na nową wersję wtyczki.
 *
 * Klasa jest więc wyłącznie arytmetyką nad parą liczb — reszta leży w bazie.
 */
final readonly class PrintFormat {

	private function __construct(
		public int $widthMm,
		public int $heightMm,
	) {}

	public static function ofMillimetres( int $widthMm, int $heightMm ): self {
		if ( $widthMm <= 0 || $heightMm <= 0 ) {
			throw new \InvalidArgumentException( 'Format musi mieć dodatnie wymiary.' );
		}

		return new self( $widthMm, $heightMm );
	}

	/**
	 * Proporcja formatu USTAWIONEGO POD ZDJĘCIE.
	 *
	 * Laboratorium nie drukuje portretu na leżąco. Format 10×15 dla zdjęcia
	 * pionowego znaczy 10 w poziomie i 15 w pionie — to ten sam format
	 * obrócony, nie inny produkt.
	 *
	 * Bez tego obrócenia każde zdjęcie pionowe wyglądałoby na przycięte
	 * w połowie, a klientka zrezygnowałaby z zamówienia.
	 */
	public function ratioFor( int $photoWidth, int $photoHeight ): float {
		$long  = max( $this->widthMm, $this->heightMm );
		$short = min( $this->widthMm, $this->heightMm );

		// Kwadrat zdjęcia traktujemy jak poziom — i tak obie orientacje
		// formatu dają wtedy to samo przycięcie.
		return $photoWidth >= $photoHeight ? $long / $short : $short / $long;
	}

	/**
	 * Nazwa handlowa, jaką klientka zna ze sklepu: „10×15".
	 *
	 * Zawsze krótszy bok pierwszy, niezależnie od tego, jak fotograf wpisał
	 * wymiary — inaczej ta sama odbitka figurowałaby raz jako 10×15,
	 * raz jako 15×10.
	 */
	public function label(): string {
		$long  = max( $this->widthMm, $this->heightMm );
		$short = min( $this->widthMm, $this->heightMm );

		return sprintf( '%s×%s', $this->centimetres( $short ), $this->centimetres( $long ) );
	}

	public function longestSideMm(): int {
		return max( $this->widthMm, $this->heightMm );
	}

	public function shortestSideMm(): int {
		return min( $this->widthMm, $this->heightMm );
	}

	/**
	 * Milimetry na centymetry, bez zera po przecinku tam, gdzie zbędne:
	 * 150 mm → „15", 105 mm → „10,5".
	 */
	private function centimetres( int $mm ): string {
		if ( 0 === $mm % 10 ) {
			return (string) intdiv( $mm, 10 );
		}

		return rtrim( rtrim( number_format( $mm / 10, 1, ',', '' ), '0' ), ',' );
	}
}
