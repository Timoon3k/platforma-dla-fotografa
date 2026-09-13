<?php
declare( strict_types=1 );

namespace Kadr\Tests\Support;

use Kadr\Domain\Tenancy\Role;
use Kadr\Domain\Tenancy\TenantContext;
use Kadr\Domain\Tenancy\TenantId;
use Kadr\Infrastructure\Database\InMemorySchemaVersion;
use Kadr\Infrastructure\Database\MigrationRunner;
use Kadr\Infrastructure\Database\Migrations\Migration0001Core;
use Kadr\Infrastructure\Database\Migrations\Migration0002Operations;
use Kadr\Infrastructure\Database\Migrations\Migration0003Lqip;
use Kadr\Infrastructure\Database\PdoDatabase;
use Kadr\Infrastructure\Database\Schema\SqliteGrammar;

/**
 * Baza testowa: SQLite w pamięci z PRAWDZIWYM schematem produkcyjnym.
 *
 * Schemat powstaje z tych samych deklaracji `Table`, z których produkcja
 * generuje DDL dla MySQL — inaczej test dowodziłby tylko tego, że atrapa
 * działa jak atrapa.
 */
final class TestDatabase {

	public static function migrated(): PdoDatabase {
		$db = PdoDatabase::sqliteInMemory();

		$runner = new MigrationRunner(
			$db,
			new SqliteGrammar(),
			new InMemorySchemaVersion(),
			array( new Migration0001Core(), new Migration0002Operations(), new Migration0003Lqip() )
		);

		$runner->run();

		return $db;
	}

	public static function tenant( int $id, Role $role = Role::Owner ): TenantContext {
		return TenantContext::for( TenantId::fromInt( $id ), 100 + $id, $role );
	}
}
