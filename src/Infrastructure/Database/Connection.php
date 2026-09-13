<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database;

use Kadr\Domain\Persistence\Database;
use Kadr\Infrastructure\Database\Migrations\Migration0001Core;
use Kadr\Infrastructure\Database\Migrations\Migration0002Operations;
use Kadr\Infrastructure\Database\Migrations\Migration0003Lqip;
use Kadr\Infrastructure\Database\Schema\MySqlGrammar;

defined( 'ABSPATH' ) || exit;

/**
 * Punkt wejścia do bazy w runtimie WordPressa.
 *
 * Jedyne miejsce, w którym sięgamy po globalne `$wpdb`. Reszta aplikacji
 * dostaje `Database` przez konstruktor.
 */
final class Connection {

	private static ?Database $instance = null;

	public static function get(): Database {
		if ( null === self::$instance ) {
			global $wpdb;
			self::$instance = new WpdbDatabase( $wpdb );
		}

		return self::$instance;
	}

	/**
	 * Lista migracji w kolejności wersji.
	 *
	 * @return list<Migration>
	 */
	public static function migrations(): array {
		return array(
			new Migration0001Core(),
			new Migration0002Operations(),
			new Migration0003Lqip(),
		);
	}

	public static function runner(): MigrationRunner {
		return new MigrationRunner(
			self::get(),
			new MySqlGrammar(),
			new OptionSchemaVersion(),
			self::migrations()
		);
	}
}
