<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database\Migrations;

use Kadr\Domain\Persistence\Database;
use Kadr\Infrastructure\Database\Migration;
use Kadr\Infrastructure\Database\Schema\Grammar;
use Kadr\Infrastructure\Database\Schema\Tables;

/**
 * Paczki plików przygotowywane w tle (sesja 10).
 */
final class Migration0004Archives implements Migration {

	public function version(): int {
		return 4;
	}

	public function description(): string {
		return 'Paczki plików do pobrania';
	}

	public function up( Database $db, Grammar $grammar ): void {
		$sample = $db->table( Tables::TENANTS );
		$prefix = substr( $sample, 0, strlen( $sample ) - strlen( Tables::TENANTS ) );
		$table  = Tables::byName( Tables::ARCHIVES );

		// Schemat jest deklaratywny, więc świeża instalacja dostaje tę tabelę
		// już z migracji 0002. Tworzenie jej drugi raz kończyłoby się błędem.
		if ( $this->hasTable( $db, $prefix . $table->name ) ) {
			return;
		}

		foreach ( $grammar->createTable( $table, $prefix ) as $statement ) {
			$db->execute( $statement );
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
