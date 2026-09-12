<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\WordPress;

use Kadr\Application\Gallery\ProcessAsset;
use Kadr\Domain\Billing\Entitlements;
use Kadr\Domain\Billing\PlanRegistry;
use Kadr\Domain\Storage\ImageProcessor;
use Kadr\Domain\Storage\StorageProvider;
use Kadr\Domain\Tenancy\Role;
use Kadr\Domain\Tenancy\TenantContext;
use Kadr\Domain\Tenancy\TenantId;
use Kadr\Infrastructure\Database\Connection;
use Kadr\Infrastructure\Database\Repositories\AssetRepository;
use Kadr\Infrastructure\Database\Repositories\AssetVariantRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Infrastructure\Image\ProcessorFactory;
use Kadr\Infrastructure\Queue\DatabaseQueue;
use Kadr\Infrastructure\Queue\JobRunner;
use Kadr\Infrastructure\Storage\LocalStorage;

defined( 'ABSPATH' ) || exit;

/**
 * Miejsce składania zależności w runtimie WordPressa.
 *
 * Świadomie nie jest to kontener DI z autowiringiem. Przy tej liczbie usług
 * ręczne składanie jest czytelniejsze i nie wymaga zależności — a gdy lista
 * urośnie, zamiana na prawdziwy kontener dotyka wyłącznie tego pliku.
 */
final class Container {

	private static ?self $instance = null;

	private ?StorageProvider $storage = null;
	private ?ImageProcessor $processor = null;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Katalog magazynu plików.
	 *
	 * Leży POZA `wp-content/uploads`, bo tamten katalog jest serwowany
	 * bezpośrednio przez serwer WWW (docs/SECURITY.md §3). Ścieżkę można
	 * nadpisać stałą w `wp-config.php` — np. żeby wskazać wolumen danych.
	 */
	public function storagePath(): string {
		if ( defined( 'KADR_STORAGE_PATH' ) ) {
			return (string) constant( 'KADR_STORAGE_PATH' );
		}

		$uploads = wp_get_upload_dir();

		return dirname( (string) $uploads['basedir'] ) . '/kadr-storage';
	}

	public function storage(): StorageProvider {
		return $this->storage ??= new LocalStorage(
			$this->storagePath(),
			home_url( '/d' )
		);
	}

	public function imageProcessor(): ImageProcessor {
		return $this->processor ??= ( new ProcessorFactory() )->create();
	}

	public function queueFor( int $tenantId ): DatabaseQueue {
		return new DatabaseQueue( Connection::get(), $tenantId );
	}

	public function jobRunner(): JobRunner {
		return new JobRunner(
			$this->queueFor( 0 ),
			sprintf( 'wp-%s-%d', gethostname() ?: 'host', getmypid() ?: 0 )
		);
	}

	/**
	 * Kontekst tenanta dla zalogowanego fotografa.
	 *
	 * Zwraca `null`, gdy użytkownik nie należy do żadnego tenanta — wtedy
	 * nie wolno wykonać żadnej operacji na danych.
	 */
	public function tenantForCurrentUser(): ?TenantContext {
		$userId = get_current_user_id();

		if ( $userId <= 0 ) {
			return null;
		}

		$row = Connection::get()->selectOne(
			sprintf(
				'SELECT tenant_id, role FROM `%s` WHERE wp_user_id = ? AND deleted_at IS NULL LIMIT 1',
				Connection::get()->table( \Kadr\Infrastructure\Database\Schema\Tables::TENANT_USERS )
			),
			array( $userId )
		);

		if ( null === $row ) {
			return null;
		}

		return TenantContext::for(
			TenantId::fromInt( (int) $row['tenant_id'] ),
			$userId,
			Role::tryFrom( (string) $row['role'] ) ?? Role::Member
		);
	}

	public function entitlementsFor( int $tenantId ): Entitlements {
		$row = Connection::get()->selectOne(
			sprintf(
				'SELECT plan FROM `%s` WHERE id = ? LIMIT 1',
				Connection::get()->table( \Kadr\Infrastructure\Database\Schema\Tables::TENANTS )
			),
			array( $tenantId )
		);

		$plan = PlanRegistry::get( (string) ( $row['plan'] ?? 'free' ) ) ?? PlanRegistry::get( 'free' );

		return new Entitlements( $plan );
	}

	/**
	 * Zadanie przetwarzania zdjęcia dla konkretnego tenanta.
	 *
	 * Worker działa poza kontekstem zalogowanego użytkownika, więc kontekst
	 * tenanta odtwarzamy z zadania — a repozytoria i tak same filtrują
	 * po tenancie.
	 */
	public function processAssetFor( int $tenantId ): ProcessAsset {
		$db     = Connection::get();
		$tenant = TenantContext::for( TenantId::fromInt( $tenantId ), 0, Role::Owner );

		return new ProcessAsset(
			new GalleryRepository( $db, $tenant ),
			new AssetRepository( $db, $tenant ),
			new AssetVariantRepository( $db, $tenant ),
			$this->storage(),
			$this->imageProcessor(),
			$tenantId
		);
	}
}
