<?php
/**
 * Wspólny budowniczy podglądów.
 *
 * Składa JEDEN samodzielny plik HTML, w którym prawdziwe moduły ES działają
 * z adresu `file://` — bez serwera, bez bundlera. Moduły są osadzone jako
 * bloki tekstowe, a ładowarka zamienia je na adresy blob i przepisuje między
 * nimi importy.
 *
 * Ten plik nie jest uruchamiany wprost — korzystają z niego `preview-app.php`
 * i `preview-components.php`.
 */

declare( strict_types=1 );

if ( ! function_exists( 'kadr_preview_wp_stubs' ) ) {
	/**
	 * Namiastki funkcji WordPressa używanych przez klasy renderujące.
	 *
	 * Powłoka panelu jest renderowana przez produkcyjną klasę `Shell`, więc
	 * podgląd nie może pokazać markupu, którego nie ma we wtyczce. Żadnej
	 * logiki — wyłącznie escapowanie i tłumaczenie tożsamościowe.
	 */
	function kadr_preview_wp_stubs( string $root ): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', $root );
		}

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
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
	function esc_url( $url ) { return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' ); }
	function esc_html__( $text, $domain = null ) { return esc_html( $text ); }
	function esc_attr__( $text, $domain = null ) { return esc_attr( $text ); }
	function __( $text, $domain = null ) { return $text; }
	function _n( $single, $plural, $number, $domain = null ) { return 1 === (int) $number ? $single : $plural; }
	function number_format_i18n( $number, $decimals = 0 ) { return number_format( (float) $number, (int) $decimals, ',', ' ' ); }
}

/**
 * @param array<string, string> $modules nazwa modułu => ścieżka względem repozytorium
 * @param array<string, string> $inline  nazwa modułu => kod źródłowy
 */
function kadr_preview_build(
	string $root,
	string $title,
	string $body,
	array $modules,
	array $inline,
	string $entry,
	string $outFile
): void {
	$blocks  = '';
	$sources = array();

	// Kolejność ma znaczenie: ładowarka przepisuje importy w miarę tworzenia
	// adresów blob, więc moduł musi powstać PO tych, które importuje.
	// Najpierw moduły wtyczki, potem dodatki podglądu, które z nich korzystają.
	foreach ( $modules as $name => $path ) {
		$source = file_get_contents( $root . $path );

		if ( false === $source ) {
			fwrite( STDERR, "BŁĄD: brak $path\n" );
			exit( 1 );
		}

		$sources[ $name ] = $source;
	}

	foreach ( $inline as $name => $source ) {
		$sources[ $name ] = $source;
	}

	foreach ( $sources as $name => $source ) {
		// Znacznik zamykający w treści modułu rozerwałby dokument.
		if ( str_contains( $source, '</script' ) ) {
			fwrite( STDERR, "BŁĄD: moduł $name zawiera znacznik zamykający script\n" );
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

	$safeTitle = htmlspecialchars( $title, ENT_QUOTES, 'UTF-8' );
	$safeEntry = htmlspecialchars( $entry, ENT_QUOTES, 'UTF-8' );

	$out = <<<HTML
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>$safeTitle</title>
<style>
$css
</style>
</head>
<body class="kadr kadr-app-document">
$body
$blocks
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

	Object.keys( sources ).forEach( function ( name ) {
		var resolve = function ( keyword ) {
			return function ( match, specifier ) {
				var base = specifier.split( '/' ).pop();

				return urls[ base ] ? keyword + '"' + urls[ base ] + '"' : match;
			};
		};

		var code = sources[ name ]
			.replace( /from\s*["']([^"']+)["']/g, resolve( 'from' ) )
			// Import wyłącznie dla efektu ubocznego (`import "./x.js";`) nie ma
			// słowa `from`, a to nim wchodzi atrapa sieci — musi być przepisany.
			.replace( /import\s*["']([^"']+)["']/g, resolve( 'import ' ) );

		urls[ name ] = URL.createObjectURL( new Blob( [ code ], { type: 'text/javascript' } ) );
	} );

	import( urls[ '$safeEntry' ] ).catch( function ( error ) {
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

	@mkdir( dirname( $outFile ), 0o755, true );
	file_put_contents( $outFile, $out );

	printf(
		"\033[32m%s (%d modułów, %s KB)\033[0m\n",
		str_replace( "$root/", '', $outFile ),
		count( $sources ),
		number_format( strlen( $out ) / 1024, 1 )
	);
}

/**
 * Moduły panelu w kolejności zależności.
 *
 * KOLEJNOŚĆ MA ZNACZENIE. Ładowarka podglądu tworzy adresy blob po kolei
 * i przepisuje importy tym, które już powstały — moduł musi więc stać PO
 * wszystkich, które importuje. Przestawienie dwóch pozycji kończy się
 * modułem, który po cichu nie wstaje.
 *
 * @return array<string, string>
 */
function kadr_preview_modules(): array {
	return array(
		// Biblioteki (ADR-018).
		'preact.js'       => '/assets/vendor/preact.js',
		'preact-hooks.js' => '/assets/vendor/preact-hooks.js',
		'signals-core.js' => '/assets/vendor/signals-core.js',
		'signals.js'      => '/assets/vendor/signals.js',
		'htm.js'          => '/assets/vendor/htm.js',

		// Podstawa panelu.
		'runtime.js'      => '/assets/js/app/runtime.js',
		'api.js'          => '/assets/js/app/api.js',
		'sha256.js'       => '/assets/js/app/sha256.js',

		// Komponenty.
		'toast.js'        => '/assets/js/app/toast.js',
		'dialog.js'       => '/assets/js/app/dialog.js',
		'drawer.js'       => '/assets/js/app/drawer.js',
		'form.js'         => '/assets/js/app/form.js',
		'table.js'        => '/assets/js/app/table.js',
		'palette.js'      => '/assets/js/app/palette.js',
		'upload.js'       => '/assets/js/app/upload.js',

		// Widoki — `today.js` przed pozostałymi, bo eksportuje `LoadFailure`.
		'today.js'        => '/assets/js/app/views/today.js',
		'share.js'        => '/assets/js/app/views/share.js',
		'arrange.js'      => '/assets/js/app/views/arrange.js',
		'delivery.js'     => '/assets/js/app/views/delivery.js',
		'selection.js'    => '/assets/js/app/views/selection.js',
		'gallery-form.js' => '/assets/js/app/views/gallery-form.js',
		'gallery.js'      => '/assets/js/app/views/gallery.js',
		'galleries.js'    => '/assets/js/app/views/galleries.js',
		'clients.js'      => '/assets/js/app/views/clients.js',
		'selections.js'   => '/assets/js/app/views/selections.js',

		'main.js'         => '/assets/js/app/main.js',
	);
}

/**
 * Moduły ekranów rejestracji i logowania.
 *
 * Osobny zestaw, bo te strony nie ładują panelu — nie ma czego montować,
 * zanim ktoś się zaloguje.
 *
 * @return array<string, string>
 */
function kadr_preview_auth_modules(): array {
	return array(
		'preact.js'       => '/assets/vendor/preact.js',
		'preact-hooks.js' => '/assets/vendor/preact-hooks.js',
		'signals-core.js' => '/assets/vendor/signals-core.js',
		'signals.js'      => '/assets/vendor/signals.js',
		'htm.js'          => '/assets/vendor/htm.js',
		'runtime.js'      => '/assets/js/app/runtime.js',
		'api.js'          => '/assets/js/app/api.js',
		'form.js'         => '/assets/js/app/form.js',
		'auth.js'         => '/assets/js/app/auth.js',
	);
}
