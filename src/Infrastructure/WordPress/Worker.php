<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\WordPress;

use Kadr\Domain\Queue\ClaimedJob;
use Kadr\Domain\Shared\Ulid;

defined( 'ABSPATH' ) || exit;

/**
 * Proces roboczy kolejki, wyzwalany cronem WordPressa.
 *
 * `wp_cron` odpala się przy ruchu na stronie, więc instalacja bez odwiedzin
 * nie przetworzy zadań. Dla realnych wdrożeń dokumentacja opisuje przejście
 * na systemowy `cron` — to zmiana konfiguracji, nie kodu.
 */
final class Worker {

	public const HOOK     = 'kadr_process_queue';
	public const INTERVAL = 'kadr_minute';

	public function register_hooks(): void {
		add_filter( 'cron_schedules', array( $this, 'add_interval' ) );
		add_action( 'init', array( $this, 'schedule' ) );
		add_action( self::HOOK, array( $this, 'run' ) );
	}

	/**
	 * @param array<string, array{interval: int, display: string}> $schedules
	 * @return array<string, array{interval: int, display: string}>
	 */
	public function add_interval( array $schedules ): array {
		$schedules[ self::INTERVAL ] = array(
			'interval' => 60,
			'display'  => __( 'Co minutę (kolejka Kadr)', 'kadr' ),
		);

		return $schedules;
	}

	public function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 60, self::INTERVAL, self::HOOK );
		}
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Jedno przejście workera.
	 *
	 * Porcja jest niewielka celowo: zadanie uruchamiane w cyklu żądania
	 * HTTP musi zdążyć przed limitem czasu wykonania PHP.
	 */
	public function run(): void {
		$container = Container::instance();
		$runner    = $container->jobRunner();

		$runner->register(
			'ProcessAsset',
			static function ( ClaimedJob $job ) use ( $container ): void {
				$process = $container->processAssetFor( $job->tenantId );

				$result = $process(
					Ulid::fromString( (string) $job->get( 'asset_id' ) ),
					Ulid::fromString( (string) $job->get( 'gallery_id' ) )
				);

				if ( $result->isFailure() ) {
					// Wyjątek zamiast cichego sukcesu — kolejka ma ponowić.
					throw new \RuntimeException( $result->message );
				}
			}
		);

		$runner->register(
			'PackArchive',
			static function ( ClaimedJob $job ) use ( $container ): void {
				$pack   = $container->packArchiveFor( $job->tenantId );
				$id     = Ulid::fromString( (string) $job->get( 'archive_id' ) );
				$result = $pack->pack( $id );

				if ( $result->isFailure() ) {
					throw new \RuntimeException( $result->message );
				}

				if ( true === ( $result->value['done'] ?? false ) ) {
					return;
				}

				/*
				 * Paczka nie zmieściła się w jednym przebiegu — wraca do kolejki.
				 * Zapętlanie jest TUTAJ, a nie w warstwie aplikacji: decyzja
				 * „kiedy dalej" należy do kolejki, a nie do reguł produktu.
				 *
				 * Zadanie dokłada się jako NOWE, więc licznik prób dotyczy
				 * jednej porcji. Inaczej wesele na tysiąc zdjęć wyczerpałoby
				 * limit ponowień po dwudziestu porcjach i umarło w połowie.
				 */
				$container->queueFor( $job->tenantId )->dispatch(
					new \Kadr\Domain\Queue\Job( 'PackArchive', array( 'archive_id' => (string) $id ) )
				);
			}
		);

		$runner->run( 5 );
	}
}
