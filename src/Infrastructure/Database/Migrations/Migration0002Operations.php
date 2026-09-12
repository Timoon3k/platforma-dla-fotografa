<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database\Migrations;

use Kadr\Domain\Persistence\Database;
use Kadr\Infrastructure\Database\Migration;
use Kadr\Infrastructure\Database\Schema\Grammar;
use Kadr\Infrastructure\Database\Schema\Tables;

/**
 * Kolejka zadań i tokeny pobrania (sesja 4).
 */
final class Migration0002Operations implements Migration {

	public function version(): int {
		return 2;
	}

	public function description(): string {
		return 'Kolejka zadań i tokeny pobrania';
	}

	public function up( Database $db, Grammar $grammar ): void {
		$sample = $db->table( Tables::TENANTS );
		$prefix = substr( $sample, 0, strlen( $sample ) - strlen( Tables::TENANTS ) );

		foreach ( Tables::operations() as $table ) {
			foreach ( $grammar->createTable( $table, $prefix ) as $statement ) {
				$db->execute( $statement );
			}
		}
	}
}
