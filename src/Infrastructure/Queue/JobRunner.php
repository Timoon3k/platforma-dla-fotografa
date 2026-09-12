<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Queue;

use Kadr\Domain\Queue\ClaimedJob;
use Kadr\Domain\Queue\Queue;

/**
 * Wykonuje zadania pobrane z kolejki.
 *
 * Handler zgłasza się nazwą zadania. Nieznana nazwa kończy się porażką
 * zadania, a nie cichym pominięciem — zadanie bez handlera oznacza błąd
 * wdrożenia, o którym trzeba się dowiedzieć.
 */
final class JobRunner {

	/** @var array<string, callable(ClaimedJob): void> */
	private array $handlers = array();

	public function __construct(
		private readonly Queue $queue,
		private readonly string $worker,
	) {}

	/**
	 * @param callable(ClaimedJob): void $handler
	 */
	public function register( string $jobName, callable $handler ): void {
		$this->handlers[ $jobName ] = $handler;
	}

	/**
	 * Przetworzenie porcji zadań.
	 *
	 * @return array{processed: int, failed: int}
	 */
	public function run( int $batchSize = 5 ): array {
		// Najpierw odzyskujemy zadania po workerach, które padły.
		$this->queue->releaseExpired( 600 );

		$processed = 0;
		$failed    = 0;

		foreach ( $this->queue->claim( $batchSize, $this->worker ) as $job ) {
			$handler = $this->handlers[ $job->name ] ?? null;

			if ( null === $handler ) {
				$this->queue->fail( $job->id, sprintf( 'Brak handlera dla zadania "%s".', $job->name ) );
				++$failed;
				continue;
			}

			try {
				$handler( $job );
				$this->queue->complete( $job->id );
				++$processed;
			} catch ( \Throwable $e ) {
				// Pełny komunikat idzie do kolejki, nie do użytkownika.
				$this->queue->fail( $job->id, $e->getMessage() );
				++$failed;
			}
		}

		return array(
			'processed' => $processed,
			'failed'    => $failed,
		);
	}
}
