<?php
declare( strict_types=1 );

namespace Kadr\Presentation\Rest\Routes;

use Kadr\Application\Gallery\ShareGallery;
use Kadr\Domain\Security\AccessGrant;
use Kadr\Domain\Shared\SystemClock;
use Kadr\Domain\Shared\Ulid;
use Kadr\Domain\Tenancy\Capability;
use Kadr\Infrastructure\Database\Connection;
use Kadr\Infrastructure\Database\Repositories\GalleryAccessRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Presentation\Rest\Controller;
use Kadr\Presentation\Rest\TenantRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Linki do galerii dla klienta.
 *
 * Jawny link wychodzi TYLKO w odpowiedzi na jego utworzenie. Lista pokazuje
 * metadane — kiedy powstał, czy ma PIN, ile razy otwarto — ale nie da się
 * z niej odtworzyć adresu, bo w bazie jest wyłącznie hash (docs/SECURITY.md §3).
 * Fotograf, który zgubi link, generuje nowy. To jest cecha, nie brak.
 */
final class SharingController extends Controller {

	use TenantRequest;

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/galleries/(?P<id>[0-9A-HJKMNP-TV-Z]{26})/access',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'index' ),
					'permission_callback' => $this->requires( Capability::ManageGalleries ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create' ),
					'permission_callback' => $this->requires( Capability::ManageGalleries ),
					'args'                => array(
						'pin'  => array(
							'type'              => array( 'string', 'null' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
						'days' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 3650,
						),
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/galleries/(?P<id>[0-9A-HJKMNP-TV-Z]{26})/access/(?P<access>[0-9]+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'destroy' ),
				'permission_callback' => $this->requires( Capability::ManageGalleries ),
			)
		);
	}

	public function index( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$id = Ulid::tryFrom( (string) $request->get_param( 'id' ) );

		if ( null === $id ) {
			return $this->notFound();
		}

		$db      = Connection::get();
		$gallery = ( new GalleryRepository( $db, $tenant ) )->findByPublicId( $id );

		if ( null === $gallery ) {
			return $this->notFound();
		}

		$clock = new SystemClock();
		$rows  = ( new GalleryAccessRepository( $db, $tenant ) )->forGallery( (int) $gallery['id'] );

		return $this->collection(
			array_map(
				static fn ( array $row ): array => array(
					'id'         => (int) $row['id'],
					'has_pin'    => null !== $row['pin_hash'] && '' !== (string) $row['pin_hash'],
					'opened'     => (int) $row['used_count'],
					'expires_at' => $row['expires_at'],
					'revoked_at' => $row['revoked_at'],
					'active'     => AccessGrant::fromRow( $row )->isUsableAt( $clock ),
					'created_at' => $row['created_at'] ?? null,
				),
				$rows
			)
		);
	}

	public function create( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$id = Ulid::tryFrom( (string) $request->get_param( 'id' ) );

		if ( null === $id ) {
			return $this->notFound();
		}

		$pin = $request->get_param( 'pin' );
		$pin = is_string( $pin ) && '' !== $pin ? $pin : null;

		$result = $this->useCase( $tenant )->issue( $id, $pin, $request->get_param( 'days' ) );

		if ( $result->isFailure() ) {
			return $this->respond( $result );
		}

		return $this->ok(
			array(
				'id'         => $result->value['id'],
				// Jedyny moment, w którym ten adres istnieje. Nie da się go
				// odtworzyć później — ani nam, ani komuś, kto wykradnie bazę.
				'url'        => home_url( '/g/' . $result->value['token'] ),
				'has_pin'    => $result->value['pin'],
				'expires_in' => $result->value['expires_in'],
			),
			array(),
			201
		);
	}

	public function destroy( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$id = Ulid::tryFrom( (string) $request->get_param( 'id' ) );

		if ( null === $id ) {
			return $this->notFound();
		}

		$result = $this->useCase( $tenant )->revoke( $id, (int) $request->get_param( 'access' ) );

		return $result->isFailure() ? $this->respond( $result ) : $this->ok( null, array(), 204 );
	}

	private function useCase( \Kadr\Domain\Tenancy\TenantContext $tenant ): ShareGallery {
		$db = Connection::get();

		return new ShareGallery(
			new GalleryRepository( $db, $tenant ),
			new GalleryAccessRepository( $db, $tenant ),
			new SystemClock()
		);
	}
}
