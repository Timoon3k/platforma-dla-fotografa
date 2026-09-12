<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Własne adresy aplikacji, poza WP Adminem (docs/ARCHITECTURE.md §3).
 *
 * Reguły przepisywania są odświeżane WYŁĄCZNIE przy aktywacji i migracji —
 * `flush_rewrite_rules()` przy każdym żądaniu to klasyczny sposób na
 * położenie serwisu.
 */
final class Rewrites {

	public const QUERY_VAR = 'kadr_route';
	public const PARAM_VAR = 'kadr_param';

	/**
	 * @var array<string, string> wzorzec => nazwa trasy
	 */
	private const ROUTES = array(
		'app(?:/(.*))?$' => 'app',      // panel fotografa
		'k(?:/(.*))?$'   => 'portal',   // portal klienta
		'g/([^/]+)/?$'   => 'gallery',  // galeria po tokenie
		'b/([^/]+)/?$'   => 'booking',  // strona rezerwacji
		'd/([^/]+)/?$'   => 'download', // pobranie po tokenie
	);

	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register' ), 5 );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'dispatch' ) );
	}

	public function register(): void {
		foreach ( self::ROUTES as $pattern => $route ) {
			add_rewrite_rule(
				'^' . $pattern,
				sprintf( 'index.php?%s=%s&%s=$matches[1]', self::QUERY_VAR, $route, self::PARAM_VAR ),
				'top'
			);
		}
	}

	/**
	 * @param array<int, string> $vars
	 * @return array<int, string>
	 */
	public function register_query_vars( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		$vars[] = self::PARAM_VAR;

		return $vars;
	}

	public static function currentRoute(): string {
		return (string) get_query_var( self::QUERY_VAR, '' );
	}

	public static function currentParam(): string {
		return (string) get_query_var( self::PARAM_VAR, '' );
	}

	/**
	 * Przekazanie żądania do właściwego widoku.
	 *
	 * Widoki aplikacji powstają w sesjach 6–10; na razie trasy istnieją
	 * i odpowiadają statusem 200, żeby dało się je sprawdzić w instalacji.
	 */
	public function dispatch(): void {
		$route = self::currentRoute();

		if ( '' === $route ) {
			return;
		}

		// Panel wymaga zalogowanego fotografa. Zanim powstaną widoki,
		// niezalogowany trafia na formularz logowania, a nie na pustą stronę.
		if ( in_array( $route, array( 'app' ), true ) && ! Capabilities::isPhotographer() ) {
			wp_safe_redirect( wp_login_url( home_url( '/app/' ) ) );
			exit;
		}

		/**
		 * Widoki podpinają się pod to działanie.
		 *
		 * @param string $route Nazwa trasy.
		 * @param string $param Pierwszy segment po nazwie trasy.
		 */
		do_action( 'kadr_render_route', $route, self::currentParam() );
	}
}
