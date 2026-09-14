<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\WordPress;

use Kadr\Application\Delivery\SweepExpiredArchives;
use Kadr\Application\Selection\NotifySelectionSubmitted;
use Kadr\Domain\Queue\ClaimedJob;
use Kadr\Domain\Shared\Ulid;
use Kadr\Domain\Tenancy\Role;
use Kadr\Domain\Tenancy\TenantContext;
use Kadr\Domain\Tenancy\TenantId;
use Kadr\Infrastructure\Database\Connection;
use Kadr\Infrastructure\Database\Platform\TenantStore;
use Kadr\Infrastructure\Database\Repositories\ArchiveRepository;
use Kadr\Infrastructure\Database\Repositories\DownloadTokenRepository;

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

	/** Kiedy ostatnio sprzątaliśmy wygasłe paczki. */
	private const SWEEP_OPTION = 'kadr_last_sweep';
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

		$runner->register(
			'NotifySelection',
			static function ( ClaimedJob $job ) use ( $container ): void {
				$db      = Connection::get();
				$tenant  = TenantContext::for( TenantId::fromInt( $job->tenantId ), 0, Role::Owner );
				$gallery = ( new \Kadr\Infrastructure\Database\Repositories\GalleryRepository( $db, $tenant ) )
					->findByPublicId( Ulid::fromString( (string) $job->get( 'gallery_id' ) ) );

				if ( null === $gallery ) {
					return;
				}

				$client = null;

				if ( null !== $gallery['client_id'] ) {
					$clients = new \Kadr\Infrastructure\Database\Repositories\ClientRepository( $db, $tenant );
					$row     = $clients->byIds( array( (int) $gallery['client_id'] ) )[ (int) $gallery['client_id'] ] ?? null;
					$client  = null === $row ? null : trim( (string) $row['first_name'] );
				}

				$result = ( new NotifySelectionSubmitted( $container->mailer() ) )->send(
					$container->ownerEmail( $job->tenantId ),
					(string) $gallery['title'],
					$client,
					array(
						'selected' => (int) $job->get( 'selected' ),
						'extra'    => (int) $job->get( 'extra' ),
						'total'    => (int) $job->get( 'total' ),
					),
					home_url( '/app/galerie/' . $gallery['public_id'] )
				);

				// Brak adresu fotografa nie jest awarią wartą ponawiania —
				// studio bez adresu kontaktowego to stan, nie błąd.
				if ( $result->isFailure() && 'kadr_mail_failed' === $result->code ) {
					throw new \RuntimeException( $result->message );
				}
			}
		);

		$runner->run( 5 );

		$this->sweep();
	}

	/**
	 * Sprzątanie wygasłych paczek — raz na dobę.
	 *
	 * To jest pozycja na rachunku, nie higiena: paczka wesela waży 30–80 GB,
	 * a fotograf ślubny robi trzydzieści wesel w sezonie. Bez tego dwa
	 * terabajty za pliki, których nikt już nie pobierze, płaci właściciel
	 * platformy.
	 *
	 * Doba, nie co przebieg workera: przejście po wszystkich studiach jest
	 * tanie, ale niepotrzebne co pięć minut, a tokeny i tak żyją 24 godziny.
	 */
	private function sweep(): void {
		$last = (int) get_option( self::SWEEP_OPTION, 0 );

		if ( $last > time() - DAY_IN_SECONDS ) {
			return;
		}

		// Znacznik zapisujemy PRZED sprzątaniem: gdyby przerwało je
		// przekroczenie czasu, kolejny przebieg nie zacznie od nowa
		// w nieskończonej pętli. Zaległości dobierze następna doba.
		update_option( self::SWEEP_OPTION, time(), false );

		$container = Container::instance();
		$db        = Connection::get();

		foreach ( ( new TenantStore( $db ) )->activeIds() as $tenantId ) {
			$tenant = TenantContext::for( TenantId::fromInt( $tenantId ), 0, Role::Owner );

			( new SweepExpiredArchives(
				new ArchiveRepository( $db, $tenant ),
				new DownloadTokenRepository( $db, $tenant ),
				$container->storage()
			) )->run( 20 );
		}
	}
}
