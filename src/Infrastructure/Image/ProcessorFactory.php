<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Image;

use Kadr\Domain\Storage\ImageProcessor;

/**
 * Wybór implementacji przetwarzania obrazów.
 *
 * Imagick jest preferowany. GD jest fallbackiem świadomie degradowanym —
 * działa, ale gorzej skaluje i zużywa więcej pamięci, co dokumentujemy
 * w wymaganiach systemowych, zamiast udawać, że różnicy nie ma.
 */
final class ProcessorFactory {

	/**
	 * @param list<ImageProcessor>|null $candidates
	 */
	public function __construct( private readonly ?array $candidates = null ) {}

	public function create(): ImageProcessor {
		foreach ( $this->candidates ?? array( new ImagickProcessor(), new GdProcessor() ) as $processor ) {
			if ( $processor->isAvailable() ) {
				return $processor;
			}
		}

		throw new \RuntimeException(
			'Brak biblioteki przetwarzania obrazów. Kadr wymaga rozszerzenia Imagick albo GD.'
		);
	}

	/**
	 * Czy dostępna implementacja jest tą preferowaną.
	 *
	 * Używane przez ekran stanu systemu, żeby administrator wiedział,
	 * że działa na wolniejszej ścieżce.
	 */
	public function isDegraded(): bool {
		return ! ( new ImagickProcessor() )->isAvailable();
	}
}
