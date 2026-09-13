<?php
declare( strict_types=1 );

namespace Kadr\Presentation\Rest\Routes;

use Kadr\Application\Gallery\ArrangeGallery;
use Kadr\Domain\Shared\Ulid;
use Kadr\Domain\Tenancy\Capability;
use Kadr\Infrastructure\Database\Connection;
use Kadr\Infrastructure\Database\Repositories\AssetRepository;
use Kadr\Infrastructure\Database\Repositories\AssetVariantRepository;
use Kadr\Domain\Storage\StoragePath;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Infrastructure\WordPress\Container;
use Kadr\Presentation\Rest\Controller;
use Kadr\Presentation\Rest\TenantRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Zdjęcia w galerii.
 *
 * Siatka w panelu potrafi mieć półtora tysiąca kadrów, więc lista idzie
 * stronami po sto — i w jednym zapytaniu dokłada warianty miniatur, zamiast
 * pytać o nie osobno dla każdego zdjęcia.
 */
final class AssetsController extends Controller {

	use TenantRequest;

	private const PAGE_SIZE = 100;

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/galleries/(?P<id>[0-9A-HJKMNP-TV-Z]{26})/assets',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'index' ),
				'permission_callback' => $this->requires( Capability::ManageGalleries ),
				'args'                => array(
					'page' => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/galleries/(?P<id>[0-9A-HJKMNP-TV-Z]{26})/assets/order',
			array(
				'methods'             => 'PATCH',
				'callback'            => array( $this, 'arrange' ),
				'permission_callback' => $this->requires( Capability::ManageGalleries ),
				'args'                => array(
					'move'   => array(
						'type'     => 'array',
						'required' => true,
						'items'    => array( 'type' => 'string' ),
					),
					'before' => array( 'type' => 'string' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/assets/(?P<asset>[0-9A-HJKMNP-TV-Z]{26})',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'destroy' ),
				'permission_callback' => $this->requires( Capability::ManageGalleries ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/assets/(?P<asset>[0-9A-HJKMNP-TV-Z]{26})/thumb',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'thumb' ),
				'permission_callback' => $this->requires( Capability::ManageGalleries ),
			)
		);
	}

	/**
	 * Miniatura zdjęcia — przez kontrolowany endpoint, nie z katalogu.
	 *
	 * Prywatne zdjęcie nigdy nie leży pod przewidywalnym publicznym adresem
	 * (CLAUDE.md §5). Plik jest poza `wp-content/uploads`, a serwer WWW nie
	 * ma do niego dostępu — jedyną drogą jest to żądanie, które najpierw
	 * sprawdza uprawnienie i tenanta.
	 */
	public function thumb( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$assetId = Ulid::tryFrom( (string) $request->get_param( 'asset' ) );

		if ( null === $assetId ) {
			return $this->notFound();
		}

		$db    = Connection::get();
		$asset = ( new AssetRepository( $db, $tenant ) )->findByPublicId( $assetId );

		if ( null === $asset ) {
			return $this->notFound();
		}

		$variants = new AssetVariantRepository( $db, $tenant );
		$storage  = Container::instance()->storage();

		// Kolejność ma znaczenie: AVIF jest najmniejszy, WebP jest zapasem
		// dla starszych przeglądarek. Serwujemy pierwszy, który istnieje.
		foreach ( array( 'avif', 'webp', 'jpeg' ) as $format ) {
			$variant = $variants->find( (int) $asset['id'], 'thumb', $format );

			if ( null === $variant ) {
				continue;
			}

			$path = StoragePath::fromString( (string) $variant['storage_path'] );

			if ( ! $storage->exists( $path ) ) {
				continue;
			}

			return $this->stream( $storage->readStream( $path ), $format, (int) $variant['bytes'] );
		}

		// Zdjęcie jest, ale wariantów jeszcze nie ma — kolejka je dopiero
		// przetwarza. To nie jest błąd, tylko stan przejściowy.
		return new \WP_Error(
			'kadr_variant_pending',
			__( 'Miniatura jest jeszcze przygotowywana.', 'kadr' ),
			array( 'status' => 409 )
		);
	}

	/**
	 * Wysłanie pliku strumieniem.
	 *
	 * Strumieniem, nie `file_get_contents`: przy setce miniatur na stronie
	 * wczytywanie każdej w całości do pamięci PHP jest niepotrzebnym kosztem.
	 *
	 * @param resource|mixed $stream
	 */
	private function stream( mixed $stream, string $format, int $bytes ): \WP_REST_Response {
		$types = array(
			'avif' => 'image/avif',
			'webp' => 'image/webp',
			'jpeg' => 'image/jpeg',
		);

		$response = new \WP_REST_Response();
		$response->set_headers(
			array(
				'Content-Type'   => $types[ $format ],
				'Content-Length' => (string) $bytes,
				// Miniatura jest niezmienna: jej zawartość zmienia się tylko
				// razem z identyfikatorem wariantu. Prywatna, więc `private`.
				'Cache-Control'  => 'private, max-age=86400, immutable',
			)
		);

		$response->set_data( null );

		add_filter(
			'rest_pre_serve_request',
			static function ( bool $served ) use ( $stream ): bool {
				if ( is_resource( $stream ) ) {
					fpassthru( $stream );
					fclose( $stream );
				}

				return true;
			}
		);

		return $response;
	}

	public function index( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$galleryId = Ulid::tryFrom( (string) $request->get_param( 'id' ) );

		if ( null === $galleryId ) {
			return $this->notFound();
		}

		$db      = Connection::get();
		$gallery = ( new GalleryRepository( $db, $tenant ) )->findByPublicId( $galleryId );

		if ( null === $gallery ) {
			return $this->notFound();
		}

		$assets = new AssetRepository( $db, $tenant );
		$page   = max( 1, (int) $request->get_param( 'page' ) );
		$rows   = $assets->forGallery( (int) $gallery['id'], self::PAGE_SIZE, ( $page - 1 ) * self::PAGE_SIZE );

		$variants = ( new AssetVariantRepository( $db, $tenant ) )->forAssets(
			array_map( static fn ( array $row ): int => (int) $row['id'], $rows )
		);

		$total = $assets->countForGallery( (int) $gallery['id'] );

		return $this->ok(
			array_map(
				fn ( array $row ): array => $this->resource( $row, $variants[ (int) $row['id'] ] ?? array() ),
				$rows
			),
			array(
				'count'    => count( $rows ),
				'total'    => $total,
				'page'     => $page,
				'has_more' => $page * self::PAGE_SIZE < $total,
			)
		);
	}

	public function destroy( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$assetId = Ulid::tryFrom( (string) $request->get_param( 'asset' ) );

		if ( null === $assetId ) {
			return $this->notFound();
		}

		$removed = ( new AssetRepository( Connection::get(), $tenant ) )->delete( $assetId );

		return 0 === $removed ? $this->notFound() : $this->ok( null, array(), 204 );
	}

	/**
	 * Wiersz bazy → zasób API.
	 *
	 * Wymiary wychodzą zawsze, również dla zdjęcia jeszcze przetwarzanego:
	 * siatka rezerwuje na nie miejsce o właściwych proporcjach i nie skacze,
	 * gdy miniatura dojdzie (CLAUDE.md §6, zero CLS).
	 *
	 * Ścieżek plików NIE wystawiamy. Prywatne zdjęcie nigdy nie leży pod
	 * przewidywalnym adresem (docs/SECURITY.md §3) — miniatura idzie przez
	 * kontrolowany endpoint pobrania.
	 *
	 * @param array<string, mixed>       $row
	 * @param list<array<string, mixed>> $variants
	 * @return array<string, mixed>
	 */
	/**
	 * Zmiana kolejności zdjęć.
	 *
	 * Żądanie opisuje zamiar: „przenieś te kadry przed ten". Pełną kolejność
	 * wylicza serwer — przeglądarka ma wczytaną tylko część galerii i nie
	 * zna pozycji kadrów, do których jeszcze nie doszła.
	 */
	public function arrange( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$gallery = $this->identifier( $request );

		if ( null === $gallery ) {
			return $this->notFound();
		}

		$moved = array();

		foreach ( (array) $request->get_param( 'move' ) as $value ) {
			$id = Ulid::tryFrom( (string) $value );

			// Identyfikator spoza alfabetu ULID-a nie trafia do repozytorium —
			// odrzucamy go tutaj, a nie w zapytaniu.
			if ( null === $id ) {
				return $this->notFound();
			}

			$moved[] = $id;
		}

		$beforeParam = $request->get_param( 'before' );
		$before      = null;

		if ( is_string( $beforeParam ) && '' !== $beforeParam ) {
			$before = Ulid::tryFrom( $beforeParam );

			if ( null === $before ) {
				return $this->notFound();
			}
		}

		$arrange = new ArrangeGallery(
			new GalleryRepository( Connection::get(), $tenant ),
			new AssetRepository( Connection::get(), $tenant )
		);

		return $this->respond( $arrange->move( $gallery, $moved, $before ) );
	}

	private function resource( array $row, array $variants ): array {
		$thumb = null;

		foreach ( $variants as $variant ) {
			if ( 'thumb' === (string) $variant['variant'] ) {
				$thumb = $variant;
				break;
			}
		}

		return array(
			'id'         => (string) $row['public_id'],
			'name'       => (string) $row['original_name'],
			'status'     => (string) $row['status'],
			'width'      => (int) $row['width'],
			'height'     => (int) $row['height'],
			'bytes'      => (int) $row['bytes'],
			'sort_order' => (int) $row['sort_order'],
			'thumb'      => null === $thumb
				? null
				: rest_url( sprintf( 'kadr/v1/assets/%s/thumb', (string) $row['public_id'] ) ),
		);
	}
}
