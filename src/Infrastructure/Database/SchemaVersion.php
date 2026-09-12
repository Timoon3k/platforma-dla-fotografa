<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database;

/**
 * Przechowywanie numeru wersji schematu.
 *
 * Wydzielone jako interfejs, żeby runner migracji dał się przetestować
 * bez WordPressa.
 */
interface SchemaVersion {

	public function current(): int;

	public function set( int $version ): void;
}
