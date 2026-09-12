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

		( new ContentTypes() )->register_hooks();
		( new Blocks() )->register_hooks();
		( new Patterns() )->register_hooks();
		( new Assets() )->register_hooks();
		( new Consent() )->register_hooks();
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'kadr', false, dirname( plugin_basename( \Kadr\PLUGIN_FILE ) ) . '/languages' );
	}
}
