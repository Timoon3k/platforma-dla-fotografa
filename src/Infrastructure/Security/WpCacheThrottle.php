<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Security;

use Kadr\Domain\Security\RateLimit;

defined( 'ABSPATH' ) || exit;

/**
 * Throttle oparty o obiektowy cache WordPressa.
 *
 * Dziedziczy całą logikę okna i blokady po wariancie pamięciowym — zmienia
 * wyłącznie miejsce przechowywania, dzięki czemu testy tej logiki obejmują
 * także tę klasę.
 *
 * ⚠️ Bez trwałego obiektowego cache (Redis, Memcached) WordPress trzyma cache
 * wyłącznie w obrębie jednego żądania, więc limity NIE DZIAŁAJĄ między
 * żądaniami. Dlatego instalacja bez trwałego cache dostaje ostrzeżenie
 * na ekranie stanu, zamiast cicho udawać ochronę.
 */
final class WpCacheThrottle extends InMemoryThrottle {

	private const GROUP = 'kadr_throttle';

	public static function isPersistent(): bool {
		return (bool) wp_using_ext_object_cache();
	}

	protected function bucket( RateLimit $limit ): array {
		$cached = wp_cache_get( $limit->key, self::GROUP );

		if ( is_array( $cached ) ) {
			$this->buckets[ $limit->key ] = array(
				'count'        => (int) ( $cached['count'] ?? 0 ),
				'window_start' => (int) ( $cached['window_start'] ?? 0 ),
				'locked_until' => (int) ( $cached['locked_until'] ?? 0 ),
			);
		}

		return parent::bucket( $limit );
	}

	protected function store( RateLimit $limit, array $bucket ): void {
		parent::store( $limit, $bucket );

		// Wpis żyje najdłuższy z dwóch czasów, żeby blokada nie zniknęła
		// razem z końcem okna zliczania.
		$ttl = max( $limit->windowSeconds, $limit->lockoutSeconds );

		wp_cache_set( $limit->key, $bucket, self::GROUP, $ttl );
	}

	public function clear( RateLimit $limit ): void {
		parent::clear( $limit );
		wp_cache_delete( $limit->key, self::GROUP );
	}
}
