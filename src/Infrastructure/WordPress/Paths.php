<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Ścieżki i adresy wtyczki w jednym miejscu.
 *
 * Dzięki temu żaden moduł nie sklepuje własnych ścieżek z plugin_dir_path().
 */
final class Paths {

	public static function dir( string $relative = '' ): string {
		return plugin_dir_path( \Kadr\PLUGIN_FILE ) . ltrim( $relative, '/' );
	}

	public static function url( string $relative = '' ): string {
		return plugin_dir_url( \Kadr\PLUGIN_FILE ) . ltrim( $relative, '/' );
	}

	/**
	 * Wersja zasobu oparta o czas modyfikacji pliku.
	 *
	 * Cache busting bez ręcznego podbijania numerów i bez wymuszania
	 * przeładowania wszystkich zasobów przy każdej aktualizacji wtyczki.
	 */
	public static function asset_version( string $relative ): string {
		$path = self::dir( $relative );
		$time = is_readable( $path ) ? filemtime( $path ) : false;

		return false !== $time ? (string) $time : \Kadr\VERSION;
	}
}
