<?php
declare( strict_types=1 );

namespace Kadr\Domain\Queue;

/**
 * Kontrakt kolejki zadań.
 *
 * Kod aplikacyjny NIGDY nie woła implementacji bezpośrednio. Dzięki temu
 * przejście na zewnętrznego workera (Redis, supervisor) dla dużych instalacji
 * nie dotyka ani jednego use case'a.
 */
interface Queue {

	/**
	 * Zakolejkowanie do natychmiastowego wykonania.
	 *
	 * @return int Identyfikator zadania.
	 */
	public function dispatch( Job $job ): int;

	/**
	 * Zakolejkowanie z opóźnieniem.
	 */
	public function dispatchIn( Job $job, int $delaySeconds ): int;

	/**
	 * Pobranie porcji zadań do wykonania wraz z założeniem blokady.
	 *
	 * @return list<ClaimedJob>
	 */
	public function claim( int $limit, string $worker ): array;

	public function complete( int $jobId ): void;

	/**
	 * Oznaczenie niepowodzenia. Zadanie wraca do kolejki z opóźnieniem,
	 * dopóki nie wyczerpie limitu prób.
	 */
	public function fail( int $jobId, string $error ): void;

	public function cancel( int $jobId ): bool;

	/**
	 * Zwolnienie zadań, których blokada wygasła — po awarii procesu roboczego.
	 *
	 * @return int Liczba zwolnionych zadań.
	 */
	public function releaseExpired( int $leaseSeconds ): int;

	/**
	 * @return array{pending: int, running: int, failed: int}
	 */
	public function stats(): array;
}
