<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database;

use Kadr\Domain\Persistence\Database;
use Kadr\Infrastructure\Database\Schema\Grammar;

/**
 * Pojedyncza migracja schematu.
 *
 * Migracje są numerowane i wykonywane w kolejności. Uruchamiane wyłącznie przy
 * aktywacji, aktualizacji wtyczki i przez świadomą akcję administratora —
 * NIGDY przy każdym żądaniu (docs/DATABASE.md §8).
 */
interface Migration {

	public function version(): int;

	public function description(): string;

	public function up( Database $db, Grammar $grammar ): void;
}
