<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database\Migrations;

use Kadr\Domain\Persistence\Database;
use Kadr\Infrastructure\Database\Migration;
use Kadr\Infrastructure\Database\Schema\Grammar;
use Kadr\Infrastructure\Database\Schema\Tables;

/**
 * Katalog produktów i wariantów (sesja 12).
 */
final class Migration0005Catalogue implements Migration {

	public function version(): int {
		return 5;
	}

	public function description(): string {
		return 'Katalog produktów i wariantów';
	}

	public function up( Database $db, Grammar $grammar ): void {
		$sample = $db->table( Tables::TENANTS );
		$prefix = substr( $sample, 0, strlen( $sample ) - strlen( Tables::TENANTS ) );

		foreach ( Tables::catalogue() as $table ) {
			// Schemat jest deklaratywny, więc świeża instalacja dostaje te
			// tabele już z pierwszej migracji, która je tworzy.
			if ( $this->hasTable( $db, $prefix . $table->name ) ) {
				continue;
			}

			foreach ( $grammar->createTable( $table, $prefix ) as $statement ) {
				$db->execute( $statement );
			}
		}
	}

	private function hasTable( Database $db, string $table ): bool {
		try {
			$db->selectAll( sprintf( 'SELECT 1 FROM `%s` LIMIT 0', $table ) );

			return true;
		} catch ( \Throwable ) {
			return false;
		}
	}
}
