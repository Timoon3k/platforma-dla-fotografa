<?php
/**
 * Weryfikacja zgodności PSR-4.
 *
 * Wtyczka używa własnego autoloadera (działa bez Composera), który mapuje
 * `Kadr\Foo\Bar` na `src/Foo/Bar.php`. Rozjazd między przestrzenią nazw
 * a ścieżką objawia się dopiero jako błąd krytyczny po instalacji,
 * więc sprawdzamy go tutaj.
 *
 * Użycie:  php tools/check-autoload.php
 */

declare( strict_types=1 );

$root   = dirname( __DIR__ );
$errors = array();
$checked = 0;

$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/src' ) );

foreach ( $files as $file ) {
	if ( ! $file->isFile() || 'php' !== $file->getExtension() ) {
		continue;
	}

	++$checked;

	$path     = $file->getPathname();
	$contents = (string) file_get_contents( $path );

	if ( ! preg_match( '/^namespace\s+([^;]+);/m', $contents, $ns ) ) {
		$errors[] = sprintf( '%s: brak deklaracji namespace.', relative( $path, $root ) );
		continue;
	}

	if ( ! preg_match( '/^(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+(\w+)/m', $contents, $cls ) ) {
		$errors[] = sprintf( '%s: brak deklaracji typu.', relative( $path, $root ) );
		continue;
	}

	$expected = 'Kadr\\' . str_replace( '/', '\\', trim( dirname( relative( $path, $root . '/src' ) ), '/' ) );
	$expected = rtrim( $expected, '\\' );

	if ( trim( $ns[1] ) !== $expected ) {
		$errors[] = sprintf(
			'%s: namespace "%s", a ścieżka wskazuje "%s".',
			relative( $path, $root ),
			trim( $ns[1] ),
			$expected
		);
	}

	$basename = basename( $path, '.php' );

	if ( $cls[1] !== $basename ) {
		$errors[] = sprintf(
			'%s: typ nazywa się "%s", a plik "%s.php".',
			relative( $path, $root ),
			$cls[1],
			$basename
		);
	}
}

// Próba realnego załadowania każdej klasy tym samym autoloaderem co w runtimie.
spl_autoload_register(
	static function ( string $class ) use ( $root ): void {
		if ( ! str_starts_with( $class, 'Kadr\\' ) ) {
			return;
		}
		$path = $root . '/src/' . str_replace( '\\', '/', substr( $class, 5 ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

if ( array() !== $errors ) {
	fwrite( STDERR, sprintf( "\033[31mNIEZGODNOŚCI PSR-4 (%d):\033[0m\n", count( $errors ) ) );
	foreach ( $errors as $error ) {
		fwrite( STDERR, "  - $error\n" );
	}
	exit( 1 );
}

printf( "\033[32mPSR-4 zgodne: %d plików.\033[0m\n", $checked );
exit( 0 );

function relative( string $path, string $base ): string {
	return ltrim( str_replace( $base, '', $path ), '/' );
}
