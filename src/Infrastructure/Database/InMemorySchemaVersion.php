<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database;

/**
 * Wersja schematu trzymana w pamięci — do testów.
 */
final class InMemorySchemaVersion implements SchemaVersion {

	public function __construct( private int $version = 0 ) {}

	public function current(): int {
		return $this->version;
	}

	public function set( int $version ): void {
		$this->version = $version;
	}
}
