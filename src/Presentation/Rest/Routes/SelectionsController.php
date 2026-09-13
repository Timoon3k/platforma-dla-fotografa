<?php
declare( strict_types=1 );

namespace Kadr\Presentation\Rest\Routes;

use Kadr\Application\Selection\SelectionInbox;
use Kadr\Domain\Tenancy\Capability;
use Kadr\Infrastructure\Database\Connection;
use Kadr\Infrastructure\Database\Repositories\ClientRepository;
use Kadr\Infrastructure\Database\Repositories\GalleryRepository;
use Kadr\Infrastructure\Database\Repositories\SelectionRepository;
use Kadr\Presentation\Rest\Controller;
use Kadr\Presentation\Rest\TenantRequest;

defined( 'ABSPATH' ) || exit;

/**
 * Skrzynka wyborów fotografa — `GET kadr/v1/selections`.
 *
 * Osobna trasa od `/galleries/{id}/selection`: tamta odpowiada na pytanie
 * „jak wygląda wybór w tej galerii", ta na „gdzie dziś czeka na mnie praca".
 */
final class SelectionsController extends Controller {

	use TenantRequest;

	private const PAGE_SIZE = 50;

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/selections',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'index' ),
					'permission_callback' => $this->requires( Capability::ManageGalleries ),
				),
			)
		);
	}

	public function index( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$tenant = $this->tenant();

		if ( $tenant instanceof \WP_Error ) {
			return $tenant;
		}

		$db = Connection::get();

		$inbox = new SelectionInbox(
			new SelectionRepository( $db, $tenant ),
			new GalleryRepository( $db, $tenant ),
			new ClientRepository( $db, $tenant )
		);

		$result = $inbox->list( self::PAGE_SIZE );

		return $this->ok( $result['items'], $result['summary'] );
	}
}
