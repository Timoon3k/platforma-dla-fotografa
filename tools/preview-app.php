<?php
/**
 * Podgląd panelu fotografa poza WordPressem.
 *
 * Generuje po jednym samodzielnym pliku HTML na sekcję panelu. To NIE jest
 * makieta: powłokę renderuje produkcyjna klasa `Shell`, widoki to produkcyjne
 * moduły, a klient REST jest ten sam, co we wtyczce. Podstawiona jest wyłącznie
 * sieć (`tools/preview-net.js`), bo tu nie ma ani WordPressa, ani bazy.
 *
 * Dane w podglądzie są jawnie oznaczone jako przykładowe (CLAUDE.md §9).
 *
 * Użycie:  php tools/preview-app.php  →  dist/preview/panel*.html
 */

declare( strict_types=1 );

$root = dirname( __DIR__ );

require_once "$root/tools/preview-build.php";

kadr_preview_wp_stubs( $root );

$shell = new \Kadr\Presentation\App\Shell();

/** Wszystkie sekcje z nawigacji — dzięki temu każdy odnośnik w podglądzie działa. */
$sections = array();

foreach ( \Kadr\Presentation\App\Shell::navigation() as $group ) {
	foreach ( array_keys( $group['items'] ) as $slug ) {
		$sections[] = (string) $slug;
	}
}

$fileFor = static fn ( string $slug ): string => '' === $slug ? 'panel.html' : "panel-$slug.html";

$net  = file_get_contents( "$root/tools/preview-net.js" );
$demo = file_get_contents( "$root/tools/preview-app-demo.js" );

foreach ( $sections as $section ) {
	// Powłoka buduje odnośniki z podanej bazy; w podglądzie zamieniamy je
	// na nazwy wygenerowanych plików, żeby nawigacja działała z `file://`.
	$body = $shell->body( $section, 'Studio Przykładowe (podgląd)', '#', '#' );

	foreach ( $sections as $target ) {
		$body = str_replace(
			sprintf( 'href="#/%s"', $target ),
			sprintf( 'href="%s"', $fileFor( $target ) ),
			$body
		);
	}

	kadr_preview_build(
		$root,
		'Kadr — panel fotografa (podgląd)',
		$body,
		kadr_preview_modules(),
		array(
			'preview-net.js' => $net,
			'demo.js'        => $demo,
		),
		'demo.js',
		"$root/dist/preview/" . $fileFor( $section )
	);
}

/*
 * Widok jednej galerii: wysyłanie zdjęć i siatka kadrów.
 *
 * Identyfikator w `data-resource` wybiera widok pojedynczej galerii —
 * dokładnie tak, jak robi to trasa `/app/galerie/{ULID}`.
 */
$galleryBody = $shell->body( 'galerie', 'Studio Przykładowe (podgląd)', '#', '#', '01JB0000000000000000000001' );

foreach ( $sections as $target ) {
	$galleryBody = str_replace(
		sprintf( 'href="#/%s"', $target ),
		sprintf( 'href="%s"', $fileFor( $target ) ),
		$galleryBody
	);
}

kadr_preview_build(
	$root,
	'Kadr — galeria (podgląd)',
	$galleryBody,
	kadr_preview_modules(),
	array(
		'preview-net.js' => $net,
		'demo.js'        => $demo,
	),
	'demo.js',
	"$root/dist/preview/panel-galeria.html"
);
