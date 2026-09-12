<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Queue;

use Kadr\Domain\Persistence\Database;
use Kadr\Domain\Queue\ClaimedJob;
use Kadr\Domain\Queue\Job;
use Kadr\Domain\Queue\Queue;
use Kadr\Domain\Shared\Clock;
use Kadr\Domain\Shared\SystemClock;
use Kadr\Infrastructure\Database\Schema\Tables;

/**
 * Kolejka oparta o tabelę w bazie (ADR-016).
 *
 * Pobieranie zadań jest CELOWO ponadtenantowe — worker obsługuje wszystkich
 * fotografów naraz. Przynależność wiersza do tenanta pozostaje zapisana
 * i służy dwóm rzeczom: limitowi współbieżności oraz temu, żeby zadanie
 * odtworzyło właściwy kontekst przed dotknięciem danych.
 *
 * Blokowanie zadania: najpierw wybieramy kandydatów, potem próbujemy je
 * zająć warunkowym UPDATE-em. Liczba zmienionych wierszy mówi, ile faktycznie
 * przypadło temu workerowi. Wzorzec jest przenośny — `UPDATE ... ORDER BY ...
 * LIMIT` działa w MySQL, ale nie w SQLite, na którym stoją testy.
 *
 * Limit współbieżności per tenant istnieje po to, żeby jeden fotograf
 * wysyłający wesele nie zagłodził kolejki pozostałych.
 */
final class DatabaseQueue implements Queue {

	public function __construct(
		private readonly Database $db,
		private readonly int $tenantId,
		private readonly Clock $clock = new SystemClock(),
		private readonly int $perTenantConcurrency = 4,
	) {}

	public function dispatch( Job $job ): int {
		return $this->insert( $job, 0 );
	}

	public function dispatchIn( Job $job, int $delaySeconds ): int {
		return $this->insert( $job, max( 0, $delaySeconds ) );
	}

	public function claim( int $limit, string $worker ): array {
		$limit = max( 1, min( 50, $limit ) );
		$now   = $this->now();
		$table = $this->table();

		// Kandydaci: gotowe do wykonania, najpierw priorytet, potem kolejność zgłoszenia.
		$candidates = $this->db->selectAll(
			sprintf(
				'SELECT id, tenant_id FROM `%s` WHERE status = ? AND available_at <= ? '
				. 'ORDER BY priority DESC, id ASC LIMIT %d',
				$table,
				$limit * 4
			),
			array( 'pending', $now )
		);

		if ( array() === $candidates ) {
			return array();
		}

		$running = $this->runningPerTenant();
		$claimed = array();

		foreach ( $candidates as $candidate ) {
			if ( count( $claimed ) >= $limit ) {
				break;
			}

			$tenant = (int) $candidate['tenant_id'];

			// Jeden tenant nie może zająć całej przepustowości.
			if ( ( $running[ $tenant ] ?? 0 ) >= $this->perTenantConcurrency ) {
				continue;
			}

			$id = (int) $candidate['id'];

			// Warunek `status = pending` sprawia, że dwóch workerów nie weźmie
			// tego samego zadania — wygrywa ten, którego UPDATE zmieni wiersz.
			$changed = $this->db->execute(
				sprintf(
					'UPDATE `%s` SET status = ?, claimed_at = ?, claimed_by = ?, attempts = attempts + 1 '
					. 'WHERE id = ? AND status = ?',
					$table
				),
				array( 'claimed', $now, $worker, $id, 'pending' )
			);

			if ( 1 !== $changed ) {
				continue;
			}

			$running[ $tenant ] = ( $running[ $tenant ] ?? 0 ) + 1;

			$row = $this->db->selectOne( sprintf( 'SELECT * FROM `%s` WHERE id = ?', $table ), array( $id ) );

			if ( null !== $row ) {
				$claimed[] = ClaimedJob::fromRow( $row );
			}
		}

		return $claimed;
	}

	public function complete( int $jobId ): void {
		$this->db->execute(
			sprintf( 'UPDATE `%s` SET status = ?, completed_at = ?, last_error = ? WHERE id = ?', $this->table() ),
			array( 'done', $this->now(), null, $jobId )
		);
	}

	public function fail( int $jobId, string $error ): void {
		$row = $this->db->selectOne(
			sprintf( 'SELECT attempts, max_attempts FROM `%s` WHERE id = ?', $this->table() ),
			array( $jobId )
		);

		if ( null === $row ) {
			return;
		}

		$attempts = (int) $row['attempts'];
		$max      = (int) $row['max_attempts'];

		// Komunikat błędu przycinamy — log nie jest miejscem na zrzut pamięci.
		$error = mb_substr( $error, 0, 1000 );

		if ( $attempts >= $max ) {
			$this->db->execute(
				sprintf( 'UPDATE `%s` SET status = ?, last_error = ?, completed_at = ? WHERE id = ?', $this->table() ),
				array( 'failed', $error, $this->now(), $jobId )
			);

			return;
		}

		// Ponowienie z rosnącym opóźnieniem.
		$this->db->execute(
			sprintf(
				'UPDATE `%s` SET status = ?, available_at = ?, claimed_at = ?, claimed_by = ?, last_error = ? WHERE id = ?',
				$this->table()
			),
			array(
				'pending',
				$this->at( Job::backoffSeconds( $attempts ) ),
				null,
				null,
				$error,
				$jobId,
			)
		);
	}

	public function cancel( int $jobId ): bool {
		// Zadania będącego w trakcie wykonania nie odwołujemy — proces roboczy
		// i tak go dokończy, a skasowanie wiersza zostawiłoby sierotę.
		$changed = $this->db->execute(
			sprintf( 'DELETE FROM `%s` WHERE id = ? AND status = ?', $this->table() ),
			array( $jobId, 'pending' )
		);

		return $changed > 0;
	}

	public function releaseExpired( int $leaseSeconds ): int {
		$cutoff = $this->at( -abs( $leaseSeconds ) );

		// Proces roboczy mógł paść w trakcie. Zadanie wraca do kolejki,
		// a licznik prób już został podniesiony przy zajęciu — więc zadanie,
		// które regularnie zabija workera, samo wyczerpie limit prób.
		return $this->db->execute(
			sprintf(
				'UPDATE `%s` SET status = ?, claimed_at = ?, claimed_by = ?, available_at = ? '
				. 'WHERE status = ? AND claimed_at IS NOT NULL AND claimed_at < ?',
				$this->table()
			),
			array( 'pending', null, null, $this->now(), 'claimed', $cutoff )
		);
	}

	public function stats(): array {
		$rows = $this->db->selectAll(
			sprintf( 'SELECT status, COUNT(*) AS total FROM `%s` GROUP BY status', $this->table() )
		);

		$counts = array( 'pending' => 0, 'claimed' => 0, 'failed' => 0 );

		foreach ( $rows as $row ) {
			$counts[ (string) $row['status'] ] = (int) $row['total'];
		}

		return array(
			'pending' => $counts['pending'],
			'running' => $counts['claimed'],
			'failed'  => $counts['failed'],
		);
	}

	private function insert( Job $job, int $delaySeconds ): int {
		return $this->db->insert(
			$this->table(),
			array(
				'tenant_id'    => $this->tenantId,
				'job_name'     => $job->name,
				'payload'      => $job->encodedPayload(),
				'status'       => 'pending',
				'priority'     => $job->priority,
				'attempts'     => 0,
				'max_attempts' => $job->maxAttempts,
				'available_at' => $this->at( $delaySeconds ),
				'created_at'   => $this->now(),
				'updated_at'   => $this->now(),
			)
		);
	}

	/**
	 * @return array<int, int>
	 */
	private function runningPerTenant(): array {
		$rows = $this->db->selectAll(
			sprintf( 'SELECT tenant_id, COUNT(*) AS total FROM `%s` WHERE status = ? GROUP BY tenant_id', $this->table() ),
			array( 'claimed' )
		);

		$running = array();

		foreach ( $rows as $row ) {
			$running[ (int) $row['tenant_id'] ] = (int) $row['total'];
		}

		return $running;
	}

	private function table(): string {
		return $this->db->table( Tables::JOBS );
	}

	private function now(): string {
		return $this->clock->now()->format( 'Y-m-d H:i:s' );
	}

	private function at( int $offsetSeconds ): string {
		return $this->clock->now()->modify( sprintf( '%+d seconds', $offsetSeconds ) )->format( 'Y-m-d H:i:s' );
	}
}
