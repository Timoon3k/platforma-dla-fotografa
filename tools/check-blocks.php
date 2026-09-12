<?php
/**
 * Walidacja spójności bloków.
 *
 * Bez działającego WordPressa nie da się wyrenderować bloku, ale da się
 * sprawdzić to, co najczęściej się rozjeżdża: atrybut używany w render.php
 * albo w editor.js, którego nie ma w block.json. Taki błąd objawia się
 * pustą sekcją na produkcji, więc łapiemy go tutaj.
 *
 * Użycie:  php tools/check-blocks.php
 */

declare( strict_types=1 );

$root   = dirname( __DIR__ );
$errors = array();
$blocks = glob( $root . '/blocks/*/block.json' ) ?: array();

if ( array() === $blocks ) {
	fwrite( STDERR, "Nie znaleziono żadnego block.json.\n" );
	exit( 1 );
}

$editor_js = (string) file_get_contents( $root . '/assets/js/editor.js' );

foreach ( $blocks as $manifest_path ) {
	$dir      = dirname( $manifest_path );
	$slug     = basename( $dir );
	$manifest = json_decode( (string) file_get_contents( $manifest_path ), true );

	if ( ! is_array( $manifest ) ) {
		$errors[] = "[$slug] block.json nie jest poprawnym JSON-em.";
		continue;
	}

	$declared = array_keys( $manifest['attributes'] ?? array() );

	// 1. Plik renderujący musi istnieć.
	$render = $manifest['render'] ?? '';
	if ( ! is_string( $render ) || ! str_starts_with( $render, 'file:./' ) ) {
		$errors[] = "[$slug] brak pola \"render\" wskazującego na plik.";
	} elseif ( ! is_readable( $dir . '/' . substr( $render, 7 ) ) ) {
		$errors[] = "[$slug] plik renderujący {$render} nie istnieje.";
	}

	// 2. Moduł widoku, jeśli zadeklarowany, musi istnieć.
	$view = $manifest['viewScriptModule'] ?? '';
	if ( is_string( $view ) && str_starts_with( $view, 'file:./' ) && ! is_readable( $dir . '/' . substr( $view, 7 ) ) ) {
		$errors[] = "[$slug] moduł widoku {$view} nie istnieje.";
	}

	// 3. Każdy $attributes['x'] w render.php musi być zadeklarowany.
	$template = (string) file_get_contents( $dir . '/render.php' );
	preg_match_all( "/\\\$attributes\\[\s*'([a-zA-Z0-9_]+)'\s*\\]/", $template, $used );

	foreach ( array_unique( $used[1] ) as $attribute ) {
		if ( ! in_array( $attribute, $declared, true ) ) {
			$errors[] = "[$slug] render.php używa atrybutu \"$attribute\", którego nie ma w block.json.";
		}
	}

	// 4. Każdy atrybut wymieniony w specyfikacji edytora musi istnieć.
	$name = (string) ( $manifest['name'] ?? '' );
	if ( preg_match( "/'" . preg_quote( $name, '/' ) . "':\s*\{(.*?)\n\t\t\},/s", $editor_js, $spec ) ) {
		preg_match_all( "/attr:\s*'([a-zA-Z0-9_]+)'/", $spec[1], $spec_attrs );
		preg_match_all( "/HEADING\(\s*'([a-zA-Z0-9_]+)'/", $spec[1], $heading_attrs );

		foreach ( array_unique( array_merge( $spec_attrs[1], $heading_attrs[1] ) ) as $attribute ) {
			if ( ! in_array( $attribute, $declared, true ) ) {
				$errors[] = "[$slug] editor.js edytuje atrybut \"$attribute\", którego nie ma w block.json.";
			}
		}
	} else {
		$errors[] = "[$slug] brak wpisu \"$name\" w specyfikacji editor.js — blok nie będzie edytowalny.";
	}

	// 5. Granice dla administratora muszą być zamknięte (CLAUDE.md §7).
	foreach ( array( 'color', 'spacing', 'typography' ) as $support ) {
		if ( ! isset( $manifest['supports'][ $support ] ) ) {
			$errors[] = "[$slug] block.json nie wyłącza swobodnych ustawień \"$support\".";
		}
	}
}

if ( array() !== $errors ) {
	fwrite( STDERR, "\033[31mNIESPÓJNOŚCI (" . count( $errors ) . "):\033[0m\n" );
	foreach ( $errors as $error ) {
		fwrite( STDERR, "  - $error\n" );
	}
	exit( 1 );
}

printf( "\033[32mBloki spójne: %d.\033[0m\n", count( $blocks ) );
exit( 0 );
