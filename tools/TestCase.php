<?php
declare( strict_types=1 );

namespace Kadr\Tests;

/**
 * Minimalna baza testowa — tylko asercje, których faktycznie używamy.
 * Świadomie nie odtwarzamy PHPUnit; patrz nagłówek tools/run-tests.php.
 */
abstract class TestCase {

	protected function assertTrue( bool $actual, string $message = '' ): void {
		if ( true !== $actual ) {
			throw new \RuntimeException( $message ?: 'Oczekiwano true, otrzymano false.' );
		}
	}

	protected function assertFalse( bool $actual, string $message = '' ): void {
		if ( false !== $actual ) {
			throw new \RuntimeException( $message ?: 'Oczekiwano false, otrzymano true.' );
		}
	}

	protected function assertSame( mixed $expected, mixed $actual, string $message = '' ): void {
		if ( $expected !== $actual ) {
			throw new \RuntimeException(
				$message ?: sprintf(
					'Oczekiwano %s, otrzymano %s.',
					var_export( $expected, true ),
					var_export( $actual, true )
				)
			);
		}
	}

	protected function assertNull( mixed $actual, string $message = '' ): void {
		if ( null !== $actual ) {
			throw new \RuntimeException( $message ?: 'Oczekiwano null.' );
		}
	}

	protected function assertNotNull( mixed $actual, string $message = '' ): void {
		if ( null === $actual ) {
			throw new \RuntimeException( '' !== $message ? $message : 'Oczekiwano wartości, dostaliśmy null.' );
		}
	}

	protected function assertThrows( string $exception, callable $fn, string $message = '' ): void {
		try {
			$fn();
		} catch ( \Throwable $e ) {
			if ( $e instanceof $exception ) {
				return;
			}
			throw new \RuntimeException( sprintf( 'Oczekiwano %s, otrzymano %s.', $exception, $e::class ) );
		}
		throw new \RuntimeException( $message ?: sprintf( 'Oczekiwano wyjątku %s, nie został rzucony.', $exception ) );
	}
}
