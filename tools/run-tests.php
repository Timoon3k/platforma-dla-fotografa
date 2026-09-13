<?php
/**
 * Mikro-runner testów warstwy Domain.
 *
 * Warstwa Domain nie zna WordPressa (CLAUDE.md §4) i nie ma zależności,
 * więc jej testy nie potrzebują frameworka. Ten runner działa wszędzie,
 * gdzie jest PHP — także z rozpakowanego archiwum, bez `composer install`.
 *
 * Docelowy toolchain (PHPUnit) jest zadeklarowany w composer.json i przejmie
 * te testy, gdy środowisko pozwoli na instalację zależności — patrz ADR-014.
 *
 * Użycie:  php tools/run-tests.php
 */

declare( strict_types=1 );

$root = dirname( __DIR__ );

/*
 * Klasy warstwy Presentation zaczynają się od `defined( 'ABSPATH' ) || exit;`
 * — słusznie, bo nie wolno ich wywołać przez bezpośrednie żądanie HTTP.
 * W runnerze oznaczało to jednak CICHE ZAKOŃCZENIE CAŁEGO PROCESU z kodem 0:
 * suita urywała się w połowie i meldowała sukces.
 *
 * Definiujemy więc ABSPATH tak samo, jak robią to harnessy podglądu.
 * Warstwy Domain to nie dotyczy — ona tej stałej nie używa.
 */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}

spl_autoload_register(
	static function ( string $class ) use ( $root ): void {
		foreach ( array( 'Kadr\\Tests\\' => '/tests/', 'Kadr\\' => '/src/' ) as $prefix => $dir ) {
			if ( str_starts_with( $class, $prefix ) ) {
				$path = $root . $dir . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
				if ( is_readable( $path ) ) {
					require_once $path;
				}
				return;
			}
		}
	}
);

require_once __DIR__ . '/TestCase.php';

$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/tests' ) );
$cases = array();

foreach ( $files as $file ) {
	if ( $file->isFile() && str_ends_with( (string) $file->getFilename(), 'Test.php' ) ) {
		require_once $file->getPathname();
	}
}

foreach ( get_declared_classes() as $class ) {
	if ( is_subclass_of( $class, Kadr\Tests\TestCase::class ) ) {
		$cases[] = $class;
	}
}

$passed   = 0;
$failed   = array();
$expected = 0;

foreach ( $cases as $class ) {
	foreach ( get_class_methods( $class ) as $method ) {
		if ( str_starts_with( $method, 'test' ) ) {
			++$expected;
		}
	}
}

/*
 * Zabezpieczenie przed cichym urwaniem suity.
 *
 * Jeden `exit` w ładowanym pliku (albo `die` w kodzie produkcyjnym) kończył
 * proces z kodem 0 w połowie testów — a runner nie miał jak tego zauważyć,
 * bo podsumowanie po prostu się nie wypisywało. Funkcja zamykająca sprawdza
 * teraz, czy wykonaliśmy tyle testów, ile znaleźliśmy.
 */
register_shutdown_function(
	static function () use ( &$passed, &$failed, &$expected ): void {
		if ( $passed + count( $failed ) >= $expected ) {
			return;
		}

		fwrite(
			STDERR,
			sprintf(
				"\n\033[31mSUITA URWANA: wykonano %d z %d testów.\033[0m\n"
				. "Najczęstsza przyczyna: `exit` w ładowanym pliku (np. strażnik ABSPATH).\n",
				$passed + count( $failed ),
				$expected
			)
		);

		// Kod wyjścia ustawiamy na końcu, żeby nadpisać zero z cudzego `exit`.
		exit( 1 );
	}
);

foreach ( $cases as $class ) {
	$instance = new $class();
	foreach ( get_class_methods( $instance ) as $method ) {
		if ( ! str_starts_with( $method, 'test' ) ) {
			continue;
		}
		try {
			$instance->$method();
			++$passed;
			echo "\033[32m.\033[0m";
		} catch ( Throwable $e ) {
			$failed[] = sprintf( "%s::%s\n    %s", $class, $method, $e->getMessage() );
			echo "\033[31mF\033[0m";
		}
	}
}

echo "\n\n";

if ( array() !== $failed ) {
	echo "\033[31mNIEPOWODZENIA (" . count( $failed ) . "):\033[0m\n";
	foreach ( $failed as $f ) {
		echo "  - $f\n";
	}
	echo "\n\033[31m" . count( $failed ) . " nieudanych, $passed zdanych.\033[0m\n";
	exit( 1 );
}

echo "\033[32mWszystkie testy zdane: $passed.\033[0m\n";
exit( 0 );
