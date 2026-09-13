<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database;

use Kadr\Domain\Persistence\Database;
use Kadr\Domain\Tenancy\TenantContext;
use Kadr\Infrastructure\Database\Schema\Table;

/**
 * Baza repozytoriów encji należących do tenanta.
 *
 * TO JEST WARSTWA, NA KTÓREJ STOI IZOLACJA DANYCH (ryzyko R2 — skutek katastrofalny).
 *
 * Zasada: nie może istnieć sposób na wykonanie zapytania bez tenanta. Realizujemy
 * ją konstrukcyjnie, a nie dyscypliną:
 *
 *  1. Klasa nie wystawia metody przyjmującej surowy SQL. Nie ma `query()`,
 *     nie ma `raw()`, nie ma `findAny()`, nie ma flagi `$ignoreTenant`.
 *  2. Wszystkie metody dostępowe są `final` — klasa potomna nie może ich obejść.
 *  3. `tenant_id` jest doklejany do KAŻDEGO warunku WHERE i nadpisywany przy
 *     każdym zapisie, niezależnie od tego, co przekazał wywołujący.
 *  4. Nazwy kolumn w warunkach są sprawdzane względem deklaracji tabeli.
 *     Klucz spoza schematu kończy się wyjątkiem, a nie zapytaniem.
 *
 * Punkt 4 jest istotny osobno: nazwy kolumn trafiają do SQL-a bez parametryzacji
 * (bo parametryzować można wartości, nie identyfikatory), więc jedyną ochroną
 * jest lista dozwolonych nazw. Deklaratywny schemat dostarcza ją za darmo.
 */
abstract class TenantRepository {

	public function __construct(
		protected readonly Database $db,
		protected readonly TenantContext $tenant,
	) {}

	/**
	 * Deklaracja tabeli — źródło nazwy i listy dozwolonych kolumn.
	 */
	abstract protected function table(): Table;

	final protected function tableName(): string {
		return $this->db->table( $this->table()->name );
	}

	final protected function hasSoftDelete(): bool {
		return in_array( 'deleted_at', $this->table()->columnNames(), true );
	}

	/**
	 * @param array<string, scalar|null> $conditions
	 * @return array<string, mixed>|null
	 */
	final protected function findOneBy( array $conditions, bool $withTrashed = false ): ?array {
		[$where, $params] = $this->buildWhere( $conditions, $withTrashed );

		return $this->db->selectOne(
			sprintf( 'SELECT * FROM %s WHERE %s LIMIT 1', $this->quote( $this->tableName() ), $where ),
			$params
		);
	}

	/**
	 * @param array<string, scalar|null> $conditions
	 * @return list<array<string, mixed>>
	 */
	final protected function findAllBy(
		array $conditions = array(),
		?string $orderColumn = null,
		string $direction = 'ASC',
		?int $limit = null,
		int $offset = 0,
		bool $withTrashed = false
	): array {
		[$where, $params] = $this->buildWhere( $conditions, $withTrashed );

		$sql = sprintf( 'SELECT * FROM %s WHERE %s', $this->quote( $this->tableName() ), $where );

		if ( null !== $orderColumn ) {
			$sql .= sprintf(
				' ORDER BY %s %s',
				$this->quote( $this->assertColumn( $orderColumn ) ),
				'DESC' === strtoupper( $direction ) ? 'DESC' : 'ASC'
			);
		}

		if ( null !== $limit ) {
			// Limit i offset są rzutowane na int, więc nie mogą przenieść SQL-a.
			$sql .= sprintf( ' LIMIT %d OFFSET %d', max( 0, $limit ), max( 0, $offset ) );
		}

		return $this->db->selectAll( $sql, $params );
	}

	/**
	 * Strona wyników z paginacją kursorową po `public_id`.
	 *
	 * Kursorem jest ULID ostatniego wiersza poprzedniej strony. Dwie korzyści
	 * naraz, obie wynikające z tego, że ULID koduje czas utworzenia:
	 *
	 *  1. **Kolejność jest ta sama, co po `created_at`**, a `public_id` jest
	 *     unikalny, więc strona nie gubi ani nie dubluje wierszy przy wstawieniu
	 *     nowego rekordu w trakcie przeglądania — czym offset grzeszy zawsze.
	 *  2. **Kursor nie zdradza sekwencyjnego `id`** (docs/SECURITY.md §1).
	 *     Nie trzeba go maskować, bo nie ma czego ukrywać.
	 *
	 * `OFFSET 50000` skanuje pięćdziesiąt tysięcy wierszy, żeby oddać
	 * dwadzieścia. Tutaj indeks ustawia się na kursorze i czyta `limit` wierszy.
	 *
	 * @param array<string, scalar|null> $conditions
	 * @return list<array<string, mixed>>
	 */
	final protected function findPageBy(
		array $conditions = array(),
		?string $cursor = null,
		int $limit = 50,
		string $direction = 'DESC',
		?array $like = null,
		bool $withTrashed = false
	): array {
		$this->assertColumn( 'public_id' );

		[$where, $params] = $this->buildWhere( $conditions, $withTrashed );

		foreach ( $like ?? array() as $column => $term ) {
			$term = trim( (string) $term );

			if ( '' === $term ) {
				continue;
			}

			// Znak ucieczki podajemy jawnie: SQLite nie ma domyślnego, a wybór
			// innego niż odwrotny ukośnik oszczędza podwójnego escapowania
			// w literale SQL-a, który MySQL i SQLite interpretują inaczej.
			$where   .= sprintf( " AND %s LIKE ? ESCAPE '!'", $this->quote( $this->assertColumn( (string) $column ) ) );
			$params[] = '%' . $this->escapeLike( $term ) . '%';
		}

		$descending = 'ASC' !== strtoupper( $direction );

		if ( null !== $cursor && '' !== $cursor ) {
			$where   .= sprintf( ' AND %s %s ?', $this->quote( 'public_id' ), $descending ? '<' : '>' );
			$params[] = $cursor;
		}

		return $this->db->selectAll(
			sprintf(
				'SELECT * FROM %s WHERE %s ORDER BY %s %s LIMIT %d',
				$this->quote( $this->tableName() ),
				$where,
				$this->quote( 'public_id' ),
				$descending ? 'DESC' : 'ASC',
				max( 1, min( 200, $limit ) )
			),
			$params
		);
	}

	/**
	 * Wiersze, których kolumna ma jedną z podanych wartości.
	 *
	 * Istnieje po to, żeby dociągnąć dane powiązane dla całej strony wyników
	 * jednym zapytaniem zamiast jednego na wiersz (docs/PERFORMANCE.md §5).
	 * Lista wartości jest parametryzowana — do SQL-a trafiają wyłącznie
	 * znaki zapytania, nigdy wartości.
	 *
	 * @param list<int|string>           $values
	 * @param array<string, scalar|null> $conditions
	 * @return list<array<string, mixed>>
	 */
	final protected function findAllIn(
		string $column,
		array $values,
		array $conditions = array(),
		?string $orderColumn = null,
		string $direction = 'ASC',
		bool $withTrashed = false
	): array {
		if ( array() === $values ) {
			return array();
		}

		[$where, $params] = $this->buildWhere( $conditions, $withTrashed );

		$placeholders = implode( ', ', array_fill( 0, count( $values ), '?' ) );
		$sql          = sprintf(
			'SELECT * FROM %s WHERE %s AND %s IN (%s)',
			$this->quote( $this->tableName() ),
			$where,
			$this->quote( $this->assertColumn( $column ) ),
			$placeholders
		);

		if ( null !== $orderColumn ) {
			$sql .= sprintf(
				' ORDER BY %s %s',
				$this->quote( $this->assertColumn( $orderColumn ) ),
				'DESC' === strtoupper( $direction ) ? 'DESC' : 'ASC'
			);
		}

		return $this->db->selectAll( $sql, array_merge( $params, array_values( $values ) ) );
	}

	/**
	 * Zliczenie z grupowaniem — jedno zapytanie zamiast pętli po wierszach.
	 *
	 * Lista galerii potrzebuje liczby zdjęć przy każdej pozycji. Zapytanie
	 * per wiersz to klasyczne N+1: przy pięćdziesięciu galeriach na stronie
	 * pięćdziesiąt jeden zapytań zamiast dwóch (docs/PERFORMANCE.md §5).
	 *
	 * @param array<string, scalar|null> $conditions
	 * @return array<string, int> wartość kolumny => liczba wierszy
	 */
	final protected function countGroupedBy( string $column, array $conditions = array(), bool $withTrashed = false ): array {
		[$where, $params] = $this->buildWhere( $conditions, $withTrashed );

		$grouped = $this->quote( $this->assertColumn( $column ) );

		$rows = $this->db->selectAll(
			sprintf(
				'SELECT %s AS grouped_value, COUNT(*) AS grouped_count FROM %s WHERE %s GROUP BY %s',
				$grouped,
				$this->quote( $this->tableName() ),
				$where,
				$grouped
			),
			$params
		);

		$counts = array();

		foreach ( $rows as $row ) {
			$counts[ (string) $row['grouped_value'] ] = (int) $row['grouped_count'];
		}

		return $counts;
	}

	/**
	 * @param array<string, scalar|null> $conditions
	 */
	final protected function countBy( array $conditions = array(), bool $withTrashed = false ): int {
		[$where, $params] = $this->buildWhere( $conditions, $withTrashed );

		return (int) $this->db->selectValue(
			sprintf( 'SELECT COUNT(*) FROM %s WHERE %s', $this->quote( $this->tableName() ), $where ),
			$params
		);
	}

	/**
	 * @param array<string, scalar|null> $data
	 * @return int Identyfikator wstawionego wiersza.
	 */
	final protected function insertRow( array $data ): int {
		foreach ( array_keys( $data ) as $column ) {
			$this->assertColumn( (string) $column );
		}

		// Nadpisujemy tenanta niezależnie od tego, co przekazał wywołujący.
		// Próba wstawienia wiersza do cudzego tenanta jest niewykonalna.
		$data['tenant_id'] = $this->tenant->id();

		$now = gmdate( 'Y-m-d H:i:s' );
		$columns = $this->table()->columnNames();

		if ( in_array( 'created_at', $columns, true ) && ! isset( $data['created_at'] ) ) {
			$data['created_at'] = $now;
		}
		if ( in_array( 'updated_at', $columns, true ) && ! isset( $data['updated_at'] ) ) {
			$data['updated_at'] = $now;
		}

		return $this->db->insert( $this->tableName(), $data );
	}

	/**
	 * @param array<string, scalar|null> $conditions
	 * @param array<string, scalar|null> $data
	 * @return int Liczba zmienionych wierszy.
	 */
	final protected function updateBy( array $conditions, array $data ): int {
		if ( array() === $data ) {
			return 0;
		}

		// tenant_id nigdy nie podlega zmianie — encja nie może zmienić właściciela.
		unset( $data['tenant_id'], $data['id'] );

		foreach ( array_keys( $data ) as $column ) {
			$this->assertColumn( (string) $column );
		}

		if ( in_array( 'updated_at', $this->table()->columnNames(), true ) ) {
			$data['updated_at'] = gmdate( 'Y-m-d H:i:s' );
		}

		$assignments = array();
		$params      = array();

		foreach ( $data as $column => $value ) {
			$assignments[] = sprintf( '%s = ?', $this->quote( (string) $column ) );
			$params[]      = $value;
		}

		[$where, $whereParams] = $this->buildWhere( $conditions, true );

		return $this->db->execute(
			sprintf(
				'UPDATE %s SET %s WHERE %s',
				$this->quote( $this->tableName() ),
				implode( ', ', $assignments ),
				$where
			),
			array_merge( $params, $whereParams )
		);
	}

	/**
	 * Miękkie usunięcie — 30-dniowy kosz (docs/DATABASE.md §7).
	 *
	 * @param array<string, scalar|null> $conditions
	 */
	final protected function softDeleteBy( array $conditions ): int {
		if ( ! $this->hasSoftDelete() ) {
			throw new \LogicException(
				sprintf( 'Tabela %s nie obsługuje miękkiego usuwania.', $this->table()->name )
			);
		}

		return $this->updateBy( $conditions, array( 'deleted_at' => gmdate( 'Y-m-d H:i:s' ) ) );
	}

	/**
	 * @param array<string, scalar|null> $conditions
	 */
	final protected function restoreBy( array $conditions ): int {
		return $this->updateBy( $conditions, array( 'deleted_at' => null ) );
	}

	/**
	 * Twarde usunięcie. Zarezerwowane dla żądań RODO i czyszczenia kosza —
	 * nigdy dla zwykłej operacji użytkownika.
	 *
	 * @param array<string, scalar|null> $conditions
	 */
	final protected function forceDeleteBy( array $conditions ): int {
		[$where, $params] = $this->buildWhere( $conditions, true );

		return $this->db->execute(
			sprintf( 'DELETE FROM %s WHERE %s', $this->quote( $this->tableName() ), $where ),
			$params
		);
	}

	/**
	 * Buduje warunek WHERE. Tenant jest doklejany zawsze i jako pierwszy.
	 *
	 * @param array<string, scalar|null> $conditions
	 * @return array{0: string, 1: list<scalar|null>}
	 */
	private function buildWhere( array $conditions, bool $withTrashed ): array {
		// Wywołujący nie ma jak nadpisać tenanta — jego wartość jest odrzucana.
		unset( $conditions['tenant_id'] );

		$clauses = array( sprintf( '%s = ?', $this->quote( 'tenant_id' ) ) );
		$params  = array( $this->tenant->id() );

		foreach ( $conditions as $column => $value ) {
			$this->assertColumn( (string) $column );

			if ( null === $value ) {
				$clauses[] = sprintf( '%s IS NULL', $this->quote( (string) $column ) );
				continue;
			}

			$clauses[] = sprintf( '%s = ?', $this->quote( (string) $column ) );
			$params[]  = $value;
		}

		if ( ! $withTrashed && $this->hasSoftDelete() ) {
			$clauses[] = sprintf( '%s IS NULL', $this->quote( 'deleted_at' ) );
		}

		return array( implode( ' AND ', $clauses ), $params );
	}

	/**
	 * Znaki wieloznaczne wpisane przez użytkownika mają być szukane dosłownie.
	 *
	 * Bez tego wpisanie `%` w wyszukiwarce zwraca wszystko, a `_` dopasowuje
	 * dowolny znak — zachowanie, którego nikt się nie spodziewa i które przy
	 * dużej tabeli potrafi wyłożyć zapytanie.
	 *
	 * Znakiem ucieczki jest `!`, a nie odwrotny ukośnik: ten drugi wymaga
	 * innego zapisu w literale MySQL-a niż w SQLite.
	 */
	private function escapeLike( string $term ): string {
		return str_replace( array( '!', '%', '_' ), array( '!!', '!%', '!_' ), $term );
	}

	/**
	 * Nazwa kolumny musi pochodzić z deklaracji tabeli.
	 *
	 * Identyfikatorów nie da się parametryzować, więc lista dozwolonych nazw
	 * jest tu jedyną ochroną przed wstrzyknięciem przez klucz tablicy.
	 *
	 * @throws \InvalidArgumentException
	 */
	private function assertColumn( string $column ): string {
		if ( ! in_array( $column, $this->table()->columnNames(), true ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Kolumna "%s" nie istnieje w tabeli %s.', $column, $this->table()->name )
			);
		}

		return $column;
	}

	/**
	 * Cytowanie identyfikatora. Podwójny cudzysłów działa w SQLite i w MySQL
	 * z trybem ANSI_QUOTES; `$wpdb` używa MySQL-a, więc adapter dostaje
	 * grawis. Nazwy pochodzą wyłącznie z listy dozwolonych (assertColumn).
	 */
	private function quote( string $identifier ): string {
		return '`' === $this->quoteChar() ? "`$identifier`" : "\"$identifier\"";
	}

	private function quoteChar(): string {
		return $this->db instanceof WpdbDatabase ? '`' : '"';
	}
}
