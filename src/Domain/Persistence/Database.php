<?php
declare( strict_types=1 );

namespace Kadr\Domain\Persistence;

/**
 * Wąski kontrakt dostępu do bazy.
 *
 * Po co, skoro WordPress ma `$wpdb`: żeby repozytoria dało się uruchomić
 * w teście na prawdziwym silniku SQL bez ładowania WordPressa. Interfejs jest
 * celowo mały — jeśli kiedyś zacznie puchnąć, to znak, że logika przecieka
 * do warstwy dostępu do danych.
 *
 * Zapytania używają znaków `?`. Implementacja odpowiada za parametryzację;
 * sklejanie SQL-a z wartościami jest niedopuszczalne (docs/SECURITY.md §1).
 */
interface Database {

	/**
	 * @param list<scalar|null> $params
	 * @return array<string, mixed>|null
	 */
	public function selectOne( string $sql, array $params = array() ): ?array;

	/**
	 * @param list<scalar|null> $params
	 * @return list<array<string, mixed>>
	 */
	public function selectAll( string $sql, array $params = array() ): array;

	/**
	 * @param list<scalar|null> $params
	 */
	public function selectValue( string $sql, array $params = array() ): mixed;

	/**
	 * @param list<scalar|null> $params
	 * @return int Liczba zmienionych wierszy.
	 */
	public function execute( string $sql, array $params = array() ): int;

	/**
	 * @param array<string, scalar|null> $data
	 * @return int Identyfikator wstawionego wiersza.
	 */
	public function insert( string $table, array $data ): int;

	/**
	 * Pełna nazwa tabeli wraz z prefiksem instalacji.
	 */
	public function table( string $base ): string;
}
