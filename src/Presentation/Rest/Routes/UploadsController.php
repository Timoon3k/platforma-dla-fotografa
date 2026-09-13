<?php
declare( strict_types=1 );

namespace Kadr\Presentation\Rest\Routes;

use Kadr\Application\Gallery\ChunkedUpload;
use Kadr\Domain\Shared\Ulid;
use Kadr\Domain\Tenancy\Capability;
use Kadr\Domain\Tenancy\TenantContext;
use Kadr\Domain\Upload\UploadPolicy;
use Kadr\Infrastructure\Database\Connection;
use Kadr\Infrastructure\Database\Repositories\AssetRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Infrastructure\WordPress\Container;
use Kadr\Presentation\Rest\Controller;
use Kadr\Presentation\Rest\TenantRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Wysyłanie zdjęć: zgłoszenie → fragmenty → scalenie.
 *
 * Trzy kroki, bo trzy realne problemy fotografa (ADR w `ChunkedUpload`):
 * niskie limity `upload_max_filesize`, zrywające łącze w połowie wesela
 * i konieczność odrzucenia pliku ZANIM przejdzie przez sieć 200 MB.
 *
 * Fragment przychodzi jako surowe ciało żądania, nie jako `multipart`.
 * Powód jest praktyczny: `multipart` przechodzi przez `$_FILES`, czyli
 * przez dysk tymczasowy i limity PHP, których właśnie unikamy.
 */
final class UploadsController extends Controller {

	use TenantRequest;

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/galleries/(?P<id>[0-9A-HJKMNP-TV-Z]{26})/uploads',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'begin' ),
				'permission_callback' => $this->requires( Capability::UploadAssets ),
				'args'                => array(
					'filename' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_file_name',
					),
					'bytes'    => array(
						'required' => true,
						'type'     => 'integer',
						'minimum'  => 1,
					),
					'hash'     => array(
						'required'          => true,
						'type'              => 'string',
						'pattern'           => '^[a-f0-9]{64}$',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/uploads/(?P<upload>[0-9A-HJKMNP-TV-Z]{26})/chunks/(?P<index>[0-9]{1,5})',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'chunk' ),
				'permission_callback' => $this->requires( Capability::UploadAssets ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/uploads/(?P<upload>[0-9A-HJKMNP-TV-Z]{26})/complete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'complete' ),
				'permission_callback' => $this->requires( Capability::UploadAssets ),
				'args'                => array(
					'gallery_id'  => array( 'required' => true, 'type' => 'string' ),
					'filename'    => array( 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_file_name' ),
					'chunk_count' => array( 'required' => true, 'type' => 'integer', 'minimum' => 1 ),
					'hash'        => array( 'required' => true, 'type' => 'string', 'pattern' => '^[a-f0-9]{64}$' ),
				),
			)
		);
	}

	public function begin( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$galleryId = Ulid::tryFrom( (string) $request->get_param( 'id' ) );

		if ( null === $galleryId ) {
			return $this->notFound();
		}

		return $this->respond(
			$this->useCase( $tenant )->begin(
				$galleryId,
				(string) $request->get_param( 'filename' ),
				(int) $request->get_param( 'bytes' ),
				strtolower( (string) $request->get_param( 'hash' ) )
			)
		);
	}

	public function chunk( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$uploadId = Ulid::tryFrom( (string) $request->get_param( 'upload' ) );

		if ( null === $uploadId ) {
			return $this->notFound();
		}

		$body = $request->get_body();

		if ( '' === $body ) {
			return new \WP_Error(
				'kadr_invalid_chunk',
				__( 'Pusty fragment.', 'kadr' ),
				array( 'status' => 400 )
			);
		}

		// Fragment nie może być większy niż zadeklarowany rozmiar — inaczej
		// „fragment" jest sposobem na obejście limitu rozmiaru pliku.
		if ( strlen( $body ) > UploadPolicy::CHUNK_BYTES ) {
			return new \WP_Error(
				'kadr_invalid_chunk',
				__( 'Fragment jest za duży.', 'kadr' ),
				array( 'status' => 413 )
			);
		}

		return $this->respond(
			$this->useCase( $tenant )->appendChunk( $uploadId, (int) $request->get_param( 'index' ), $body )
		);
	}

	public function complete( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$uploadId  = Ulid::tryFrom( (string) $request->get_param( 'upload' ) );
		$galleryId = Ulid::tryFrom( (string) $request->get_param( 'gallery_id' ) );

		if ( null === $uploadId || null === $galleryId ) {
			return $this->notFound();
		}

		return $this->respond(
			$this->useCase( $tenant )->complete(
				$uploadId,
				$galleryId,
				(string) $request->get_param( 'filename' ),
				(int) $request->get_param( 'chunk_count' ),
				strtolower( (string) $request->get_param( 'hash' ) )
			),
			201
		);
	}

	private function useCase( TenantContext $tenant ): ChunkedUpload {
		$db        = Connection::get();
		$container = Container::instance();

		return new ChunkedUpload(
			new GalleryRepository( $db, $tenant ),
			new AssetRepository( $db, $tenant ),
			$container->storage(),
			$container->queueFor( $tenant->id() ),
			$container->entitlementsFor( $tenant->id() ),
			$tenant->id()
		);
	}
}
