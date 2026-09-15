<?php
declare( strict_types=1 );

namespace Kadr\Domain\Printing;

/**
 * Czy plik wystarczy na ten format.
 *
 * DRUGIE ŹRÓDŁO REKLAMACJI, ZARAZ PO KADROWANIU.
 *
 * Zdjęcie po kadrowaniu ma 1600 px szerokości. W 10×15 wyjdzie znakomicie.
 * W 50×70 wyjdzie rozmyte — i znów reklamację dostanie fotograf, nie my.
 * Różnicy nie widać na ekranie telefonu, więc nikt jej sam nie zauważy;
 * trzeba ją policzyć i powiedzieć wprost.
 *
 * Liczymy DPI po przycięciu, nie z oryginału: kadrowanie zabiera piksele,
 * a to właśnie przy dużych formatach przycięcie bywa największe.
 */
final readonly class PrintQuality {

	/** Poniżej tego progu odbitka jest wyraźnie rozmyta. */
	private const POOR = 150;

	/** Od tego progu wydruk jest bez zarzutu. */
	private const GOOD = 240;

	private function __construct(
		public int $dpi,
		public Grade $grade,
	) {}

	public static function of( int $croppedWidthPx, int $croppedHeightPx, PrintFormat $format ): self {
		// Dłuższy bok zdjęcia trafia na dłuższy bok formatu — format jest
		// ustawiany pod zdjęcie (patrz `PrintFormat::ratioFor`).
		$longestPx = max( $croppedWidthPx, $croppedHeightPx );
		$longestMm = $format->longestSideMm();

		$inches = $longestMm / 25.4;
		$dpi    = $inches > 0 ? (int) floor( $longestPx / $inches ) : 0;

		return new self( $dpi, self::gradeFor( $dpi ) );
	}

	public function isPrintable(): bool {
		return Grade::Poor !== $this->grade;
	}

	/**
	 * Zdanie dla klientki. Bez „DPI" — ta liczba nic jej nie mówi,
	 * a „wyjdzie rozmyta" mówi wszystko (skill photography-workflow §9).
	 */
	public function describe(): string {
		return match ( $this->grade ) {
			Grade::Good       => 'Rozdzielczość w sam raz na ten format.',
			Grade::Acceptable => 'Rozdzielczość wystarczy, ale to największy format, jaki zalecamy dla tego kadru.',
			Grade::Poor       => 'Ten kadr jest za mały na taki format — odbitka wyjdzie rozmyta.',
		};
	}

	private static function gradeFor( int $dpi ): Grade {
		if ( $dpi >= self::GOOD ) {
			return Grade::Good;
		}

		return $dpi >= self::POOR ? Grade::Acceptable : Grade::Poor;
	}
}
