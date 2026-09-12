<?php
declare( strict_types=1 );

namespace Kadr\Domain\Security;

/**
 * Licznik prób z blokadą.
 *
 * Kontrakt jest wąski celowo: `check()` przed operacją, `record()` po nieudanej,
 * `clear()` po udanej. Trzy metody, których nie da się pomylić.
 */
interface Throttle {

	/**
	 * Czy operacja jest jeszcze dozwolona.
	 */
	public function isAllowed( RateLimit $limit ): bool;

	/**
	 * Ile sekund do odblokowania. Zero, gdy nie ma blokady.
	 */
	public function retryAfter( RateLimit $limit ): int;

	/**
	 * Odnotowanie nieudanej próby.
	 *
	 * @return int Liczba prób w bieżącym oknie po tym zapisie.
	 */
	public function record( RateLimit $limit ): int;

	/**
	 * Wyzerowanie po udanej operacji — żeby jedna pomyłka w haśle
	 * nie zbliżała użytkownika do blokady przez kolejne piętnaście minut.
	 */
	public function clear( RateLimit $limit ): void;
}
