<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database\Schema;

/**
 * Tłumaczy deklarację tabeli na DDL konkretnego silnika.
 */
interface Grammar {

	/**
	 * @return list<string> Instrukcje do wykonania po kolei.
	 */
	public function createTable( Table $table, string $prefix ): array;

	public function dropTable( Table $table, string $prefix ): string;

	/**
	 * Dołożenie kolumny do istniejącej tabeli.
	 *
	 * Definicja pochodzi z deklaracji tabeli, więc migracja nie powtarza
	 * typu — dopisanie kolumny w `Tables` i wskazanie jej nazwy tutaj
	 * wystarcza, a produkcja i testy dostają ten sam typ.
	 *
	 * @throws \InvalidArgumentException gdy kolumny nie ma w deklaracji.
	 */
	public function addColumn( Table $table, string $column, string $prefix ): string;
}
