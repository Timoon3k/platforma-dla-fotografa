<?php
declare( strict_types=1 );

namespace Kadr\Presentation\Rest\Routes;

use Kadr\Application\Studio\RegisterStudio;
use Kadr\Infrastructure\Database\Connection;
use Kadr\Infrastructure\Database\Platform\TenantStore;
use Kadr\Infrastructure\Security\WpCacheThrottle;
use Kadr\Infrastructure\WordPress\WpUserDirectory;
use Kadr\Presentation\Rest\Controller;

defined( 'ABSPATH' ) || exit;

/**
 * Rejestracja fotografa.
 *
 * Jedyny endpoint panelu dostępny bez zalogowania — z natury rzeczy, bo konta
 * jeszcze nie ma. `permission_callback` NIE jest tu `__return_true`
 * (CLAUDE.md §5): sprawdzamy nonce formularza, a use case dokłada limit prób
 * na adres IP. Bez tego endpoint jest darmowym generatorem kont WordPressa.
 */
final class RegistrationController extends Controller {

	public const NONCE_ACTION = 'kadr_register';

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/register',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create' ),
				'permission_callback' => array( $this, 'allowed' ),
				'args'                => array(
					'studio'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'email'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'password' => array(
						'required' => true,
						'type'     => 'string',
						// Hasła NIE sanityzujemy: `sanitize_text_field` wycięłoby
						// z niego znaki, a użytkownik zapisałby w menedżerze haseł
						// coś innego, niż trafiło do bazy.
					),
				),
			)
		);
	}

	public function allowed( \WP_REST_Request $request ): bool|\WP_Error {
		if ( is_user_logged_in() ) {
			return new \WP_Error(
				'kadr_already_signed_in',
				__( 'Jesteś już zalogowany.', 'kadr' ),
				array( 'status' => 409 )
			);
		}

		if ( ! wp_verify_nonce( (string) $request->get_header( 'X-Kadr-Nonce' ), self::NONCE_ACTION ) ) {
			return new \WP_Error(
				'kadr_invalid_form',
				__( 'Formularz wygasł. Odśwież stronę i spróbuj ponownie.', 'kadr' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	public function create( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$useCase = new RegisterStudio(
			new TenantStore( Connection::get() ),
			new WpUserDirectory(),
			new WpCacheThrottle()
		);

		$result = $useCase->handle(
			(string) $request->get_param( 'studio' ),
			(string) $request->get_param( 'email' ),
			(string) $request->get_param( 'password' ),
			$this->addressHash()
		);

		if ( $result->isFailure() ) {
			return $this->respond( $result );
		}

		return $this->ok(
			array(
				'studio'   => $result->value['studio'],
				'redirect' => home_url( '/app/' ),
			),
			array(),
			201
		);
	}

	/**
	 * Hash adresu IP, nigdy sam adres.
	 *
	 * Do limitowania prób wystarczy stabilny identyfikator; adres jest daną
	 * osobową i nie ma powodu trzymać go w pamięci podręcznej
	 * (docs/SECURITY.md §6).
	 */
	private function addressHash(): ?string {
		$address = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) )
			: '';

		return '' === $address ? null : hash( 'sha256', $address . wp_salt( 'auth' ) );
	}
}
