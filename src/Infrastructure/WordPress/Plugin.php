<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Kompozycja wtyczki — jedyne miejsce, w którym powstają obiekty warstwy WordPressa.
 *
 * Klasa nie zawiera logiki biznesowej. Rejestruje moduły i oddaje im sterowanie.
 */
final class Plugin {

	public function boot(): void {
		add_action( 'init', array( $this, 'load_textdomain' ), 0 );
		add_action( 'admin_init', array( $this, 'maybe_migrate' ) );

		( new Capabilities() )->register_hooks();
		( new Rewrites() )->register_hooks();
		( new Worker() )->register_hooks();
		( new ContentTypes() )->register_hooks();
		( new Blocks() )->register_hooks();
		( new Patterns() )->register_hooks();
		( new Assets() )->register_hooks();
		( new Consent() )->register_hooks();
	}

	/**
	 * Migracja po aktualizacji wtyczki.
	 *
	 * Sprawdzenie jest tanie (jeden odczyt opcji), a samo uruchomienie
	 * następuje wyłącznie wtedy, gdy schemat faktycznie jest starszy.
	 * Celowo tylko w panelu administratora — nie na żądaniach odwiedzających.
	 */
	public function maybe_migrate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( Activation::needs_migration() ) {
			Activation::migrate();
		}
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'kadr', false, dirname( plugin_basename( \Kadr\PLUGIN_FILE ) ) . '/languages' );
	}
}
