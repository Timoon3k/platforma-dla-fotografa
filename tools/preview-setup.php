<?php
/**
 * Podgląd ekranu pierwszego uruchomienia.
 *
 * Ten ekran żyje w kokpicie WordPressa, którego w tym środowisku nie ma
 * (kwestia O9). Harness podstawia garść funkcji WordPressa i renderuje
 * PRODUKCYJNĄ klasę `SetupPage` w trzech stanach, w jakich administrator
 * faktycznie ją zobaczy: świeża instalacja, zepsute odnośniki, wszystko gra.
 *
 * Sens jest ten sam, co przy `preview-app.php`: nie istnieje druga wersja
 * tego ekranu. To, co tu widać, to ten sam kod, który pójdzie na produkcję.
 *
 * Użycie:  php tools/preview-setup.php
 */

declare( strict_types=1 );

$root = dirname( __DIR__ );

require_once "$root/tools/preview-build.php";

kadr_preview_wp_stubs( $root );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/' );
}

/* --- Namiastki WordPressa potrzebne temu ekranowi ------------------- */

$GLOBALS['kadr_preview_options'] = array();

function current_user_can( $capability ) { return true; }
function admin_url( $path = '' ) { return 'https://studio.example/wp-admin/' . ltrim( (string) $path, '/' ); }
function home_url( $path = '' ) { return 'https://studio.example/' . ltrim( (string) $path, '/' ); }
function get_option( $name, $default = false ) { return $GLOBALS['kadr_preview_options'][ $name ] ?? $default; }
function update_option( $name, $value, $autoload = null ) { $GLOBALS['kadr_preview_options'][ $name ] = $value; return true; }
function get_post_field( $field, $id ) { return $GLOBALS['kadr_preview_options']['__front_content'] ?? ''; }
function wp_nonce_field( $action, $name = '_wpnonce', $referer = true, $echo = true ) { return '<input type="hidden" name="_wpnonce" value="podglad" />'; }
function get_current_screen() { return null; }
function is_wp_error( $thing ) { return false; }
function wp_kses_post( $html ) { return $html; }
function wp_get_upload_dir() { return array( 'basedir' => sys_get_temp_dir() . '/kadr-podglad/uploads' ); }

/* --- Trzy stany, w jakich administrator zobaczy ten ekran ----------- */

$healthy = array(
	'permalinks'         => '/%postname%/',
	'database_reachable' => true,
	'schema_current'     => true,
	'has_zip'            => true,
	'has_imagick'        => true,
	'storage_writable'   => true,
	'has_landing_page'   => true,
	'is_https'           => true,
);

$states = array(
	'setup-swieza'  => array(
		'title' => 'Świeża instalacja — brak strony głównej',
		'facts' => array( 'has_landing_page' => false ),
	),
	'setup-blokada' => array(
		'title' => 'Zwykłe odnośniki — platforma zwraca 404',
		'facts' => array(
			'permalinks'         => '',
			'database_reachable' => false,
			'schema_current'     => false,
			'storage_writable'   => false,
			'has_imagick'        => false,
			'has_landing_page'   => false,
		),
	),
	'setup-gotowe'  => array(
		'title' => 'Instalacja kompletna',
		'facts' => array(),
	),
);

@mkdir( "$root/dist/preview", 0o775, true );

foreach ( $states as $slug => $state ) {
	ob_start();
	( new \Kadr\Presentation\Admin\SetupPage( array_merge( $healthy, $state['facts'] ) ) )->render();
	$body = (string) ob_get_clean();

	file_put_contents(
		"$root/dist/preview/$slug.html",
		sprintf(
			'<!doctype html><html lang="pl"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>%s — Kadr</title>
<style>
/* Przybliżenie kokpitu WordPressa — wystarczające, żeby ocenić układ
   i hierarchię. Prawdziwe style dostarcza WordPress. */
body { margin:0; background:#f0f0f1; color:#3c434a; font:14px/1.5 -apple-system,"Segoe UI",Roboto,sans-serif; }
.wrap { max-width:60rem; margin:0 auto; padding:2rem 1.25rem 4rem; }
h1 { font-size:23px; font-weight:400; margin:0 0 1em; }
h2 { font-size:1.3em; margin:2em 0 .6em; }
.widefat { width:100%%; border:1px solid #c3c4c7; border-radius:4px; border-collapse:collapse; background:#fff; }
.widefat td { padding:.75rem 1rem; border-top:1px solid #f0f0f1; vertical-align:top; }
.widefat tr:first-child td { border-top:0; }
.striped tbody tr:nth-child(odd) { background:#fbfbfc; }
.button { display:inline-block; padding:.35rem .9rem; border:1px solid #2271b1; border-radius:3px;
  background:#f6f7f7; color:#2271b1; text-decoration:none; cursor:pointer; font-size:13px; }
.button-primary { background:#2271b1; border-color:#2271b1; color:#fff; }
.notice { padding:.75rem 1rem; border-left:4px solid #72aee6; background:#fff; margin:1rem 0;
  box-shadow:0 1px 1px rgba(0,0,0,.04); }
.notice-success { border-left-color:#00a32a; }
code { background:#f0f0f1; padding:.1em .4em; border-radius:2px; }
a { color:#2271b1; }
</style></head><body>%s</body></html>',
			htmlspecialchars( $state['title'], ENT_QUOTES, 'UTF-8' ),
			$body
		)
	);

	printf( "  %s.html — %s\n", $slug, $state['title'] );
}

echo "\033[32mPodgląd kreatora gotowy: " . count( $states ) . " ekrany.\033[0m\n";
