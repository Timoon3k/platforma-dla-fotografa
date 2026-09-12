<?php
declare( strict_types=1 );

namespace Kadr\Domain\Shared;

/**
 * Zegar zatrzymany — wyłącznie do testów.
 */
final class FrozenClock implements Clock {

	private \DateTimeImmutable $now;

	public function __construct( string $time = '2026-01-01 12:00:00' ) {
		$this->now = new \DateTimeImmutable( $time, new \DateTimeZone( 'UTC' ) );
	}

	public function now(): \DateTimeImmutable {
		return $this->now;
	}

	public function nowMilliseconds(): int {
		return $this->now->getTimestamp() * 1000;
	}

	public function advance( string $interval ): void {
		$this->now = $this->now->modify( $interval );
	}
}
