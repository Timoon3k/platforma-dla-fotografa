<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database;

use Kadr\Domain\Persistence\Database;
use Kadr\Infrastructure\Database\Schema\Grammar;

/**
 * Wykonuje migracje, których jeszcze nie zastosowano.
 *
 * Runner nigdy nie usuwa danych użytkownika. Migracja, która miałaby to zrobić,
 * wymaga osobnej, jawnej decyzji administratora (docs/DATABASE.md §8).
 */
final class MigrationRunner {

	/** @var list<Migration> */
	private array $migrations;

	/**
	 * @param list<Migration> $migrations
	 */
	public function __construct(
		private readonly Database $db,
		private readonly Grammar $grammar,
		private readonly SchemaVersion $version,
		array $migrations,
	) {
		usort( $migrations, static fn( Migration $a, Migration $b ): int => $a->version() <=> $b->version() );
		$this->migrations = $migrations;
	}

	/**
	 * @return list<string> Opisy zastosowanych migracji.
	 */
	public function run(): array {
		$current = $this->version->current();
		$applied = array();

		foreach ( $this->migrations as $migration ) {
			if ( $migration->version() <= $current ) {
				continue;
			}

			$migration->up( $this->db, $this->grammar );
			$this->version->set( $migration->version() );

			$applied[] = sprintf( '%04d — %s', $migration->version(), $migration->description() );
		}

		return $applied;
	}

	public function isUpToDate(): bool {
		return $this->version->current() >= $this->latestVersion();
	}

	public function latestVersion(): int {
		if ( array() === $this->migrations ) {
			return 0;
		}

		return $this->migrations[ count( $this->migrations ) - 1 ]->version();
	}
}
