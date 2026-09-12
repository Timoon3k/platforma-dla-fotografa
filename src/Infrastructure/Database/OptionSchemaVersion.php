<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Wersja schematu w `wp_options`.
 */
final class OptionSchemaVersion implements SchemaVersion {

	public const OPTION = 'kadr_schema_version';

	public function current(): int {
		return (int) get_option( self::OPTION, 0 );
	}

	public function set( int $version ): void {
		update_option( self::OPTION, $version, false );
	}
}
