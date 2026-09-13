<?php
/**
 * Audyt kontrastu WCAG 2.2 AA na podstawie tokens.css.
 *
 * Czyta paletę bezpośrednio z pliku tokenów, więc nie da się zmienić koloru
 * i zapomnieć o sprawdzeniu. Uruchamiane przed każdym commitem dotykającym
 * warstwy wizualnej.
 *
 * Ten audyt wykrył dwa realne błędy przy wprowadzaniu kierunku Obsidian
 * (ADR-015): etykiety o kontraście 4,35:1 oraz biel na przycisku głównym
 * o kontraście 3,72:1 — czyli na najważniejszym elemencie strony.
 *
 * Użycie:  php tools/check-contrast.php
 */

declare( strict_types=1 );

const AA_TEXT = 4.5;   // tekst standardowy
const AA_UI   = 3.0;   // elementy interfejsu i tekst duży

$root   = dirname( __DIR__ );
$tokens = parse_tokens( (string) file_get_contents( $root . '/assets/css/tokens.css' ) );

/**
 * Pary do sprawdzenia: etykieta, kolor treści, tło, wymagany próg.
 *
 * @var list<array{0: string, 1: string, 2: string, 3: float}>
 */
$pairs = array(
	array( 'Nagłówki na bazie',            'ink', 'surface', AA_TEXT ),
	array( 'Nagłówki na powierzchni',      'ink', 'surface-raised', AA_TEXT ),
	array( 'Treść na bazie',               'ink-muted', 'surface', AA_TEXT ),
	array( 'Treść na powierzchni',         'ink-muted', 'surface-raised', AA_TEXT ),
	array( 'Treść na panelu',              'ink-muted', 'surface-overlay', AA_TEXT ),
	array( 'Etykiety na bazie',            'ink-subtle', 'surface', AA_TEXT ),
	array( 'Etykiety na powierzchni',      'ink-subtle', 'surface-raised', AA_TEXT ),
	array( 'Akcent jako tekst na bazie',   'accent', 'surface', AA_TEXT ),
	array( 'Akcent na powierzchni',        'accent', 'surface-raised', AA_TEXT ),
	array( 'Sygnał na powierzchni',        'signal', 'surface-raised', AA_TEXT ),
	array( 'Tekst przycisku głównego',     'accent-ink', 'accent-solid', AA_TEXT ),
	array( 'Tekst przycisku — hover',      'accent-ink', 'accent-solid-hover', AA_TEXT ),
	array( 'Ostrzeżenie na powierzchni',   'warning', 'surface-raised', AA_TEXT ),
	array( 'Błąd na powierzchni',          'danger', 'surface-raised', AA_TEXT ),
	// Obrysy dekoracyjne (separator, krawędź karty) NIE podlegają 1.4.11 —
	// karta jest rozpoznawalna po tle, nie po krawędzi. Sprawdzamy wyłącznie
	// obrysy, które są jedynym wyróżnikiem kontrolki.
	array( 'Obrys kontrolki na wklęsłym',  'line-control', 'surface-sunken', AA_UI ),
	array( 'Obrys kontrolki na bazie',     'line-control', 'surface', AA_UI ),
	array( 'Obrys kontrolki na powierz.',  'line-control', 'surface-raised', AA_UI ),
	array( 'Obrys fokusu na bazie',        'focus', 'surface', AA_UI ),
);

$failures = array();

printf( "Kontrast — motyw domyślny (Obsidian)\n\n" );

foreach ( $pairs as [$label, $fg, $bg, $threshold] ) {
	if ( ! isset( $tokens[ $fg ], $tokens[ $bg ] ) ) {
		$failures[] = sprintf( '%s: brak tokenu (%s lub %s)', $label, $fg, $bg );
		continue;
	}

	$background = $tokens[ $bg ];
	$foreground = flatten( $tokens[ $fg ], $background );
	$ratio      = contrast( $foreground, $background );
	$passes     = $ratio >= $threshold;

	if ( ! $passes ) {
		$failures[] = sprintf( '%s: %.2f:1, wymagane %.1f:1', $label, $ratio, $threshold );
	}

	printf( "  %s %-32s %5.2f:1  (min %.1f)\n", $passes ? "\033[32m✓\033[0m" : "\033[31m✗\033[0m", $label, $ratio, $threshold );
}

echo "\n";

if ( array() !== $failures ) {
	fwrite( STDERR, sprintf( "\033[31mKONTRAST NIEWYSTARCZAJĄCY (%d):\033[0m\n", count( $failures ) ) );
	foreach ( $failures as $failure ) {
		fwrite( STDERR, "  - $failure\n" );
	}
	exit( 1 );
}

/* ---------------------------------------------------------------------
 * Motywy galerii klienta
 *
 * Trzy motywy to trzy palety, a WCAG nie interesuje, że dwie z nich
 * są jasne. Bez tego audytu Paper i Minimal byłyby sprawdzone wyłącznie
 * okiem — czyli wcale.
 * ------------------------------------------------------------------- */

$galleryCss = (string) file_get_contents( $root . '/assets/css/gallery.css' );

/** Pary w galerii: etykieta, treść, tło, próg. */
$galleryPairs = array(
	array( 'Tytuł galerii',            'ink', 'surface', AA_TEXT ),
	array( 'Tytuł na kadrze',          'ink', 'surface-raised', AA_TEXT ),
	array( 'Opis sesji',               'ink-muted', 'surface', AA_TEXT ),
	array( 'Opis na kadrze',           'ink-muted', 'surface-raised', AA_TEXT ),
	array( 'Podpis, licznik',          'ink-subtle', 'surface', AA_TEXT ),
	array( 'Podpis na kadrze',         'ink-subtle', 'surface-raised', AA_TEXT ),
	array( 'Tekst przycisku',          'accent-ink', 'accent', AA_TEXT ),
	array( 'Obrys przycisku',          'line-strong', 'surface', AA_UI ),
	array( 'Obrys pola PIN-u',         'line-strong', 'surface-raised', AA_UI ),
	array( 'Obrys fokusu',             'accent', 'surface', AA_UI ),
);

/**
 * Okładka ze zdjęciem ma własne kolory, wspólne dla wszystkich motywów.
 *
 * Tło to przyciemnienie na nieznanym kadrze — sprawdzamy najgorszy przypadek,
 * czyli sam scrim, bo pod nim może być dowolnie jasne zdjęcie.
 */
$coverPairs = array(
	array( 'Tytuł na okładce',   'ink', AA_TEXT ),
	array( 'Opis na okładce',    'ink-muted', AA_TEXT ),
	array( 'Podpis na okładce',  'ink-subtle', AA_TEXT ),
);

$themeFailures = array();
$themeChecks   = 0;

foreach ( array( 'noir' => '.kadr-gallery {', 'paper' => '.kadr-gallery[data-theme="paper"] {', 'minimal' => '.kadr-gallery[data-theme="minimal"] {' ) as $theme => $selector ) {
	$palette = parse_block( $galleryCss, $selector, 'g' );

	printf( "\nKontrast — motyw galerii: %s\n\n", $theme );

	foreach ( $galleryPairs as [$label, $fg, $bg, $threshold] ) {
		if ( ! isset( $palette[ $fg ], $palette[ $bg ] ) ) {
			$themeFailures[] = sprintf( '%s / %s: brak tokenu (%s lub %s)', $theme, $label, $fg, $bg );
			continue;
		}

		$background = $palette[ $bg ];
		$foreground = flatten( $palette[ $fg ], $background );
		$ratio      = contrast( $foreground, $background );
		$passes     = $ratio >= $threshold;
		++$themeChecks;

		if ( ! $passes ) {
			$themeFailures[] = sprintf( '%s / %s: %.2f:1, wymagane %.1f:1', $theme, $label, $ratio, $threshold );
		}

		printf( "  %s %-32s %5.2f:1  (min %.1f)\n", $passes ? "\033[32m✓\033[0m" : "\033[31m✗\033[0m", $label, $ratio, $threshold );
	}
}

$cover = parse_block( $galleryCss, '.kadr-g-cover:not(.kadr-g-cover--plain) {', 'g' );

printf( "\nKontrast — okładka ze zdjęciem (wspólna dla motywów)\n\n" );

foreach ( $coverPairs as [$label, $fg, $threshold] ) {
	if ( ! isset( $cover[ $fg ], $cover['scrim'] ) ) {
		$themeFailures[] = sprintf( 'okładka / %s: brak tokenu', $label );
		continue;
	}

	// Najgorszy przypadek: pod przyciemnieniem leży biel.
	$background = flatten( $cover['scrim'], array( 255, 255, 255, 1.0 ) );
	$foreground = flatten( $cover[ $fg ], $background );
	$ratio      = contrast( $foreground, $background );
	$passes     = $ratio >= $threshold;
	++$themeChecks;

	if ( ! $passes ) {
		$themeFailures[] = sprintf( 'okładka / %s: %.2f:1, wymagane %.1f:1', $label, $ratio, $threshold );
	}

	printf( "  %s %-32s %5.2f:1  (min %.1f)\n", $passes ? "\033[32m✓\033[0m" : "\033[31m✗\033[0m", $label, $ratio, $threshold );
}

echo "\n";

if ( array() !== $themeFailures ) {
	fwrite( STDERR, sprintf( "\033[31mKONTRAST NIEWYSTARCZAJĄCY W MOTYWACH GALERII (%d):\033[0m\n", count( $themeFailures ) ) );
	foreach ( $themeFailures as $failure ) {
		fwrite( STDERR, "  - $failure\n" );
	}
	exit( 1 );
}

printf(
	"\033[32mWszystkie pary zdają WCAG 2.2 AA: %d (panel) + %d (trzy motywy galerii).\033[0m\n",
	count( $pairs ),
	$themeChecks
);
exit( 0 );

/**
 * Tokeny z dowolnego bloku CSS, o dowolnym przedrostku.
 *
 * @return array<string, array{0: int, 1: int, 2: int, 3: float}>
 */
function parse_block( string $css, string $selector, string $prefix ): array {
	$start = strpos( $css, $selector );

	if ( false === $start ) {
		return array();
	}

	$end   = strpos( $css, "\n}", $start );
	$block = substr( $css, $start, (int) $end - $start );

	preg_match_all( '/--' . preg_quote( $prefix, '/' ) . '-([a-z0-9-]+):\s*([^;]+);/i', $block, $matches, PREG_SET_ORDER );

	$tokens = array();

	foreach ( $matches as $match ) {
		$colour = parse_colour( trim( $match[2] ) );

		if ( null !== $colour ) {
			$tokens[ $match[1] ] = $colour;
		}
	}

	return $tokens;
}

/**
 * Wyciąga tokeny kolorów z bloku `:root` — motywy jasne mają własne bloki
 * i są sprawdzane osobno, gdy powstanie ich pełna paleta.
 *
 * @return array<string, array{0: int, 1: int, 2: int, 3: float}>
 */
function parse_tokens( string $css ): array {
	$start = strpos( $css, ':root {' );
	$end   = strpos( $css, "\n}", (int) $start );
	$root  = substr( $css, (int) $start, (int) $end - (int) $start );

	preg_match_all( '/--kadr-([a-z0-9-]+):\s*([^;]+);/i', $root, $matches, PREG_SET_ORDER );

	$tokens = array();

	foreach ( $matches as $match ) {
		$colour = parse_colour( trim( $match[2] ) );
		if ( null !== $colour ) {
			$tokens[ $match[1] ] = $colour;
		}
	}

	return $tokens;
}

/**
 * @return array{0: int, 1: int, 2: int, 3: float}|null
 */
function parse_colour( string $value ): ?array {
	if ( preg_match( '/^#([0-9a-f]{6})$/i', $value, $m ) ) {
		return array(
			hexdec( substr( $m[1], 0, 2 ) ),
			hexdec( substr( $m[1], 2, 2 ) ),
			hexdec( substr( $m[1], 4, 2 ) ),
			1.0,
		);
	}

	if ( preg_match( '~^rgba?\(\s*(\d+)[,\s]+(\d+)[,\s]+(\d+)(?:[,\s/]+([\d.]+))?\s*\)$~i', $value, $m ) ) {
		return array( (int) $m[1], (int) $m[2], (int) $m[3], isset( $m[4] ) ? (float) $m[4] : 1.0 );
	}

	return null;
}

/**
 * Nakłada kolor z przezroczystością na tło.
 *
 * @param array{0: int, 1: int, 2: int, 3: float} $colour
 * @param array{0: int, 1: int, 2: int, 3: float} $background
 * @return array{0: int, 1: int, 2: int, 3: float}
 */
function flatten( array $colour, array $background ): array {
	$alpha = $colour[3];

	return array(
		(int) round( $colour[0] * $alpha + $background[0] * ( 1 - $alpha ) ),
		(int) round( $colour[1] * $alpha + $background[1] * ( 1 - $alpha ) ),
		(int) round( $colour[2] * $alpha + $background[2] * ( 1 - $alpha ) ),
		1.0,
	);
}

/**
 * @param array{0: int, 1: int, 2: int, 3: float} $a
 * @param array{0: int, 1: int, 2: int, 3: float} $b
 */
function contrast( array $a, array $b ): float {
	$la = luminance( $a );
	$lb = luminance( $b );

	return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
}

/**
 * @param array{0: int, 1: int, 2: int, 3: float} $colour
 */
function luminance( array $colour ): float {
	$channels = array();

	foreach ( array( 0, 1, 2 ) as $i ) {
		$c            = $colour[ $i ] / 255;
		$channels[]   = $c <= 0.04045 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
	}

	return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}
