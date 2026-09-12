<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database;

use Kadr\Domain\Persistence\Database;

/**
 * Implementacja oparta o PDO.
 *
 * Używana w testach (SQLite w pamięci), dzięki czemu repozytoria są
 * sprawdzane na prawdziwym silniku SQL bez serwera MySQL i bez WordPressa.
 */
final class PdoDatabase implements Database {

	public function __construct(
		private readonly \PDO $pdo,
		private readonly string $prefix = 'wp_',
	) {
		$this->pdo->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );
		$this->pdo->setAttribute( \PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC );
	}

	public static function sqliteInMemory( string $prefix = 'wp_' ): self {
		return new self( new \PDO( 'sqlite::memory:' ), $prefix );
	}

	public function selectOne( string $sql, array $params = array() ): ?array {
		$row = $this->run( $sql, $params )->fetch();

		return false === $row ? null : $row;
	}

	public function selectAll( string $sql, array $params = array() ): array {
		return $this->run( $sql, $params )->fetchAll();
	}

	public function selectValue( string $sql, array $params = array() ): mixed {
		$value = $this->run( $sql, $params )->fetchColumn();

		return false === $value ? null : $value;
	}

	public function execute( string $sql, array $params = array() ): int {
		return $this->run( $sql, $params )->rowCount();
	}

	public function insert( string $table, array $data ): int {
		$columns      = array_keys( $data );
		$placeholders = implode( ', ', array_fill( 0, count( $columns ), '?' ) );
		$columnList   = implode( ', ', array_map( static fn( string $c ): string => "\"$c\"", $columns ) );

		$this->run(
			sprintf( 'INSERT INTO "%s" (%s) VALUES (%s)', $table, $columnList, $placeholders ),
			array_values( $data )
		);

		return (int) $this->pdo->lastInsertId();
	}

	public function table( string $base ): string {
		return $this->prefix . $base;
	}

	public function pdo(): \PDO {
		return $this->pdo;
	}

	/**
	 * @param list<scalar|null> $params
	 */
	private function run( string $sql, array $params ): \PDOStatement {
		$statement = $this->pdo->prepare( $sql );
		$statement->execute( $params );

		return $statement;
	}
}
