<?php
declare( strict_types=1 );

namespace Kadr\Presentation\App;

use Kadr\Domain\Billing\PlanRegistry;
use Kadr\Presentation\Rest\Routes\RegistrationController;
use Kadr\Presentation\Rest\Routes\SessionController;

defined( 'ABSPATH' ) || exit;

/**
 * Rejestracja i logowanie fotografa.
 *
 * Własne ekrany, nie `wp-login.php`. Powód nie jest kosmetyczny: fotograf
 * nigdy nie widzi WordPressa (CLAUDE.md §4.5), a ekran logowania jest
 * pierwszą rzeczą, którą widzi codziennie. Formularz WordPressa mówi mu
 * „to jest WordPress", zanim zdąży cokolwiek kliknąć.
 *
 * Jak w `Shell`, markup jest w osobnej metodzie `body()` i nie dotyka bazy
 * ani stanu WordPressa — dzięki temu ten sam kod renderuje harness podglądu.
 */
final class AuthPages {

	public function register_hooks(): void {
		add_action( 'kadr_render_route', array( $this, 'render' ), 10, 2 );
	}

	public function render( string $route, string $param ): void {
		if ( ! in_array( $route, array( 'register', 'signin' ), true ) ) {
			return;
		}

		status_header( 200 );
		nocache_headers();
		add_filter( 'show_admin_bar', '__return_false' );

		$isRegister = 'register' === $route;

		add_filter(
			'pre_get_document_title',
			static fn (): string => $isRegister
				? __( 'Załóż studio — Kadr', 'kadr' )
				: __( 'Zaloguj się — Kadr', 'kadr' )
		);

		$body = $this->body(
			$isRegister,
			wp_create_nonce( $isRegister ? RegistrationController::NONCE_ACTION : SessionController::NONCE_ACTION ),
			$this->requestedReturn(),
			home_url( '/' )
		);

		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php
		wp_robots_no_robots();
		wp_head();
?>
</head>
<body class="kadr kadr-app-document">
<?php
		echo $body; // phpcs:ignore WordPress.Security.EscapingOutput -- markup zbudowany w body(), każda wartość escapowana u źródła.
		wp_footer();
?>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * Markup ekranu uwierzytelniania.
	 *
	 * @param bool   $isRegister Rejestracja (true) albo logowanie (false).
	 * @param string $nonce      Jednorazowy token formularza.
	 * @param string $returnPath Ścieżka powrotu po zalogowaniu.
	 * @param string $homeUrl    Adres strony głównej.
	 */
	public function body( bool $isRegister, string $nonce, string $returnPath, string $homeUrl ): string {
		$home = rtrim( $homeUrl, '/' ) . '/';

		$switch = $isRegister
			? sprintf(
				'%s <a href="%s">%s</a>',
				esc_html__( 'Masz już konto?', 'kadr' ),
				esc_url( $home . 'logowanie' ),
				esc_html__( 'Zaloguj się', 'kadr' )
			)
			: sprintf(
				'%s <a href="%s">%s</a>',
				esc_html__( 'Nie masz jeszcze studia?', 'kadr' ),
				esc_url( $home . 'rejestracja' ),
				esc_html__( 'Załóż je za darmo', 'kadr' )
			);

		return sprintf(
			'<main class="kadr-auth" id="tresc">
	<div class="kadr-auth__panel">
		<a class="kadr-auth__brand" href="%1$s">Kadr</a>
		<h1 class="kadr-auth__title">%2$s</h1>
		<p class="kadr-auth__lead">%3$s</p>
		<div id="kadr-auth-form" data-mode="%4$s" data-nonce="%5$s" data-return="%6$s"></div>
		<p class="kadr-auth__switch">%7$s</p>
	</div>%8$s
</main>',
			esc_url( $home ),
			esc_html( $isRegister ? __( 'Załóż studio', 'kadr' ) : __( 'Zaloguj się', 'kadr' ) ),
			esc_html(
				$isRegister
					? __( 'Konto i studio powstają w jednym kroku. Bez karty, bez okresu próbnego do odliczania.', 'kadr' )
					: __( 'Panel studia: galerie, wybory klientów, zamówienia.', 'kadr' )
			),
			esc_attr( $isRegister ? 'register' : 'signin' ),
			esc_attr( $nonce ),
			esc_attr( $returnPath ),
			$switch,
			$isRegister ? $this->freePlanFacts() : ''
		);
	}

	/**
	 * Co dokładnie mieści się w planie darmowym.
	 *
	 * Liczby pochodzą z `PlanRegistry` — jedynego źródła cennika (ADR-008).
	 * Wpisanie ich tu na sztywno skończyłoby się tym, że strona rejestracji
	 * obiecuje co innego niż cennik.
	 */
	private function freePlanFacts(): string {
		$free = PlanRegistry::get( 'free' );

		if ( null === $free ) {
			return '';
		}

		$galleries = (int) $free->value( 'gallery_limit' );
		$clients   = (int) $free->value( 'client_limit' );
		$gigabytes = (int) round( (int) $free->value( 'storage_limit_bytes' ) / ( 1024 ** 3 ) );

		$facts = array(
			/* translators: %d: liczba galerii w planie darmowym */
			sprintf( _n( '%d galeria', '%d galerii', $galleries, 'kadr' ), $galleries ),
			/* translators: %d: liczba gigabajtów w planie darmowym */
			sprintf( __( '%d GB na pliki', 'kadr' ), $gigabytes ),
			/* translators: %d: liczba klientów w planie darmowym */
			sprintf( _n( '%d klient', '%d klientów', $clients, 'kadr' ), $clients ),
			__( 'Sprzedaż zdjęć ponad pakiet', 'kadr' ),
		);

		$items = '';

		foreach ( $facts as $fact ) {
			$items .= sprintf( '<li>%s</li>', esc_html( $fact ) );
		}

		return sprintf(
			'
	<aside class="kadr-auth__aside">
		<p class="kadr-auth__aside-label">%s</p>
		<ul class="kadr-auth__facts">%s</ul>
		<p class="kadr-auth__aside-note">%s</p>
	</aside>',
			esc_html__( 'Plan darmowy zawiera', 'kadr' ),
			$items,
			esc_html__( 'Plan zmienisz w panelu. Dane zostają, cokolwiek wybierzesz.', 'kadr' )
		);
	}

	/**
	 * Ścieżka, na którą wracamy po zalogowaniu.
	 *
	 * Przepuszczamy wyłącznie adresy wewnątrz panelu — formularz logowania
	 * przyjmujący dowolny adres zwrotny jest nośnikiem phishingu.
	 */
	private function requestedReturn(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- odczyt parametru nawigacyjnego, bez skutków ubocznych.
		$raw  = isset( $_GET['wroc'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['wroc'] ) ) : '';
		$path = '/' . ltrim( rawurldecode( $raw ), '/' );

		return 1 === preg_match( '~^/app(/[a-z0-9\-/]*)?$~', $path ) ? $path : '/app/';
	}
}
