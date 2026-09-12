<?php
declare( strict_types=1 );

namespace Kadr\Tests\Queue;

use Kadr\Domain\Queue\Job;
use Kadr\Domain\Shared\FrozenClock;
use Kadr\Infrastructure\Queue\DatabaseQueue;
use Kadr\Tests\Support\TestDatabase;
use Kadr\Tests\TestCase;

/**
 * Kolejka zadań — testy na prawdziwym silniku SQL.
 */
final class DatabaseQueueTest extends TestCase {

	public function testDispatchedJobIsClaimedAndCompleted(): void {
		$db    = TestDatabase::migrated();
		$queue = new DatabaseQueue( $db, 1 );

		$queue->dispatch( new Job( 'GenerateVariants', array( 'asset_id' => 42 ) ) );

		$claimed = $queue->claim( 5, 'worker-1' );

		$this->assertSame( 1, count( $claimed ) );
		$this->assertSame( 'GenerateVariants', $claimed[0]->name );
		$this->assertSame( 42, $claimed[0]->get( 'asset_id' ) );
		$this->assertSame( 1, $claimed[0]->attempts );

		$queue->complete( $claimed[0]->id );

		$this->assertSame( 0, $queue->stats()['pending'] );
		$this->assertSame( 0, $queue->stats()['running'] );
	}

	/**
	 * Dwóch workerów nie może wziąć tego samego zadania.
	 */
	public function testAJobIsNeverClaimedTwice(): void {
		$db    = TestDatabase::migrated();
		$queue = new DatabaseQueue( $db, 1 );

		$queue->dispatch( new Job( 'SendEmail' ) );

		$first  = $queue->claim( 5, 'worker-1' );
		$second = $queue->claim( 5, 'worker-2' );

		$this->assertSame( 1, count( $first ) );
		$this->assertSame( 0, count( $second ) );
	}

	public function testDelayedJobIsNotAvailableYet(): void {
		$clock = new FrozenClock( '2026-03-01 12:00:00' );
		$db    = TestDatabase::migrated();
		$queue = new DatabaseQueue( $db, 1, $clock );

		$queue->dispatchIn( new Job( 'ExpireGallery' ), 3600 );

		$this->assertSame( 0, count( $queue->claim( 5, 'w' ) ) );

		$clock->advance( '+2 hours' );
		$this->assertSame( 1, count( $queue->claim( 5, 'w' ) ) );
	}

	public function testHigherPriorityGoesFirst(): void {
		$db    = TestDatabase::migrated();
		$queue = new DatabaseQueue( $db, 1 );

		$queue->dispatch( new Job( 'Niski', array(), 0 ) );
		$queue->dispatch( new Job( 'Wysoki', array(), 10 ) );

		$claimed = $queue->claim( 1, 'w' );

		$this->assertSame( 'Wysoki', $claimed[0]->name );
	}

	/**
	 * Jeden fotograf wysyłający wesele nie może zagłodzić kolejki pozostałych.
	 */
	public function testOneTenantCannotStarveTheOthers(): void {
		$db = TestDatabase::migrated();

		$busy  = new DatabaseQueue( $db, 1, new FrozenClock(), 2 );
		$other = new DatabaseQueue( $db, 2, new FrozenClock(), 2 );

		for ( $i = 0; $i < 20; $i++ ) {
			$busy->dispatch( new Job( 'GenerateVariants', array( 'n' => $i ) ) );
		}
		$other->dispatch( new Job( 'SendEmail' ) );

		$claimed = $busy->claim( 10, 'worker-1' );

		// Limit dwóch zadań na tenanta: dwa od zajętego plus jedno od drugiego.
		$this->assertSame( 3, count( $claimed ) );

		$tenants = array();
		foreach ( $claimed as $job ) {
			$tenants[ $job->tenantId ] = ( $tenants[ $job->tenantId ] ?? 0 ) + 1;
		}

		$this->assertSame( 2, $tenants[1] );
		$this->assertSame( 1, $tenants[2] );
	}

	public function testFailureSchedulesARetryWithBackoff(): void {
		$clock = new FrozenClock( '2026-03-01 12:00:00' );
		$db    = TestDatabase::migrated();
		$queue = new DatabaseQueue( $db, 1, $clock );

		$queue->dispatch( new Job( 'GenerateVariants' ) );
		$claimed = $queue->claim( 1, 'w' );
		$queue->fail( $claimed[0]->id, 'Imagick padł' );

		// Zadanie wróciło do kolejki, ale jeszcze nie jest dostępne.
		$this->assertSame( 1, $queue->stats()['pending'] );
		$this->assertSame( 0, count( $queue->claim( 5, 'w' ) ) );

		$clock->advance( '+2 minutes' );
		$this->assertSame( 1, count( $queue->claim( 5, 'w' ) ) );
	}

	public function testBackoffGrowsAndIsCapped(): void {
		$this->assertSame( 60, Job::backoffSeconds( 1 ) );
		$this->assertSame( 120, Job::backoffSeconds( 2 ) );
		$this->assertSame( 240, Job::backoffSeconds( 3 ) );
		// Bez górnej granicy dziesiąta próba czekałaby ponad dobę.
		$this->assertSame( 3600, Job::backoffSeconds( 20 ) );
	}

	public function testJobGivesUpAfterMaxAttempts(): void {
		$clock = new FrozenClock( '2026-03-01 12:00:00' );
		$db    = TestDatabase::migrated();
		$queue = new DatabaseQueue( $db, 1, $clock );

		$queue->dispatch( new Job( 'Beznadziejne', array(), 0, 2 ) );

		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			$claimed = $queue->claim( 1, 'w' );
			$this->assertSame( 1, count( $claimed ) );
			$queue->fail( $claimed[0]->id, 'znowu' );
			$clock->advance( '+1 hour' );
		}

		$this->assertSame( 1, $queue->stats()['failed'] );
		$this->assertSame( 0, count( $queue->claim( 5, 'w' ) ) );
	}

	/**
	 * Proces roboczy padł w trakcie — zadanie musi wrócić do kolejki,
	 * a nie zostać zajęte na zawsze.
	 */
	public function testExpiredLeaseReturnsTheJobToTheQueue(): void {
		$clock = new FrozenClock( '2026-03-01 12:00:00' );
		$db    = TestDatabase::migrated();
		$queue = new DatabaseQueue( $db, 1, $clock );

		$queue->dispatch( new Job( 'BuildZip' ) );
		$queue->claim( 1, 'worker-ktory-padl' );

		$this->assertSame( 1, $queue->stats()['running'] );

		$clock->advance( '+20 minutes' );
		$released = $queue->releaseExpired( 600 );

		$this->assertSame( 1, $released );
		$this->assertSame( 1, count( $queue->claim( 5, 'worker-2' ) ) );
	}

	public function testLeaseThatHasNotExpiredIsLeftAlone(): void {
		$clock = new FrozenClock( '2026-03-01 12:00:00' );
		$db    = TestDatabase::migrated();
		$queue = new DatabaseQueue( $db, 1, $clock );

		$queue->dispatch( new Job( 'BuildZip' ) );
		$queue->claim( 1, 'worker-1' );

		$clock->advance( '+2 minutes' );

		$this->assertSame( 0, $queue->releaseExpired( 600 ) );
	}

	public function testPendingJobCanBeCancelled(): void {
		$db    = TestDatabase::migrated();
		$queue = new DatabaseQueue( $db, 1 );

		$id = $queue->dispatch( new Job( 'SendEmail' ) );

		$this->assertTrue( $queue->cancel( $id ) );
		$this->assertSame( 0, $queue->stats()['pending'] );
	}

	/**
	 * Zadania w trakcie wykonania nie odwołujemy — worker i tak je dokończy,
	 * a skasowanie wiersza zostawiłoby sierotę.
	 */
	public function testRunningJobCannotBeCancelled(): void {
		$db    = TestDatabase::migrated();
		$queue = new DatabaseQueue( $db, 1 );

		$id = $queue->dispatch( new Job( 'BuildZip' ) );
		$queue->claim( 1, 'w' );

		$this->assertFalse( $queue->cancel( $id ) );
	}

	public function testPayloadSurvivesRoundTrip(): void {
		$db    = TestDatabase::migrated();
		$queue = new DatabaseQueue( $db, 1 );

		$queue->dispatch(
			new Job(
				'GenerateVariants',
				array(
					'asset_id' => 'ZŁÓŻ ĆMĘ',
					'sizes'    => array( 400, 900, 1800 ),
					'flag'     => true,
					'nothing'  => null,
				)
			)
		);

		$claimed = $queue->claim( 1, 'w' )[0];

		$this->assertSame( 'ZŁÓŻ ĆMĘ', $claimed->get( 'asset_id' ) );
		$this->assertSame( array( 400, 900, 1800 ), $claimed->get( 'sizes' ) );
		$this->assertTrue( $claimed->get( 'flag' ) );
		$this->assertNull( $claimed->get( 'nothing' ) );
		$this->assertSame( 'domyślna', $claimed->get( 'brak', 'domyślna' ) );
	}

	public function testJobRequiresAName(): void {
		$this->assertThrows( \InvalidArgumentException::class, static fn() => new Job( '   ' ) );
	}
}

/**
 * Wykonywanie zadań przez runner.
 */
final class JobRunnerTest extends TestCase {

	public function testRunsHandlerAndCompletesTheJob(): void {
		$db    = TestDatabase::migrated();
		$queue = new DatabaseQueue( $db, 1 );
		$queue->dispatch( new Job( 'ProcessAsset', array( 'asset_id' => 'X' ) ) );

		$seen   = array();
		$runner = new \Kadr\Infrastructure\Queue\JobRunner( $queue, 'worker-1' );
		$runner->register( 'ProcessAsset', static function ( $job ) use ( &$seen ): void {
			$seen[] = $job->get( 'asset_id' );
		} );

		$result = $runner->run();

		$this->assertSame( 1, $result['processed'] );
		$this->assertSame( 0, $result['failed'] );
		$this->assertSame( array( 'X' ), $seen );
		$this->assertSame( 0, $queue->stats()['pending'] );
	}

	/**
	 * Wyjątek w handlerze nie może wywrócić całej porcji.
	 */
	public function testOneFailingJobDoesNotStopTheBatch(): void {
		$db    = TestDatabase::migrated();
		$queue = new DatabaseQueue( $db, 1 );

		$queue->dispatch( new Job( 'Dobre' ) );
		$queue->dispatch( new Job( 'Złe' ) );
		$queue->dispatch( new Job( 'Dobre' ) );

		$runner = new \Kadr\Infrastructure\Queue\JobRunner( $queue, 'w' );
		$runner->register( 'Dobre', static fn() => null );
		$runner->register( 'Złe', static function (): void {
			throw new \RuntimeException( 'Imagick padł' );
		} );

		$result = $runner->run( 10 );

		$this->assertSame( 2, $result['processed'] );
		$this->assertSame( 1, $result['failed'] );
	}

	/**
	 * Zadanie bez handlera to błąd wdrożenia, o którym trzeba się dowiedzieć —
	 * nie wolno go po cichu pominąć.
	 */
	public function testUnknownJobFailsInsteadOfBeingSkipped(): void {
		$db    = TestDatabase::migrated();
		$queue = new DatabaseQueue( $db, 1 );
		$queue->dispatch( new Job( 'NieistniejąceZadanie' ) );

		$runner = new \Kadr\Infrastructure\Queue\JobRunner( $queue, 'w' );
		$result = $runner->run();

		$this->assertSame( 0, $result['processed'] );
		$this->assertSame( 1, $result['failed'] );
	}

	/**
	 * Runner odzyskuje zadania po workerze, który padł.
	 */
	public function testReclaimsJobsFromDeadWorkers(): void {
		$clock = new FrozenClock( '2026-05-01 08:00:00' );
		$db    = TestDatabase::migrated();
		$queue = new DatabaseQueue( $db, 1, $clock );

		$queue->dispatch( new Job( 'BuildZip' ) );
		$queue->claim( 1, 'worker-ktory-padl' );

		$clock->advance( '+30 minutes' );

		$runner = new \Kadr\Infrastructure\Queue\JobRunner( $queue, 'worker-2' );
		$runner->register( 'BuildZip', static fn() => null );

		$this->assertSame( 1, $runner->run()['processed'] );
	}
}
