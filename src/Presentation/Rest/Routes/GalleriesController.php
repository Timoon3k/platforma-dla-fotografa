<?php
declare( strict_types=1 );

namespace Kadr\Presentation\Rest\Routes;

use Kadr\Domain\Tenancy\Capability;
use Kadr\Infrastructure\Database\Connection;
use Kadr\Infrastructure\Database\Repositories\AssetRepository;
use Kadr\Infrastructure\Database\Repositories\ClientRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Presentation\Rest\Controller;
use Kadr\Presentation\Rest\TenantRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Galerie fotografa — lista dla panelu.
 *
 * Kontroler nie zna reguł biznesowych: pobiera stronę z repozytorium
 * (które samo filtruje po tenancie) i tłumaczy wiersze na zasób API.
 */
final class GalleriesController extends Controller {

	use TenantRequest;

	private const PAGE_SIZE = 25;

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/galleries',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'index' ),
				'permission_callback' => $this->requires( Capability::ManageGalleries ),
				'args'                => array(
					'status' => array(
						'type'              => 'string',
						'enum'              => array( 'draft', 'published', 'expired', 'archived' ),
						'sanitize_callback' => 'sanitize_key',
					),
					'q'      => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'cursor' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	public function index( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$db        = Connection::get();
		$galleries = new GalleryRepository( $db, $tenant );
		$assets    = new AssetRepository( $db, $tenant );
		$clients   = new ClientRepository( $db, $tenant );

		$status = $request->get_param( 'status' );
		$search = $request->get_param( 'q' );

		// O jeden wiersz więcej, niż oddamy: obecność nadmiarowego wiersza
		// mówi, że jest kolejna strona, bez osobnego zapytania COUNT.
		$rows = $galleries->page(
			is_string( $status ) && '' !== $status ? $status : null,
			is_string( $search ) && '' !== $search ? $search : null,
			(string) $request->get_param( 'cursor' ) ?: null,
			self::PAGE_SIZE + 1
		);

		$hasMore = count( $rows ) > self::PAGE_SIZE;
		$rows    = array_slice( $rows, 0, self::PAGE_SIZE );

		$photoCounts = $assets->countsByGallery();
		$clientNames = $this->clientNames( $clients, $rows );

		$items = array_map(
			fn ( array $row ): array => $this->resource( $row, $photoCounts, $clientNames ),
			$rows
		);

		return $this->collection(
			$items,
			$hasMore && array() !== $rows ? (string) $rows[ count( $rows ) - 1 ]['public_id'] : null
		);
	}

	/**
	 * Nazwy klientów dla galerii z bieżącej strony.
	 *
	 * Pobieramy je raz, dla wszystkich wierszy naraz — pytanie o klienta
	 * przy każdej galerii byłoby N+1.
	 *
	 * @param list<array<string, mixed>> $rows
	 * @return array<int, string>
	 */
	private function clientNames( ClientRepository $clients, array $rows ): array {
		$names = array();

		foreach ( $clients->all( 500 ) as $client ) {
			$names[ (int) $client['id'] ] = trim(
				sprintf( '%s %s', (string) $client['first_name'], (string) ( $client['last_name'] ?? '' ) )
			);
		}

		return $names;
	}

	/**
	 * Wiersz bazy → zasób API.
	 *
	 * Na zewnątrz wychodzi `public_id` (ULID), nigdy sekwencyjne `id`
	 * (docs/SECURITY.md §1). Kwoty w groszach — nigdy zmiennoprzecinkowe.
	 *
	 * @param array<string, mixed> $row
	 * @param array<int, int>      $photoCounts
	 * @param array<int, string>   $clientNames
	 * @return array<string, mixed>
	 */
	private function resource( array $row, array $photoCounts, array $clientNames ): array {
		$clientId = null === $row['client_id'] ? null : (int) $row['client_id'];

		return array(
			'id'                => (string) $row['public_id'],
			'title'             => (string) $row['title'],
			'slug'              => (string) $row['slug'],
			'status'            => (string) $row['status'],
			'theme'             => (string) $row['theme'],
			'client'            => null === $clientId ? null : ( $clientNames[ $clientId ] ?? null ),
			'photos'            => $photoCounts[ (int) $row['id'] ] ?? 0,
			'package_limit'     => null === $row['package_limit'] ? null : (int) $row['package_limit'],
			'extra_photo_price' => null === $row['extra_photo_price'] ? null : (int) $row['extra_photo_price'],
			'allow_download'    => (bool) $row['allow_download'],
			'published_at'      => $row['published_at'],
			'expires_at'        => $row['expires_at'],
			'created_at'        => $row['created_at'],
		);
	}
}
