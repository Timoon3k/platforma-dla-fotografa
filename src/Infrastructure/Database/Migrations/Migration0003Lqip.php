<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database\Migrations;

use Kadr\Domain\Persistence\Database;
use Kadr\Infrastructure\Database\Migration;
use Kadr\Infrastructure\Database\Schema\Grammar;
use Kadr\Infrastructure\Database\Schema\Tables;

/**
 * Miniatura wpisywana wprost w HTML galerii klienta (sesja 8).
 *
 * Kolumna, nie wariant w magazynie: sens LQIP-u polega na tym, że jest
 * W dokumencie, a nie za kolejnym żądaniem sieciowym.
 */
final class Migration0003Lqip implements Migration {

	public function version(): int {
		return 3;
	}

	public function description(): string {
		return 'Miniatura LQIP w tabeli zdjęć';
	}

	public function up( Database $db, Grammar $grammar ): void {
		$sample = $db->table( Tables::TENANTS );
		$prefix = substr( $sample, 0, strlen( $sample ) - strlen( Tables::TENANTS ) );

		foreach ( Tables::galleries() as $table ) {
			if ( Tables::GALLERY_ASSETS !== $table->name ) {
				continue;
			}

			// Schemat jest deklaratywny, więc NOWA instalacja dostaje tę kolumnę
			// już z pierwszej migracji, która tworzy tabelę. Dokładanie jej tutaj
			// skończyłoby się błędem przy każdej świeżej instalacji — sprawdzamy
			// więc, czy kolumna już istnieje.
			if ( $this->hasColumn( $db, $prefix . $table->name ) ) {
				return;
			}

			$db->execute( $grammar->addColumn( $table, 'lqip', $prefix ) );
		}
	}

	/**
	 * Czy kolumna już istnieje.
	 *
	 * Zapytanie zamiast introspekcji katalogu systemowego: `information_schema`
	 * i `pragma_table_info` mają inne nazwy i inny kształt w MySQL-u i SQLite,
	 * a nieudany `SELECT` jest tani i zachowuje się tak samo wszędzie.
	 */
	private function hasColumn( Database $db, string $table ): bool {
		try {
			$db->selectAll( sprintf( 'SELECT `lqip` FROM `%s` LIMIT 0', $table ) );

			return true;
		} catch ( \Throwable ) {
			return false;
		}
	}
}
