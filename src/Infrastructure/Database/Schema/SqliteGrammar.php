<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database\Schema;

/**
 * DDL dla SQLite — WYŁĄCZNIE do testów.
 *
 * Istnieje po to, żeby testy izolacji tenantów wykonywały prawdziwe zapytania
 * SQL na tych samych kolumnach co produkcja, bez serwera MySQL.
 * Nigdy nie jest używana w runtimie wtyczki.
 */
final readonly class SqliteGrammar implements Grammar {

	public function createTable( Table $table, string $prefix ): array {
		$lines = array();

		foreach ( $table->allColumns() as $column ) {
			$lines[] = '  ' . $this->columnSql( $column );
		}

		$statements = array(
			sprintf( "CREATE TABLE IF NOT EXISTS \"%s%s\" (\n%s\n)", $prefix, $table->name, implode( ",\n", $lines ) ),
		);

		// SQLite tworzy indeksy osobnymi instrukcjami.
		foreach ( $table->allIndexes() as $index ) {
			$columns      = implode( ', ', array_map( static fn( string $c ): string => "\"$c\"", $index['columns'] ) );
			$statements[] = sprintf(
				'CREATE %sINDEX IF NOT EXISTS "%s%s_%s" ON "%s%s" (%s)',
				$index['unique'] ? 'UNIQUE ' : '',
				$prefix,
				$table->name,
				$index['name'],
				$prefix,
				$table->name,
				$columns
			);
		}

		return $statements;
	}

	public function dropTable( Table $table, string $prefix ): string {
		return sprintf( 'DROP TABLE IF EXISTS "%s%s"', $prefix, $table->name );
	}

	public function addColumn( Table $table, string $column, string $prefix ): string {
		return sprintf(
			'ALTER TABLE "%s%s" ADD COLUMN %s',
			$prefix,
			$table->name,
			$this->columnSql( $this->definitionOf( $table, $column ) )
		);
	}

	/**
	 * @return array{name: string, type: string, null: bool, default: mixed}
	 */
	private function definitionOf( Table $table, string $column ): array {
		foreach ( $table->allColumns() as $definition ) {
			if ( $definition['name'] === $column ) {
				return $definition;
			}
		}

		throw new \InvalidArgumentException(
			sprintf( 'Kolumna "%s" nie istnieje w deklaracji tabeli %s.', $column, $table->name )
		);
	}

	/**
	 * @param array{name: string, type: string, null: bool, default: mixed} $column
	 */
	private function columnSql( array $column ): string {
		if ( 'id' === $column['type'] ) {
			return "\"{$column['name']}\" INTEGER PRIMARY KEY AUTOINCREMENT";
		}

		$type = match ( true ) {
			'ulid' === $column['type']                    => 'TEXT',
			in_array( $column['type'], array( 'bigint', 'int', 'money', 'bool' ), true ) => 'INTEGER',
			in_array( $column['type'], array( 'text', 'json', 'datetime' ), true )       => 'TEXT',
			str_starts_with( $column['type'], 'string:' ) => 'TEXT',
			default                                       => throw new \InvalidArgumentException( "Nieznany typ kolumny: {$column['type']}" ),
		};

		$sql = "\"{$column['name']}\" $type " . ( $column['null'] ? 'NULL' : 'NOT NULL' );

		if ( null !== $column['default'] ) {
			$sql .= is_int( $column['default'] ) ? " DEFAULT {$column['default']}" : " DEFAULT '{$column['default']}'";
		}

		return $sql;
	}
}
