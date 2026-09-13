<?php
/**
 * Podgląd panelu fotografa poza WordPressem.
 *
 * Generuje JEDEN samodzielny plik HTML, w którym panel faktycznie działa:
 * paleta poleceń, dialogi, powiadomienia, sortowanie tabeli. To nie jest
 * zrzut ekranu ani makieta — to te same moduły, które trafiają do wtyczki.
 *
 * Jak to działa bez serwera i bez bundlera: moduły są osadzone w dokumencie
 * jako bloki tekstowe, a skrypt startowy zamienia je na adresy blob i
 * przepisuje między nimi importy. Dzięki temu przeglądarka ładuje prawdziwe
 * moduły ES z pliku otwartego przez `file://`.
 *
 * Dane w podglądzie są jawnie oznaczone jako przykładowe (CLAUDE.md §9).
 *
 * Użycie:  php tools/preview-app.php  →  dist/preview/panel.html
 */

declare( strict_types=1 );

$root = dirname( __DIR__ );

/** Moduły w kolejności zależności. */
$modules = array(
	'preact.js'       => '/assets/vendor/preact.js',
	'preact-hooks.js' => '/assets/vendor/preact-hooks.js',
	'signals-core.js' => '/assets/vendor/signals-core.js',
	'signals.js'      => '/assets/vendor/signals.js',
	'htm.js'          => '/assets/vendor/htm.js',
	'runtime.js'      => '/assets/js/app/runtime.js',
	'api.js'          => '/assets/js/app/api.js',
	'toast.js'        => '/assets/js/app/toast.js',
	'dialog.js'       => '/assets/js/app/dialog.js',
	'palette.js'      => '/assets/js/app/palette.js',
	'table.js'        => '/assets/js/app/table.js',
	'form.js'         => '/assets/js/app/form.js',
	'drawer.js'       => '/assets/js/app/drawer.js',
);

$blocks = '';

foreach ( $modules as $name => $path ) {
	$source = file_get_contents( $root . $path );

	if ( false === $source ) {
		fwrite( STDERR, "BŁĄD: brak $path\n" );
		exit( 1 );
	}

	if ( str_contains( $source, '</script' ) ) {
		fwrite( STDERR, "BŁĄD: $path zawiera znacznik zamykający script\n" );
		exit( 1 );
	}

	$blocks .= sprintf(
		'<script type="text/plain" data-module="%s">%s</script>' . "\n",
		htmlspecialchars( $name, ENT_QUOTES ),
		$source
	);
}

$css = '';
foreach ( array( 'tokens', 'base', 'components', 'motion', 'app' ) as $sheet ) {
	$css .= file_get_contents( "$root/assets/css/$sheet.css" ) . "\n";
}

$demo = file_get_contents( "$root/tools/preview-app-demo.js" );
$body = require "$root/tools/preview-app-markup.php";

$out = <<<HTML
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kadr — panel fotografa (podgląd)</title>
<style>
$css
</style>
</head>
<body class="kadr">
$body
$blocks
<script type="text/plain" data-module="demo.js">$demo</script>
<script>
/*
 * Ładowarka modułów dla podglądu.
 *
 * Zamienia osadzone bloki na adresy blob i przepisuje importy między nimi,
 * żeby prawdziwe moduły ES działały z pliku otwartego lokalnie.
 */
( function () {
	var sources = {};
	var urls = {};

	document.querySelectorAll( 'script[data-module]' ).forEach( function ( node ) {
		sources[ node.dataset.module ] = node.textContent;
	} );

	var order = Object.keys( sources );

	order.forEach( function ( name ) {
		var code = sources[ name ].replace(
			/from\s*["']([^"']+)["']/g,
			function ( match, specifier ) {
				var base = specifier.split( '/' ).pop();

				return urls[ base ] ? 'from"' + urls[ base ] + '"' : match;
			}
		);

		urls[ name ] = URL.createObjectURL( new Blob( [ code ], { type: 'text/javascript' } ) );
	} );

	import( urls[ 'demo.js' ] ).catch( function ( error ) {
		document.body.insertAdjacentHTML(
			'afterbegin',
			'<pre style="padding:16px;color:#ff6b5e;font:13px monospace;white-space:pre-wrap">' +
				'Podgląd nie wystartował: ' + error.message + '</pre>'
		);
	} );
} )();
</script>
</body>
</html>
HTML;

@mkdir( "$root/dist/preview", 0o755, true );
file_put_contents( "$root/dist/preview/panel.html", $out );

printf(
	"\033[32mPodgląd panelu: dist/preview/panel.html (%d modułów, %s KB)\033[0m\n",
	count( $modules ) + 1,
	number_format( strlen( $out ) / 1024, 1 )
);
