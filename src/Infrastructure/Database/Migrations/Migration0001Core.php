<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database\Migrations;

use Kadr\Domain\Persistence\Database;
use Kadr\Infrastructure\Database\Migration;
use Kadr\Infrastructure\Database\Schema\Grammar;
use Kadr\Infrastructure\Database\Schema\Tables;

/**
 * Rdzeń schematu: tenancy, klienci, galerie, zdjęcia, wybory, audyt.
 *
 * Tabele commerce i booking dochodzą w kolejnych migracjach (sesje 11–14).
 */
final class Migration0001Core implements Migration {

	public function version(): int {
		return 1;
	}

	public function description(): string {
		return 'Rdzeń: tenancy, klienci, galerie, zdjęcia, wybory, audyt';
	}

	public function up( Database $db, Grammar $grammar ): void {
		foreach ( Tables::all() as $table ) {
			foreach ( $grammar->createTable( $table, $this->prefix( $db ) ) as $statement ) {
				$db->execute( $statement );
			}
		}
	}

	/**
	 * Prefiks instalacji wyciągnięty z pełnej nazwy tabeli.
	 *
	 * Gramatyka składa nazwę z prefiksu i nazwy bazowej, a `Database::table()`
	 * zna wyłącznie pełną nazwę — stąd ta operacja.
	 */
	private function prefix( Database $db ): string {
		$sample = $db->table( Tables::TENANTS );

		return substr( $sample, 0, strlen( $sample ) - strlen( Tables::TENANTS ) );
	}
}
