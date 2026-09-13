<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\WordPress;

use Kadr\Presentation\Rest\Routes\AssetsController;
use Kadr\Presentation\Rest\Routes\ClientsController;
use Kadr\Presentation\Rest\Routes\GalleriesController;
use Kadr\Presentation\Rest\Routes\SelectionsController;
use Kadr\Presentation\Rest\Routes\RegistrationController;
use Kadr\Presentation\Rest\Routes\SelectionController;
use Kadr\Presentation\Rest\Routes\SessionController;
use Kadr\Presentation\Rest\Routes\SharingController;
use Kadr\Presentation\Rest\Routes\UploadsController;
use Kadr\Presentation\Rest\Routes\TodayController;

defined( 'ABSPATH' ) || exit;

/**
 * Rejestracja tras REST API v1.
 *
 * Jedno miejsce, w którym widać cały kontrakt danych. REST jest jedynym
 * kanałem danych panelu i portalu klienta (CLAUDE.md §4.3) — nie ma
 * `admin-ajax`, nie ma danych przemycanych w zmiennych globalnych.
 */
final class Rest {

	public function register_hooks(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		foreach ( $this->controllers() as $controller ) {
			$controller->register_routes();
		}
	}

	/**
	 * @return list<\Kadr\Presentation\Rest\Controller>
	 */
	private function controllers(): array {
		return array(
			new TodayController(),
			new RegistrationController(),
			new SessionController(),
			new GalleriesController(),
			new SelectionsController(),
			new ClientsController(),
			new AssetsController(),
			new SharingController(),
			new SelectionController(),
			new UploadsController(),
		);
	}
}
