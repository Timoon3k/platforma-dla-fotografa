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

		// Migracje wykonują się WYŁĄCZNIE tutaj i przy aktualizacji wtyczki —
		// nigdy przy zwykłym żądaniu (docs/DATABASE.md §8).
		self::migrate();

		Capabilities::install();

		// Rejestrujemy typy treści i trasy zanim przepiszemy reguły.
		( new ContentTypes() )->register();
		( new Rewrites() )->register();
		flush_rewrite_rules();
	}

	/**
	 * Doprowadza schemat do bieżącej wersji.
	 *
	 * Wywoływane przy aktywacji oraz po aktualizacji wtyczki, gdy numer wersji
	 * w bazie jest starszy niż w kodzie.
	 *
	 * @return list<string> Opisy zastosowanych migracji.
	 */
	public static function migrate(): array {
		return \Kadr\Infrastructure\Database\Connection::runner()->run();
	}

	/**
	 * Czy schemat wymaga migracji.
	 */
	public static function needs_migration(): bool {
		return ! \Kadr\Infrastructure\Database\Connection::runner()->isUpToDate();
	}

	/**
	 * Czy wtyczka została właśnie zaktualizowana.
	 */
	public static function needs_upgrade(): bool {
		return \Kadr\VERSION !== (string) get_option( self::OPTION_VERSION, '' );
	}

	/**
	 * Dokończenie aktualizacji wtyczki.
	 *
	 * Nowa wersja potrafi dołożyć trasę (`/rejestracja`, `/logowanie`) albo
	 * uprawnienie. Jedno i drugie zaczyna działać dopiero po przepisaniu reguł
	 * i ponownym zainstalowaniu ról — bez tego fotograf po aktualizacji dostaje
	 * 404 na nowym adresie i nie ma pojęcia dlaczego.
	 *
	 * `flush_rewrite_rules()` jest kosztowne, więc uruchamia się WYŁĄCZNIE
	 * wtedy, gdy numer wersji w bazie różni się od numeru w kodzie — nigdy
	 * przy zwykłym żądaniu (docs/DATABASE.md §8).
	 */
	public static function upgrade(): void {
		self::migrate();
		Capabilities::install();

		( new ContentTypes() )->register();
		( new Rewrites() )->register();
		flush_rewrite_rules();

		update_option( self::OPTION_VERSION, \Kadr\VERSION, false );
	}

	public static function deactivate(): void {
		// Zatrzymujemy wyłącznie to, co cyklicznie pracuje. Dane pozostają nietknięte.
		wp_clear_scheduled_hook( 'kadr_daily_maintenance' );
		Worker::unschedule();
		flush_rewrite_rules();
	}
}
