<?php
/**
 * Podgląd ekranów rejestracji i logowania.
 *
 * Markup pochodzi z produkcyjnej klasy `AuthPages`, a formularz to ten sam
 * `auth.js`, który trafia do wtyczki. Podstawiona jest wyłącznie sieć:
 * wysłanie formularza odpowiada tak, jak odpowiedziałoby API.
 *
 * Użycie:  php tools/preview-auth.php  →  dist/preview/rejestracja.html
 *                                         dist/preview/logowanie.html
 */

declare( strict_types=1 );

$root = dirname( __DIR__ );

require_once "$root/tools/preview-build.php";

kadr_preview_wp_stubs( $root );

$pages = new \Kadr\Presentation\App\AuthPages();
$net   = file_get_contents( "$root/tools/preview-auth-net.js" );
$demo  = file_get_contents( "$root/tools/preview-auth-demo.js" );

$variants = array(
	'rejestracja' => true,
	'logowanie'   => false,
);

foreach ( $variants as $file => $isRegister ) {
	$body = $pages->body( $isRegister, 'podglad', '/app/', '#' );

	// Odnośnik „przełącz ekran” ma w podglądzie prowadzić do drugiego pliku.
	$body = str_replace(
		array( 'href="#logowanie"', 'href="#rejestracja"' ),
		array( 'href="logowanie.html"', 'href="rejestracja.html"' ),
		$body
	);

	kadr_preview_build(
		$root,
		$isRegister ? 'Kadr — załóż studio (podgląd)' : 'Kadr — zaloguj się (podgląd)',
		$body,
		kadr_preview_auth_modules(),
		array(
			'preview-auth-net.js' => $net,
			'demo.js'             => $demo,
		),
		'demo.js',
		"$root/dist/preview/$file.html"
	);
}
