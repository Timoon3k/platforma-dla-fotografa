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

	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register' ) );
		add_filter( 'body_class', array( $this, 'body_class' ) );
	}

	public function register(): void {
		$this->register_style( self::HANDLE_TOKENS, 'assets/css/tokens.css' );
		$this->register_style( 'kadr-base', 'assets/css/base.css', array( self::HANDLE_TOKENS ) );
		$this->register_style( 'kadr-components', 'assets/css/components.css', array( 'kadr-base' ) );

		// Wspólny arkusz bloków marketingowych. Wczytywany tylko przy obecności bloku.
		$this->register_style( self::HANDLE_MARKETING, 'assets/css/marketing.css', array( 'kadr-components' ) );

		$this->register_style( self::HANDLE_EDITOR_CSS, 'assets/css/editor.css', array( self::HANDLE_MARKETING ) );

		wp_register_script(
			self::HANDLE_EDITOR,
			Paths::url( 'assets/js/editor.js' ),
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
			Paths::asset_version( 'assets/js/editor.js' ),
			true
		);

		wp_set_script_translations( self::HANDLE_EDITOR, 'kadr', Paths::dir( 'languages' ) );

		wp_register_script(
			self::HANDLE_CONSENT,
			Paths::url( 'assets/js/consent.js' ),
			array(),
			Paths::asset_version( 'assets/js/consent.js' ),
			true
		);
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
