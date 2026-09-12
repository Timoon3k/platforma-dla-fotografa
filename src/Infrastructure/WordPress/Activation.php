<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Aktywacja i deaktywacja wtyczki.
 *
 * Deaktywacja NIGDY nie usuwa danych (CLAUDE.md §4, docs/ARCHITECTURE.md §9).
 */
final class Activation {

	public const OPTION_VERSION = 'kadr_version';

	public static function activate(): void {
		update_option( self::OPTION_VERSION, \Kadr\VERSION, false );

		// Rejestrujemy typy treści zanim przepiszemy reguły, żeby trafiły do nowych reguł.
		( new ContentTypes() )->register();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		// Zatrzymujemy wyłącznie to, co cyklicznie pracuje. Dane pozostają nietknięte.
		wp_clear_scheduled_hook( 'kadr_daily_maintenance' );
		flush_rewrite_rules();
	}
}
