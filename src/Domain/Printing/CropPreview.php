<?php
declare( strict_types=1 );

namespace Kadr\Domain\Printing;

/**
 * Co zostanie z kadru po wydrukowaniu w danym formacie.
 *
 * TO JEST SZCZEGÓŁ, KTÓRY DECYDUJE O ZWROTACH (skill photography-workflow §5).
 *
 * Zdjęcie 3:2 wydrukowane w 10×15 (też 3:2) jest pełne. To samo zdjęcie
 * w 13×18 (≈1,38:1) zostanie przycięte — i klientka, która tego nie
 * zobaczyła, dostanie odbitkę z obciętą głową. Reklamację złoży u fotografa,
 * nie u nas, a fotograf zapamięta, przez jaką platformę to przeszło.
 *
 * Klasa liczy UŁAMKI, nie piksele: podgląd w przeglądarce ma inny rozmiar
 * niż plik, a laboratorium jeszcze inny. Ułamek przenosi się na każdy z nich.
 */
final readonly class CropPreview {

	private function __construct(
		/** Ułamek szerokości zdjęcia, który zostaje (0–1). */
		public float $keptWidth,
		/** Ułamek wysokości zdjęcia, który zostaje (0–1). */
		public float $keptHeight,
		/** Ułamek powierzchni, który zostanie obcięty (0–1). */
		public float $lost,
		public Trim $trim,
	) {}

	public static function of( int $photoWidth, int $photoHeight, PrintFormat $format ): self {
		if ( $photoWidth <= 0 || $photoHeight <= 0 ) {
			throw new \InvalidArgumentException( 'Zdjęcie musi mieć dodatnie wymiary.' );
		}

		$photoRatio  = $photoWidth / $photoHeight;
		$formatRatio = $format->ratioFor( $photoWidth, $photoHeight );

		// Porównanie liczb zmiennoprzecinkowych przez tolerancję, nie przez
		// `===`: 3/2 i 150/100 to ta sama proporcja, ale nie ten sam double.
		// Bez tolerancji zdjęcie 3:2 w formacie 10×15 pokazywałoby stratę
		// rzędu 0,00000001% — czyli komunikat „zostanie przycięte" tam,
		// gdzie nic się nie dzieje.
		if ( abs( $photoRatio - $formatRatio ) < 0.001 ) {
			return new self( 1.0, 1.0, 0.0, Trim::None );
		}

		if ( $photoRatio > $formatRatio ) {
			// Zdjęcie jest szersze niż format — obcinamy boki.
			$kept = $formatRatio / $photoRatio;

			return new self( $kept, 1.0, 1.0 - $kept, Trim::Sides );
		}

		// Zdjęcie jest wyższe niż format — obcinamy górę i dół.
		$kept = $photoRatio / $formatRatio;

		return new self( 1.0, $kept, 1.0 - $kept, Trim::TopAndBottom );
	}

	public function isFullFrame(): bool {
		return Trim::None === $this->trim;
	}

	/**
	 * Czy strata jest na tyle duża, żeby klientkę o niej UPRZEDZIĆ.
	 *
	 * Dziesięć procent powierzchni to mniej więcej moment, w którym z kadru
	 * znika czyjeś ramię albo stopy. Poniżej tego progu obcięcie jest
	 * widoczne na podglądzie, ale nie wymaga ostrzeżenia — inaczej
	 * ostrzeżenie pojawiałoby się prawie zawsze i przestałoby cokolwiek
	 * znaczyć.
	 */
	public function needsWarning(): bool {
		return $this->lost >= 0.10;
	}

	/**
	 * Strata w procentach, zaokrąglona do liczby całkowitej.
	 */
	public function lostPercent(): int {
		return (int) round( $this->lost * 100 );
	}

	/**
	 * Wymiary widocznego wycinka w pikselach oryginału — do wygenerowania
	 * miniatury podglądu po stronie serwera.
	 *
	 * @return array{width: int, height: int, left: int, top: int}
	 */
	public function pixelsIn( int $photoWidth, int $photoHeight ): array {
		$width  = (int) round( $photoWidth * $this->keptWidth );
		$height = (int) round( $photoHeight * $this->keptHeight );

		return array(
			'width'  => max( 1, $width ),
			'height' => max( 1, $height ),
			// Kadrujemy ZE ŚRODKA. Fotograf komponuje kadr centralnie
			// częściej niż jakkolwiek inaczej, a klientka i tak zobaczy
			// wynik przed zamówieniem.
			'left'   => max( 0, (int) round( ( $photoWidth - $width ) / 2 ) ),
			'top'    => max( 0, (int) round( ( $photoHeight - $height ) / 2 ) ),
		);
	}
}
