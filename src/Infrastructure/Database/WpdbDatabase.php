<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database;

use Kadr\Domain\Persistence\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Implementacja oparta o `$wpdb` — używana w runtimie wtyczki.
 *
 * Zapytania przychodzą ze znakami `?`, a adapter zamienia je na zastępniki
 * `$wpdb` (`%d`, `%f`, `%s`) dobrane po typie wartości i przepuszcza całość
 * przez `$wpdb->prepare()`. To jest dokładnie ta sama ochrona, co natywne
 * przygotowanie zapytania — nigdzie nie sklejamy SQL-a z wartościami.
 */
final class WpdbDatabase implements Database {

	public function __construct( private readonly \wpdb $wpdb ) {}

	public function selectOne( string $sql, array $params = array() ): ?array {
		$row = $this->wpdb->get_row( $this->prepare( $sql, $params ), ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	public function selectAll( string $sql, array $params = array() ): array {
		$rows = $this->wpdb->get_results( $this->prepare( $sql, $params ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	public function selectValue( string $sql, array $params = array() ): mixed {
		return $this->wpdb->get_var( $this->prepare( $sql, $params ) );
	}

	public function execute( string $sql, array $params = array() ): int {
		$result = $this->wpdb->query( $this->prepare( $sql, $params ) );

		return is_int( $result ) ? $result : 0;
	}

	public function insert( string $table, array $data ): int {
		$columns      = array_keys( $data );
		$placeholders = implode( ', ', array_map( fn( $value ): string => $this->placeholderFor( $value ), array_values( $data ) ) );
		$columnList   = implode( ', ', array_map( static fn( string $c ): string => "`$c`", $columns ) );

		$sql = sprintf( 'INSERT INTO `%s` (%s) VALUES (%s)', $table, $columnList, $placeholders );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- nazwy kolumn pochodzą z kodu, wartości przez prepare().
		$this->wpdb->query( $this->wpdb->prepare( $sql, ...array_values( $data ) ) );

		return (int) $this->wpdb->insert_id;
	}

	public function table( string $base ): string {
		return $this->wpdb->prefix . $base;
	}

	/**
	 * @param list<scalar|null> $params
	 */
	private function prepare( string $sql, array $params ): string {
		if ( array() === $params ) {
			return $sql;
		}

		$index    = 0;
		$prepared = preg_replace_callback(
			'/\?/',
			function () use ( $params, &$index ): string {
				$value = $params[ $index ] ?? null;
				++$index;

				return $this->placeholderFor( $value );
			},
			$sql
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- zastępniki wygenerowane wyżej, wartości przekazane osobno.
		return (string) $this->wpdb->prepare( (string) $prepared, ...$params );
	}

	private function placeholderFor( mixed $value ): string {
		return match ( true ) {
			is_int( $value )   => '%d',
			is_float( $value ) => '%f',
			default            => '%s',
		};
	}
}
