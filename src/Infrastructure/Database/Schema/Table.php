<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database\Schema;

/**
 * Deklaratywna definicja tabeli.
 *
 * Po co własny opis zamiast surowego SQL-a w migracjach: ten sam opis
 * generuje DDL dla MySQL (produkcja) i dla SQLite (testy), więc testy
 * izolacji tenantów wykonują prawdziwe zapytania na dokładnie tych samych
 * kolumnach co produkcja. Atrapa `$wpdb` udowodniłaby tylko tyle,
 * że atrapa działa.
 *
 * Druga korzyść: konwencje z docs/DATABASE.md §2 są wymuszone konstrukcyjnie,
 * a nie pamięcią autora migracji — `tenantScoped()` sam dokłada `tenant_id`
 * i indeks zaczynający się od tenanta.
 */
final class Table {

	/** @var list<array{name: string, type: string, null: bool, default: mixed}> */
	private array $columns = array();

	/** @var list<array{name: string, columns: list<string>, unique: bool}> */
	private array $indexes = array();

	private bool $tenantScoped = false;
	private bool $crossTenantReads = false;
	private string $crossTenantReason = '';

	private function __construct( public readonly string $name ) {}

	public static function named( string $name ): self {
		return new self( $name );
	}

	/**
	 * Encja należąca do tenanta: dokłada `tenant_id` i jego indeks.
	 */
	public function tenantScoped(): self {
		$this->tenantScoped = true;

		return $this;
	}

	/**
	 * Tabela jest CELOWO odpytywana ponad tenantami przez proces działający
	 * poza kontekstem tenanta — np. worker kolejki pobierający kolejne zadania
	 * ze wszystkich tenantów naraz.
	 *
	 * Deklaracja jest jawna i wymaga uzasadnienia, bo zwalnia tabelę z reguły
	 * „każdy indeks zaczyna się od tenant_id”. Nie zwalnia jej z posiadania
	 * kolumny `tenant_id` — wiersze nadal należą do konkretnego tenanta,
	 * a repozytoria nadal je po nim filtrują.
	 */
	public function crossTenantReads( string $reason ): self {
		$this->crossTenantReads  = true;
		$this->crossTenantReason = $reason;

		return $this;
	}

	public function hasCrossTenantReads(): bool {
		return $this->crossTenantReads;
	}

	public function crossTenantReason(): string {
		return $this->crossTenantReason;
	}

	public function ulid( string $name = 'public_id' ): self {
		return $this->column( $name, 'ulid' )->unique( $name );
	}

	public function string( string $name, int $length = 191, bool $null = false, mixed $default = null ): self {
		return $this->column( $name, "string:$length", $null, $default );
	}

	public function text( string $name, bool $null = true ): self {
		return $this->column( $name, 'text', $null );
	}

	public function json( string $name, bool $null = true ): self {
		return $this->column( $name, 'json', $null );
	}

	public function int( string $name, bool $null = false, ?int $default = 0 ): self {
		return $this->column( $name, 'int', $null, $default );
	}

	public function bigint( string $name, bool $null = false, ?int $default = 0 ): self {
		return $this->column( $name, 'bigint', $null, $default );
	}

	public function bool( string $name, bool $default = false ): self {
		return $this->column( $name, 'bool', false, $default ? 1 : 0 );
	}

	/** Kwota w groszach. Nigdy float (docs/DATABASE.md §2). */
	public function money( string $name, bool $null = false ): self {
		return $this->column( $name, 'money', $null, $null ? null : 0 );
	}

	public function datetime( string $name, bool $null = true ): self {
		return $this->column( $name, 'datetime', $null );
	}

	/**
	 * Odwołanie do innej encji.
	 *
	 * Świadomie bez więzów FOREIGN KEY: usuwanie danych przebiega u nas
	 * przez miękkie usuwanie i kolejkę zadań, a twarde więzy zamieniłyby
	 * pojedynczą pomyłkę w kaskadowe usunięcie zdjęć klienta.
	 */
	public function reference( string $name, bool $null = false ): self {
		return $this->column( $name, 'bigint', $null, $null ? null : 0 );
	}

	public function index( string ...$columns ): self {
		$this->indexes[] = array(
			'name'    => implode( '_', $columns ),
			'columns' => array_values( $columns ),
			'unique'  => false,
		);

		return $this;
	}

	public function unique( string ...$columns ): self {
		$this->indexes[] = array(
			'name'    => 'uniq_' . implode( '_', $columns ),
			'columns' => array_values( $columns ),
			'unique'  => true,
		);

		return $this;
	}

	public function timestamps( bool $softDelete = true ): self {
		$this->column( 'created_at', 'datetime', false );
		$this->column( 'updated_at', 'datetime', false );

		if ( $softDelete ) {
			$this->column( 'deleted_at', 'datetime', true );
		}

		return $this;
	}

	/**
	 * @return list<array{name: string, type: string, null: bool, default: mixed}>
	 */
	public function allColumns(): array {
		$columns = array( array( 'name' => 'id', 'type' => 'id', 'null' => false, 'default' => null ) );

		if ( $this->tenantScoped ) {
			$columns[] = array( 'name' => 'tenant_id', 'type' => 'bigint', 'null' => false, 'default' => null );
		}

		return array_merge( $columns, $this->columns );
	}

	/**
	 * @return list<array{name: string, columns: list<string>, unique: bool}>
	 */
	public function allIndexes(): array {
		$indexes = array();

		if ( $this->tenantScoped ) {
			$indexes[] = array( 'name' => 'tenant', 'columns' => array( 'tenant_id' ), 'unique' => false );
		}

		return array_merge( $indexes, $this->indexes );
	}

	public function isTenantScoped(): bool {
		return $this->tenantScoped;
	}

	/**
	 * @return list<string>
	 */
	public function columnNames(): array {
		return array_map(
			static fn( array $column ): string => $column['name'],
			$this->allColumns()
		);
	}

	private function column( string $name, string $type, bool $null = false, mixed $default = null ): self {
		$this->columns[] = array(
			'name'    => $name,
			'type'    => $type,
			'null'    => $null,
			'default' => $default,
		);

		return $this;
	}
}
