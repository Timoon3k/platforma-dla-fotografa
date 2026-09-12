<?php
declare( strict_types=1 );

namespace Kadr\Tests\Domain;

use Kadr\Infrastructure\Database\Schema\MySqlGrammar;
use Kadr\Infrastructure\Database\Schema\SqliteGrammar;
use Kadr\Infrastructure\Database\Schema\Tables;
use Kadr\Tests\TestCase;

/**
 * Reguły schematu wymuszane automatycznie.
 *
 * Powód istnienia: test izolacji wykrył, że `UNIQUE(selection_id, asset_id)`
 * bez `tenant_id` powoduje, iż wpis jednego fotografa blokuje zapis drugiemu.
 * Ten sam błąd był w trzech tabelach. Zamiast poprawić trzy miejsca i liczyć
 * na pamięć, pilnujemy reguły testem.
 */
final class SchemaTest extends TestCase {

	/** Wartości unikalne globalnie z założenia — ich globalność jest funkcją. */
	private const GLOBALLY_UNIQUE = array( 'public_id', 'token_hash' );

	public function testEveryTenantScopedUniqueKeyStartsWithTenantId(): void {
		foreach ( Tables::all() as $table ) {
			if ( ! $table->isTenantScoped() ) {
				continue;
			}

			foreach ( $table->allIndexes() as $index ) {
				if ( ! $index['unique'] ) {
					continue;
				}

				$columns = $index['columns'];

				$isGlobal = 1 === count( $columns ) && in_array( $columns[0], self::GLOBALLY_UNIQUE, true );
				$isScoped = 'tenant_id' === ( $columns[0] ?? '' );

				$this->assertTrue(
					$isGlobal || $isScoped,
					sprintf(
						'Tabela %s: UNIQUE(%s) nie zaczyna się od tenant_id, '
						. 'więc wiersz jednego tenanta zablokuje zapis drugiemu.',
						$table->name,
						implode( ', ', $columns )
					)
				);
			}
		}
	}

	public function testEveryTenantScopedIndexStartsWithTenantId(): void {
		foreach ( Tables::all() as $table ) {
			if ( ! $table->isTenantScoped() ) {
				continue;
			}

			// Tabele jawnie zadeklarowane jako odpytywane ponad tenantami
			// (kolejka) mają inny wzorzec dostępu i inne indeksy.
			if ( $table->hasCrossTenantReads() ) {
				continue;
			}

			foreach ( $table->allIndexes() as $index ) {
				if ( $index['unique'] ) {
					continue;
				}

				$first = $index['columns'][0] ?? '';

				// Indeksy pomocnicze na pojedynczej kolumnie (np. expires_at
				// dla zadania czyszczącego) są dopuszczalne — zadanie w tle
				// nie działa w kontekście tenanta.
				$this->assertTrue(
					'tenant_id' === $first || 1 === count( $index['columns'] ),
					sprintf(
						'Tabela %s: indeks złożony (%s) nie zaczyna się od tenant_id, '
						. 'więc będzie bezużyteczny w zapytaniach wielotenantowych.',
						$table->name,
						implode( ', ', $index['columns'] )
					)
				);
			}
		}
	}

	/**
	 * Zwolnienie z reguły indeksów wymaga uzasadnienia zapisanego w kodzie,
	 * a nie samego wywołania metody.
	 */
	public function testCrossTenantTablesDocumentTheirReason(): void {
		foreach ( Tables::all() as $table ) {
			if ( ! $table->hasCrossTenantReads() ) {
				continue;
			}

			$this->assertTrue(
				strlen( $table->crossTenantReason() ) > 40,
				sprintf( 'Tabela %s nie uzasadnia odczytu ponadtenantowego.', $table->name )
			);

			// Odczyt ponad tenantami nie znosi przynależności wierszy.
			$this->assertTrue(
				in_array( 'tenant_id', $table->columnNames(), true ),
				sprintf( 'Tabela %s nadal musi mieć kolumnę tenant_id.', $table->name )
			);
		}
	}

	public function testOnlyTheTenantsTableLacksATenantColumn(): void {
		$withoutTenant = array();

		foreach ( Tables::all() as $table ) {
			if ( ! $table->isTenantScoped() ) {
				$withoutTenant[] = $table->name;
			}
		}

		$this->assertSame( array( Tables::TENANTS ), $withoutTenant );
	}

	public function testEveryTableCarriesCreationTimestamp(): void {
		foreach ( Tables::all() as $table ) {
			$this->assertTrue(
				in_array( 'created_at', $table->columnNames(), true ),
				sprintf( 'Tabela %s nie ma kolumny created_at.', $table->name )
			);
		}
	}

	public function testBothGrammarsHandleEveryTable(): void {
		$mysql  = new MySqlGrammar();
		$sqlite = new SqliteGrammar();

		foreach ( Tables::all() as $table ) {
			$this->assertTrue( array() !== $mysql->createTable( $table, 'wp_' ) );
			$this->assertTrue( array() !== $sqlite->createTable( $table, 'wp_' ) );
		}
	}

	public function testMoneyColumnsAreIntegers(): void {
		foreach ( Tables::all() as $table ) {
			foreach ( $table->allColumns() as $column ) {
				if ( 'money' === $column['type'] ) {
					// Typ `money` mapuje się na INT w obu gramatykach — kwoty
					// trzymamy w groszach, nigdy w liczbach zmiennoprzecinkowych.
					$sql = ( new MySqlGrammar() )->createTable( $table, '' )[0];
					$this->assertTrue(
						str_contains( $sql, sprintf( '`%s` INT', $column['name'] ) ),
						sprintf( 'Kolumna %s.%s nie jest typu całkowitego.', $table->name, $column['name'] )
					);
				}
			}
		}
	}
}
