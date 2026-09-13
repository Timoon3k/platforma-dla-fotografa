<?php
declare( strict_types=1 );

namespace Kadr\Presentation\Rest\Routes;

use Kadr\Domain\Security\RateLimit;
use Kadr\Infrastructure\Security\WpCacheThrottle;
use Kadr\Infrastructure\WordPress\Capabilities;
use Kadr\Presentation\Rest\Controller;

defined( 'ABSPATH' ) || exit;

/**
 * Logowanie fotografa.
 *
 * Hasło sprawdza WordPress (`wp_signon`) — własnej kryptografii nie piszemy
 * (ADR-003). Do nas należą trzy rzeczy, o które rdzeń nie zadba:
 *
 *  1. **Limit prób** na konto i na adres IP (docs/SECURITY.md §5).
 *  2. **Jeden komunikat** dla złego hasła i nieistniejącego konta — inaczej
 *     formularz logowania mówi, które adresy są zarejestrowane.
 *  3. **Sprawdzenie, czy to fotograf.** Konto WordPressa bez uprawnienia
 *     `kadr_access_app` nie ma czego szukać w panelu.
 */
final class SessionController extends Controller {

	public const NONCE_ACTION = 'kadr_signin';

	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/session',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create' ),
				'permission_callback' => array( $this, 'allowed' ),
				'args'                => array(
					'email'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_email',
					),
					'password' => array(
						'required' => true,
						'type'     => 'string',
					),
					'remember' => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'wroc'     => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	public function allowed( \WP_REST_Request $request ): bool|\WP_Error {
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
		$email    = (string) $request->get_param( 'email' );
		$throttle = new WpCacheThrottle();

		$byAccount = RateLimit::photographerLogin( $email );
		$byAddress = RateLimit::loginByAddress( $this->addressHash() );

		foreach ( array( $byAccount, $byAddress ) as $limit ) {
			if ( ! $throttle->isAllowed( $limit ) ) {
				return new \WP_Error(
					'kadr_rate_limited',
					__( 'Zbyt wiele prób logowania. Spróbuj ponownie za chwilę.', 'kadr' ),
					array(
						'status'      => 429,
						'retry_after' => $throttle->retryAfter( $limit ),
					)
				);
			}
		}

		// Próbę zapisujemy PRZED sprawdzeniem hasła. Liczenie dopiero
		// nieudanych prób pozwalałoby zgadywać w nieskończoność, byle trafić.
		$throttle->record( $byAccount );
		$throttle->record( $byAddress );

		$user = wp_signon(
			array(
				'user_login'    => $email,
				'user_password' => (string) $request->get_param( 'password' ),
				'remember'      => (bool) $request->get_param( 'remember' ),
			),
			is_ssl()
		);

		if ( is_wp_error( $user ) ) {
			return $this->rejected();
		}

		if ( ! Capabilities::isPhotographer( $user->ID ) ) {
			// Konto istnieje, ale nie jest kontem fotografa. Wylogowujemy je
			// z powrotem i odpowiadamy tak samo jak przy złym haśle — panel
			// nie potwierdza, jakie konta istnieją w instalacji.
			wp_logout();

			return $this->rejected();
		}

		return $this->ok( array( 'redirect' => $this->safeRedirect( (string) $request->get_param( 'wroc' ) ) ) );
	}

	private function rejected(): \WP_Error {
		return new \WP_Error(
			'kadr_invalid_credentials',
			__( 'Nieprawidłowy adres e-mail lub hasło.', 'kadr' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Adres powrotu po zalogowaniu.
	 *
	 * Przyjmujemy wyłącznie ścieżkę wewnątrz panelu. Otwarte przekierowanie
	 * z formularza logowania to klasyczny nośnik phishingu: link wygląda jak
	 * nasz, a ląduje gdzie indziej.
	 */
	private function safeRedirect( string $requested ): string {
		$path = '/' . ltrim( $requested, '/' );

		if ( 1 !== preg_match( '~^/app(/[a-z0-9\-/]*)?$~', $path ) ) {
			return home_url( '/app/' );
		}

		return home_url( $path );
	}

	private function addressHash(): string {
		$address = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) )
			: '';

		return hash( 'sha256', $address . wp_salt( 'auth' ) );
	}
}
