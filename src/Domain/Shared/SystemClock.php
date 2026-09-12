<?php
declare( strict_types=1 );

namespace Kadr\Domain\Shared;

/**
 * Zegar systemowy. Zawsze UTC — strefa czasowa jest sprawą prezentacji,
 * nie przechowywania (docs/DATABASE.md §2).
 */
final class SystemClock implements Clock {

	public function now(): \DateTimeImmutable {
		return new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
	}

	public function nowMilliseconds(): int {
		return (int) round( microtime( true ) * 1000 );
	}
}
