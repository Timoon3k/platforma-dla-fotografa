<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Rejestracja zasobów frontu i edytora.
 *
 * Zasada wydajnościowa (CLAUDE.md §6): style bloków są rejestrowane, ale
 * wczytywane wyłącznie wtedy, gdy blok faktycznie jest na stronie —
 * WordPress robi to sam na podstawie pola "style" w block.json.
 * Nie ma globalnego pakietu ładowanego na każdej podstronie.
 */
final class Assets {

	public const HANDLE_TOKENS     = 'kadr-tokens';
	public const HANDLE_MARKETING  = 'kadr-marketing';
	public const HANDLE_EDITOR     = 'kadr-block-editor';
	public const HANDLE_EDITOR_CSS = 'kadr-editor';
	public const HANDLE_CONSENT    = 'kadr-consent';
	public const HANDLE_MOTION     = 'kadr-motion';
	public const HANDLE_APP        = 'kadr-app';

	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_motion' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_app' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_auth' ), 20 );
		// Późno, żeby zdążyły odezwać się motyw i inne wtyczki.
		add_action( 'wp_enqueue_scripts', array( $this, 'isolate_app' ), 9999 );
		add_action( 'wp_head', array( $this, 'app_config' ), 1 );
		add_filter( 'body_class', array( $this, 'body_class' ) );
	}

	public function register(): void {
		$this->register_style( self::HANDLE_TOKENS, 'assets/css/tokens.css' );
		$this->register_style( 'kadr-base', 'assets/css/base.css', array( self::HANDLE_TOKENS ) );
		$this->register_style( 'kadr-components', 'assets/css/components.css', array( 'kadr-base' ) );

		// Warstwa ruchu (ADR-015). Zależy od tokenów, nie od komponentów.
		$this->register_style( self::HANDLE_MOTION, 'assets/css/motion.css', array( 'kadr-components' ) );

		// Wspólny arkusz bloków marketingowych. Wczytywany tylko przy obecności bloku.
		$this->register_style( self::HANDLE_MARKETING, 'assets/css/marketing.css', array( self::HANDLE_MOTION ) );

		$this->register_style( self::HANDLE_EDITOR_CSS, 'assets/css/editor.css', array( self::HANDLE_MARKETING ) );

		// Arkusz panelu. NIE ładuje się na stronie marketingowej (CLAUDE.md §6).
		$this->register_style( self::HANDLE_APP, 'assets/css/app.css', array( self::HANDLE_MOTION ) );

		wp_register_script(
			self::HANDLE_EDITOR,
			Paths::url( 'assets/js/editor.js' ),
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
			Paths::asset_version( 'assets/js/editor.js' ),
			true
		);

		wp_set_script_translations( self::HANDLE_EDITOR, 'kadr', Paths::dir( 'languages' ) );

		wp_register_script(
			self::HANDLE_MOTION,
			Paths::url( 'assets/js/motion.js' ),
			array(),
			Paths::asset_version( 'assets/js/motion.js' ),
			true
		);

		wp_register_script(
			self::HANDLE_CONSENT,
			Paths::url( 'assets/js/consent.js' ),
			array(),
			Paths::asset_version( 'assets/js/consent.js' ),
			true
		);
	}

	/**
	 * Zasoby panelu fotografa.
	 *
	 * Biblioteki frontendowe są dołączone jako moduły ES (ADR-018) i importowane
	 * ścieżkami względnymi, więc przeglądarka rozwiązuje je sama — nie potrzeba
	 * ani mapy importów, ani bundlera.
	 *
	 * `wp_enqueue_script_module` jest dostępne od WordPressa 6.5, czyli od naszej
	 * minimalnej wersji; zapas na starsze instalacje nie jest potrzebny.
	 */
	public function enqueue_app(): void {
		if ( 'app' !== Rewrites::currentRoute() ) {
			return;
		}

		wp_enqueue_style( self::HANDLE_APP );

		wp_enqueue_script_module(
			'kadr-app',
			Paths::url( 'assets/js/app/main.js' ),
			array(),
			Paths::asset_version( 'assets/js/app/main.js' )
		);
	}

	/**
	 * Ekrany rejestracji i logowania.
	 *
	 * Ładują ten sam arkusz co panel — to ta sama aplikacja, tylko przed
	 * zalogowaniem. Skrypt jest osobny, bo `main.js` montuje panel, którego
	 * tu nie ma.
	 */
	public function enqueue_auth(): void {
		if ( ! in_array( Rewrites::currentRoute(), array( 'register', 'signin' ), true ) ) {
			return;
		}

		wp_enqueue_style( self::HANDLE_APP );

		wp_enqueue_script_module(
			'kadr-auth',
			Paths::url( 'assets/js/app/auth.js' ),
			array(),
			Paths::asset_version( 'assets/js/app/auth.js' )
		);
	}

	/**
	 * Panel nie dziedziczy zasobów motywu ani innych wtyczek.
	 *
	 * Panel fotografa jest osobną aplikacją, nie podstroną motywu
	 * (CLAUDE.md §4.5). Arkusz motywu wstrzyknięty w ten dokument potrafi
	 * przesunąć układ, nadpisać typografię albo dołożyć własny pasek
	 * nawigacji — a fotograf nie ma jak tego naprawić.
	 *
	 * Nie jest to mur nie do przejścia: filtr `kadr_app_keep_asset` pozwala
	 * zostawić konkretny zasób, gdy instalacja tego potrzebuje.
	 */
	public function isolate_app(): void {
		if ( ! in_array( Rewrites::currentRoute(), array( 'app', 'register', 'signin' ), true ) ) {
			return;
		}

		// Kopia kolejki, bo dequeue modyfikuje ją w trakcie iteracji.
		foreach ( array_values( wp_styles()->queue ) as $handle ) {
			if ( ! $this->keeps_asset( (string) $handle ) ) {
				wp_dequeue_style( $handle );
			}
		}

		foreach ( array_values( wp_scripts()->queue ) as $handle ) {
			if ( ! $this->keeps_asset( (string) $handle ) ) {
				wp_dequeue_script( $handle );
			}
		}
	}

	private function keeps_asset( string $handle ): bool {
		$keep = str_starts_with( $handle, 'kadr' )
			|| in_array( $handle, array( 'wp-i18n', 'wp-polyfill' ), true );

		/**
		 * Czy zasób ma zostać w panelu mimo izolacji.
		 *
		 * @param bool   $keep   Domyślna decyzja.
		 * @param string $handle Uchwyt zasobu.
		 */
		return (bool) apply_filters( 'kadr_app_keep_asset', $keep, $handle );
	}

	/**
	 * Konfiguracja przekazywana do panelu.
	 *
	 * Wyłącznie to, czego panel potrzebuje, żeby odezwać się do API —
	 * żadnych danych biznesowych w globalnych zmiennych (CLAUDE.md §4).
	 */
	public function app_config(): void {
		$route = Rewrites::currentRoute();

		if ( in_array( $route, array( 'register', 'signin' ), true ) ) {
			// Ekrany uwierzytelniania dostają WYŁĄCZNIE adres API. Nonce
			// REST-owe jest tu bezużyteczne (nikt nie jest zalogowany),
			// a formularz i tak niesie własne, jednorazowe.
			printf(
				'<script id="kadr-auth-config">window.kadrAuth=%s;</script>',
				wp_json_encode( array( 'root' => esc_url_raw( rest_url( 'kadr/v1/' ) ) ) )
			);

			return;
		}

		if ( 'app' !== $route ) {
			return;
		}

		printf(
			'<script id="kadr-app-config">window.kadrApp=%s;</script>',
			wp_json_encode(
				array(
					'root'   => esc_url_raw( rest_url( 'kadr/v1/' ) ),
					'nonce'  => wp_create_nonce( 'wp_rest' ),
					'locale' => get_user_locale(),
					// Baza adresów panelu. Widoki budują z niej odnośniki
					// do zasobów zamiast zgadywać z bieżącego adresu.
					'app'    => esc_url_raw( home_url( '/app/' ) ),
				)
			)
		);
	}

	/**
	 * Warstwa ruchu ładuje się tam, gdzie jest blok Kadr — czyli razem
	 * z arkuszem marketingowym, a nie na każdej podstronie WordPressa.
	 */
	public function enqueue_motion(): void {
		if ( wp_style_is( self::HANDLE_MARKETING, 'enqueued' ) ) {
			wp_enqueue_script( self::HANDLE_MOTION );
		}
	}

	/**
	 * Klasa `kadr` otwiera zasięg design systemu.
	 *
	 * Wszystkie style są zakresowane do `.kadr`, żeby wtyczka nigdy nie
	 * nadpisała stylów motywu, w którym jest zainstalowana.
	 *
	 * @param array<int, string> $classes
	 * @return array<int, string>
	 */
	public function body_class( array $classes ): array {
		$classes[] = 'kadr';

		return $classes;
	}

	/**
	 * @param array<int, string> $deps
	 */
	private function register_style( string $handle, string $relative, array $deps = array() ): void {
		wp_register_style( $handle, Paths::url( $relative ), $deps, Paths::asset_version( $relative ) );
	}
}
