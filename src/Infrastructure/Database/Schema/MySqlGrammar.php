<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database\Schema;

/**
 * DDL dla MySQL 8.0 / MariaDB 10.6 — silnik produkcyjny.
 */
final readonly class MySqlGrammar implements Grammar {

	public function __construct( private string $charset = 'utf8mb4_unicode_520_ci' ) {}

	public function createTable( Table $table, string $prefix ): array {
		$lines = array();

		foreach ( $table->allColumns() as $column ) {
			$lines[] = '  ' . $this->columnSql( $column );
		}

		$lines[] = '  PRIMARY KEY (id)';

		foreach ( $table->allIndexes() as $index ) {
			$columns = implode( ', ', array_map( static fn( string $c ): string => "`$c`", $index['columns'] ) );
			$lines[] = sprintf(
				'  %s KEY `%s` (%s)',
				$index['unique'] ? 'UNIQUE' : '',
				$index['name'],
				$columns
			);
		}

		return array(
			sprintf(
				"CREATE TABLE IF NOT EXISTS `%s%s` (\n%s\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=%s",
				$prefix,
				$table->name,
				implode( ",\n", $lines ),
				$this->charset
			),
		);
	}

	public function dropTable( Table $table, string $prefix ): string {
		return sprintf( 'DROP TABLE IF EXISTS `%s%s`', $prefix, $table->name );
	}

	public function addColumn( Table $table, string $column, string $prefix ): string {
		return sprintf(
			'ALTER TABLE `%s%s` ADD COLUMN %s',
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
		$type = match ( true ) {
			'id' === $column['type']                  => 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
			'ulid' === $column['type']                => 'CHAR(26) NOT NULL',
			'bigint' === $column['type']              => 'BIGINT UNSIGNED',
			'int' === $column['type']                 => 'INT',
			'money' === $column['type']               => 'INT',
			'bool' === $column['type']                => 'TINYINT(1)',
			'text' === $column['type']                => 'TEXT',
			'json' === $column['type']                => 'LONGTEXT',
			'datetime' === $column['type']            => 'DATETIME',
			str_starts_with( $column['type'], 'string:' ) => 'VARCHAR(' . substr( $column['type'], 7 ) . ')',
			default                                   => throw new \InvalidArgumentException( "Nieznany typ kolumny: {$column['type']}" ),
		};

		if ( 'id' === $column['type'] ) {
			return "`{$column['name']}` $type";
		}

		$sql = "`{$column['name']}` $type " . ( $column['null'] ? 'NULL' : 'NOT NULL' );

		if ( null !== $column['default'] ) {
			$sql .= is_int( $column['default'] ) ? " DEFAULT {$column['default']}" : " DEFAULT '{$column['default']}'";
		}

		return $sql;
	}
}
