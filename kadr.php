<?php
/**
 * Plugin Name:       Kadr
 * Plugin URI:        https://kadr.studio
 * Description:       Platforma SaaS dla profesjonalnych fotografów — od rezerwacji, przez wybór zdjęć, po sprzedaż i dostawę.
 * Version:           0.6.0
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            Kadr
 * License:           Proprietary
 * Text Domain:       kadr
 * Domain Path:       /languages
 *
 * @package Kadr
 */

declare( strict_types=1 );

namespace Kadr;

defined( 'ABSPATH' ) || exit;

const VERSION     = '0.6.0';
const PLUGIN_FILE = __FILE__;

/**
 * Autoloader PSR-4 dla przestrzeni Kadr\.
 *
 * Wtyczka działa bez `composer install` — Composer jest potrzebny wyłącznie
 * dla narzędzi deweloperskich (PHPCS, PHPStan). Jeśli `vendor/` istnieje,
 * korzystamy z niego; w przeciwnym razie z tego autoloadera.
 */
if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( string $class ): void {
			$prefix = __NAMESPACE__ . '\\';
			if ( ! str_starts_with( $class, $prefix ) ) {
				return;
			}
			$relative = substr( $class, strlen( $prefix ) );
			$path     = __DIR__ . '/src/' . str_replace( '\\', '/', $relative ) . '.php';
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	);
}

/**
 * Start wtyczki po sprawdzeniu wymagań środowiskowych.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		$requirements = new Infrastructure\WordPress\Requirements( '8.2', '6.5' );

		if ( ! $requirements->are_met() ) {
			$requirements->show_admin_notice();
			return;
		}

		( new Infrastructure\WordPress\Plugin() )->boot();
	}
);

register_activation_hook(
	__FILE__,
	static function (): void {
		Infrastructure\WordPress\Activation::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		Infrastructure\WordPress\Activation::deactivate();
	}
);
