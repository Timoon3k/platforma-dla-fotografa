<?php
/**
 * Harness renderujący bloki poza WordPressem.
 *
 * W środowisku, w którym powstaje ten kod, nie da się uruchomić WordPressa
 * (brak dostępu do wordpress.org, brak serwera MySQL). Bez czegoś takiego
 * szablony bloków byłyby pisane całkowicie na wiarę.
 *
 * Harness podstawia minimalny zestaw funkcji WordPressa używanych przez
 * render.php i składa stronę z prawdziwymi arkuszami stylów. To NIE jest
 * substytut testu w WordPressie — nie sprawdza rejestracji bloków, edytora,
 * Interactivity API ani zapytań do bazy. Sprawdza to, co potrafi sprawdzić:
 * czy szablony w ogóle się wykonują i jaki HTML produkują.
 *
 * Użycie:  php tools/preview.php  →  dist/preview/index.html
 */

declare( strict_types=1 );

define( 'ABSPATH', __DIR__ );

$root = dirname( __DIR__ );

spl_autoload_register(
	static function ( string $class ) use ( $root ): void {
		$prefix = 'Kadr\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}
		$path = $root . '/src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

/* ---------------------------------------------------------------------
 * Namiastki funkcji WordPressa
 * ------------------------------------------------------------------- */

function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $text, $domain = null ) { return esc_html( $text ); }
function esc_attr__( $text, $domain = null ) { return esc_attr( $text ); }
function esc_html_e( $text, $domain = null ) { echo esc_html( $text ); }
function esc_attr_e( $text, $domain = null ) { echo esc_attr( $text ); }
function __( $text, $domain = null ) { return $text; }
function _e( $text, $domain = null ) { echo $text; }
function _n( $single, $plural, $number, $domain = null ) {
	// Uproszczona polska liczba mnoga — wystarczająca dla podglądu.
	if ( 1 === $number ) {
		return $single;
	}
	return $plural;
}
function number_format_i18n( $number, $decimals = 0 ) {
	return number_format( (float) $number, (int) $decimals, ',', ' ' );
}
function wp_kses( $html, $allowed ) { return $html; }
function wp_kses_post( $html ) { return $html; }
function wp_kses_data( $html ) { return $html; }
function wp_json_encode( $data ) { return json_encode( $data, JSON_UNESCAPED_UNICODE ); }
function checked( $a, $b = true, $echo = true ) { $r = $a == $b ? ' checked' : ''; if ( $echo ) { echo $r; } return $r; }
function disabled( $a, $b = true, $echo = true ) { $r = $a == $b ? ' disabled' : ''; if ( $echo ) { echo $r; } return $r; }

function get_block_wrapper_attributes( $extra = array() ) {
	$class = $extra['class'] ?? '';
	return 'class="' . esc_attr( $class ) . '"';
}

function wp_get_attachment_image( $id, $size = 'full', $icon = false, $attr = array() ) {
	// Brak biblioteki mediów — zwracamy pusty ciąg, żeby zadziałała ścieżka
	// zapasowa szablonu (jawnie oznaczony slot).
	return '';
}

function wp_interactivity_data_wp_context( $context ) {
	return "data-wp-context='" . esc_attr( wp_json_encode( $context ) ) . "'";
}

/* ---------------------------------------------------------------------
 * Renderowanie
 * ------------------------------------------------------------------- */

/**
 * Renderuje jeden blok z jego domyślnymi atrybutami z block.json.
 *
 * @param array<string, mixed> $overrides
 */
function kadr_render_block( string $slug, array $overrides = array() ): string {
	global $kadr_root;

	$manifest = json_decode( (string) file_get_contents( "$kadr_root/blocks/$slug/block.json" ), true );
	$defaults = array();

	foreach ( $manifest['attributes'] ?? array() as $key => $definition ) {
		$defaults[ $key ] = $definition['default'] ?? null;
	}

	$attributes = array_merge( $defaults, $overrides );

	ob_start();
	include "$kadr_root/blocks/$slug/render.php";
	return (string) ob_get_clean();
}

$kadr_root = $root;

$sections = array(
	array( 'hero' ),
	array( 'problem' ),
	array( 'journey' ),
	array( 'feature' ),
	array( 'proof' ),
	array(
		'feature',
		array(
			'eyebrow'   => 'Odbitki',
			'number'    => '05',
			'title'     => 'Odbitki zamawiane bez wychodzenia z galerii',
			'body'      => 'Klient widzi, jak jego kadr zmieści się w danym formacie, zanim zamówi. Zdjęcie 3:2 w formacie 13×18 zostanie przycięte — lepiej, żeby zobaczył to teraz niż po wywołaniu.',
			'mediaSide' => 'left',
			'bullets'   => array(
				array( 'text' => 'Własne formaty, papiery i ceny — nic nie jest zakodowane na sztywno' ),
				array( 'text' => 'Podgląd kadrowania dla każdego formatu' ),
				array( 'text' => 'Płatność trafia prosto na Twoje konto' ),
			),
		),
	),
	array( 'pricing' ),
	array( 'faq' ),
	array( 'testimonials' ),
	array( 'cta' ),
);

$body   = '';
$errors = array();

foreach ( $sections as $section ) {
	$slug = $section[0];
	try {
		$html = kadr_render_block( $slug, $section[1] ?? array() );
		if ( '' === trim( $html ) ) {
			$errors[] = "Blok \"$slug\" wyrenderował pusty wynik.";
		}
		$body .= $html . "\n";
	} catch ( Throwable $e ) {
		$errors[] = "Blok \"$slug\" rzucił wyjątek: " . $e->getMessage();
	}
}

$css = '';
foreach ( array( 'tokens', 'base', 'components', 'motion', 'marketing' ) as $sheet ) {
	$css .= file_get_contents( "$root/assets/css/$sheet.css" ) . "\n";
}

$js = file_get_contents( "$root/assets/js/motion.js" );

$out = <<<HTML
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kadr — podgląd strony głównej</title>
<style>
$css
</style>
</head>
<body class="kadr">
<a class="kadr-skip-link" href="#tresc">Przejdź do treści</a>
<main id="tresc">
$body
</main>
<script>
$js
</script>
</body>
</html>
HTML;

@mkdir( "$root/dist/preview", 0755, true );
file_put_contents( "$root/dist/preview/index.html", $out );

if ( array() !== $errors ) {
	fwrite( STDERR, "\033[31mBŁĘDY RENDEROWANIA (" . count( $errors ) . "):\033[0m\n" );
	foreach ( $errors as $error ) {
		fwrite( STDERR, "  - $error\n" );
	}
	exit( 1 );
}

printf(
	"\033[32mPodgląd zapisany: dist/preview/index.html (%d sekcji, %s)\033[0m\n",
	count( $sections ),
	size_format_local( strlen( $out ) )
);

function size_format_local( int $bytes ): string {
	return $bytes > 1024 ? round( $bytes / 1024, 1 ) . ' KB' : $bytes . ' B';
}
