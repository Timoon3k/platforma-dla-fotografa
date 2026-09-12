<?php
declare( strict_types=1 );

namespace Kadr\Domain\Shared;

/**
 * Źródło czasu jako zależność, nie jako wywołanie time().
 *
 * Dzięki temu wygasanie galerii, tokenów i sesji da się przetestować
 * bez czekania i bez podmieniania zegara systemowego.
 */
interface Clock {

	public function now(): \DateTimeImmutable;

	public function nowMilliseconds(): int;
}
