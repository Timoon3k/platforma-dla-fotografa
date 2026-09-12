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
}
