<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Security;

use Kadr\Domain\Security\RateLimit;
use Kadr\Domain\Security\Throttle;
use Kadr\Domain\Shared\Clock;
use Kadr\Domain\Shared\SystemClock;

/**
 * Throttle trzymany w pamięci procesu.
 *
 * Używany w testach oraz jako implementacja bazowa dla wariantu opartego
 * o obiektowy cache WordPressa — logika okna i blokady jest ta sama,
 * różni się wyłącznie miejsce przechowywania.
 */
class InMemoryThrottle implements Throttle {

	/** @var array<string, array{count: int, window_start: int, locked_until: int}> */
	protected array $buckets = array();

	public function __construct( protected readonly Clock $clock = new SystemClock() ) {}

	public function isAllowed( RateLimit $limit ): bool {
		$bucket = $this->bucket( $limit );
		$now    = $this->clock->now()->getTimestamp();

		if ( $bucket['locked_until'] > $now ) {
			return false;
		}

		return $bucket['count'] < $limit->maxAttempts;
	}

	public function retryAfter( RateLimit $limit ): int {
		$bucket = $this->bucket( $limit );
		$now    = $this->clock->now()->getTimestamp();

		return max( 0, $bucket['locked_until'] - $now );
	}

	public function record( RateLimit $limit ): int {
		$bucket = $this->bucket( $limit );
		$now    = $this->clock->now()->getTimestamp();

		++$bucket['count'];

		// Przekroczenie limitu zamyka bramkę na czas blokady, a nie tylko
		// do końca okna — inaczej atakujący czekałby sekundę i próbował dalej.
		if ( $bucket['count'] >= $limit->maxAttempts ) {
			$bucket['locked_until'] = $now + $limit->lockoutSeconds;
		}

		$this->store( $limit, $bucket );

		return $bucket['count'];
	}

	public function clear( RateLimit $limit ): void {
		unset( $this->buckets[ $limit->key ] );
	}

	/**
	 * @return array{count: int, window_start: int, locked_until: int}
	 */
	protected function bucket( RateLimit $limit ): array {
		$now    = $this->clock->now()->getTimestamp();
		$bucket = $this->buckets[ $limit->key ] ?? null;

		if ( null === $bucket ) {
			return array(
				'count'        => 0,
				'window_start' => $now,
				'locked_until' => 0,
			);
		}

		// Okno wygasło i nie ma aktywnej blokady — licznik startuje od nowa.
		if ( $now - $bucket['window_start'] >= $limit->windowSeconds && $bucket['locked_until'] <= $now ) {
			return array(
				'count'        => 0,
				'window_start' => $now,
				'locked_until' => 0,
			);
		}

		return $bucket;
	}

	/**
	 * @param array{count: int, window_start: int, locked_until: int} $bucket
	 */
	protected function store( RateLimit $limit, array $bucket ): void {
		$this->buckets[ $limit->key ] = $bucket;
	}
}
