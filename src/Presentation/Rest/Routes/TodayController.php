<?php
declare( strict_types=1 );

namespace Kadr\Presentation\Rest\Routes;

use Kadr\Domain\Tenancy\Capability;
use Kadr\Infrastructure\Database\Connection;
use Kadr\Infrastructure\Database\Repositories\AssetRepository;
use Kadr\Infrastructure\Database\Repositories\ClientRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Infrastructure\WordPress\Container;
use Kadr\Presentation\Rest\Controller;
use Kadr\Presentation\Rest\TenantRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Widok „Dzisiaj" — jedno wejście, jedna odpowiedź.
 *
 * Pierwszy ekran panelu nie może wysyłać sześciu zapytań, bo jest otwierany
 * najczęściej ze wszystkich (docs/PERFORMANCE.md §5). Stąd jeden endpoint
 * zwracający komplet kafli zamiast sześciu endpointów zasobowych.
 *
 * Liczby pochodzą wyłącznie z bazy. Żadnej z nich nie ma prawa być,
 * gdy nie ma za nią danych (CLAUDE.md §9) — pusty panel pokazuje zera
 * i pusty stan, a nie przykładowe wartości.
 */
final class TodayController extends Controller {

	use TenantRequest;

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/today',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'show' ),
				'permission_callback' => $this->requires( Capability::AccessApp ),
			)
		);
	}

	public function show(): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$db        = Connection::get();
		$galleries = new GalleryRepository( $db, $tenant );
		$assets    = new AssetRepository( $db, $tenant );
		$clients   = new ClientRepository( $db, $tenant );

		$statuses     = $galleries->countsByStatus();
		$entitlements = Container::instance()->entitlementsFor( $tenant->id() );
		$usedBytes    = $assets->totalBytes();

		// `null` znaczy „bez limitu", a nie „zero" — rozróżnienie pilnowane
		// przez `Limit`, bo pomylenie ich raz już kosztowało plan Pro
		// pokazujący zero galerii.
		$storageLimit = $entitlements->limit( 'storage_limit_bytes' )->value;
		$galleryLimit = $entitlements->limit( 'gallery_limit' )->value;

		return $this->ok(
			array(
				'studio'    => Container::instance()->studioName( $tenant->id() ),
				'plan'      => array(
					'key'           => $entitlements->plan()->key,
					'name'          => $entitlements->plan()->name,
					'gallery_limit' => $galleryLimit,
					'storage_bytes' => $storageLimit,
				),
				'galleries' => array(
					'draft'     => $statuses['draft'] ?? 0,
					'published' => $statuses['published'] ?? 0,
					'expired'   => $statuses['expired'] ?? 0,
					'archived'  => $statuses['archived'] ?? 0,
					'active'    => $galleries->countActive(),
				),
				'clients'   => $clients->count(),
				'storage'   => array(
					'used_bytes'  => $usedBytes,
					'limit_bytes' => $storageLimit,
					// Procent liczy serwer, bo to on zna definicję planu.
					// Brak limitu (plan bez ograniczenia) to null, nie 0.
					'used_ratio'  => null === $storageLimit || $storageLimit <= 0
						? null
						: round( $usedBytes / $storageLimit, 4 ),
				),
				'expiring'  => array_map(
					static fn ( array $row ): array => array(
						'id'         => (string) $row['public_id'],
						'title'      => (string) $row['title'],
						'expires_at' => $row['expires_at'],
					),
					$galleries->expiringWithin( 7 )
				),
			)
		);
	}
}
