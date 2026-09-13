<?php
/**
 * Podgląd galerii klienta w trzech motywach.
 *
 * Markup pochodzi z produkcyjnej klasy `GalleryMarkup`, style z `gallery.css`,
 * a lightbox z `assets/js/gallery/gallery.js`. Podstawione są wyłącznie
 * zdjęcia: zamiast prawdziwych kadrów idą generowane SVG w adresie `data:`,
 * bo podgląd ma pokazywać zachowanie galerii, a nie czyjeś zdjęcia.
 *
 * Użycie:  php tools/preview-gallery.php  →  dist/preview/galeria-*.html
 */

declare( strict_types=1 );

$root = dirname( __DIR__ );

require_once "$root/tools/preview-build.php";

kadr_preview_wp_stubs( $root );

/** Kolory kadrów — dobrane tak, żeby było widać rytm serii, nie żeby ładnie wyglądać. */
$palette = array( '#2b3440', '#3a2f3d', '#2e3b35', '#3d352b', '#2a3242', '#382c3a', '#333b44', '#43372f' );

/** Proporcje mieszane: pion i poziom, bo tak wygląda prawdziwa sesja. */
$shapes = array( array( 1600, 1067 ), array( 1067, 1600 ), array( 1600, 1067 ), array( 1600, 1200 ), array( 1067, 1600 ), array( 1600, 1067 ) );

$frame = static function ( int $index, int $width, int $height, string $colour ): string {
	$svg = sprintf(
		'<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d"><rect width="%d" height="%d" fill="%s"/>'
		. '<text x="%d" y="%d" fill="rgba(255,255,255,0.35)" font-family="monospace" font-size="%d" text-anchor="middle">%04d</text></svg>',
		$width,
		$height,
		$width,
		$height,
		$colour,
		(int) ( $width / 2 ),
		(int) ( $height / 2 ),
		(int) ( $width / 14 ),
		$index + 1
	);

	return 'data:image/svg+xml;utf8,' . rawurlencode( $svg );
};

$photos = array();

for ( $index = 0; $index < 24; $index++ ) {
	[$width, $height] = $shapes[ $index % count( $shapes ) ];
	$colour           = $palette[ $index % count( $palette ) ];

	$photos[] = array(
		'id'     => sprintf( '01JG%022d', $index ),
		'src'    => $frame( $index, $width, $height, $colour ),
		'full'     => $frame( $index, $width, $height, $colour ),
		// Adres pobrania w podglądzie jest pozorowany — pokazuje kształt
		// odpowiedzi, nie prawdziwy plik.
		'download' => sprintf( '/g/podglad/d/01JG%022d', $index ),
		// Miniatura zastępcza: jednolity kolor kadru, kilkadziesiąt bajtów.
		'lqip'   => 'data:image/svg+xml;utf8,' . rawurlencode(
			sprintf( '<svg xmlns="http://www.w3.org/2000/svg" width="4" height="3"><rect width="4" height="3" fill="%s"/></svg>', $colour )
		),
		'width'  => $width,
		'height' => $height,
		'alt'    => '',
	);
}

$markup = new \Kadr\Presentation\Client\GalleryMarkup();
$studio = array( 'name' => 'Studio Przykładowe', 'logo' => null, 'footer' => 'Studio Przykładowe · podgląd' );

$gallery = array(
	'title' => 'Ślub Marty i Piotra',
	'intro' => 'Wybierz zdjęcia, które mamy obrobić. Pakiet obejmuje 60 kadrów — resztę możesz dokupić przy zatwierdzeniu wyboru.',
	'theme' => 'noir',
	'count' => 842,
	'cover' => array( 'src' => $frame( 0, 2400, 1350, '#20242c' ), 'width' => 2400, 'height' => 1350 ),
);

$css = file_get_contents( "$root/assets/css/tokens.css" ) . "\n" . file_get_contents( "$root/assets/css/gallery.css" );
$js  = file_get_contents( "$root/assets/js/gallery/gallery.js" );
$net = file_get_contents( "$root/tools/preview-selection-net.js" );

/*
 * Wybór zdjęć — stan początkowy dla podglądu.
 *
 * Pakiet 20, cena 60 zł, pięć kadrów już zaznaczonych: tyle, żeby było widać
 * licznik przed przekroczeniem pakietu i po nim.
 */
$selection = array(
	'status' => 'open',
	'states' => array(
		$photos[0]['id'] => 'selected',
		$photos[1]['id'] => 'selected',
		$photos[2]['id'] => 'favorite',
		$photos[3]['id'] => 'selected',
		$photos[5]['id'] => 'selected',
	),
	'tally'  => array(
		'selected'      => 4,
		'package_limit' => 20,
		'included'      => 4,
		'extra'         => 0,
		'unit_price'    => 6000,
		'total'         => 0,
		'remaining'     => 16,
		'at_limit'      => false,
		'needs_payment' => false,
	),
	'favorites' => 1,
	'rejected'  => 0,
);

/** Wybór po przekroczeniu pakietu — 28 zdjęć przy pakiecie 20. */
$overLimit = $selection;
$overLimit['tally'] = array(
	'selected'      => 28,
	'package_limit' => 20,
	'included'      => 20,
	'extra'         => 8,
	'unit_price'    => 6000,
	'total'         => 48000,
	'remaining'     => 0,
	'at_limit'      => false,
	'needs_payment' => true,
);

/** Strony podglądu: nazwa pliku => [motyw, treść]. */
$pages = array(
	// Noir bez pobierania (proofing), Paper z pobieraniem (galeria po dostawie).
	'galeria-noir'    => array( 'noir', $markup->page( $gallery, $photos, $studio, true, false ) ),
	'galeria-paper'   => array( 'paper', $markup->page( array_merge( $gallery, array( 'theme' => 'paper' ) ), $photos, $studio, true, true ) ),
	'galeria-minimal' => array( 'minimal', $markup->page( array_merge( $gallery, array( 'theme' => 'minimal' ) ), $photos, $studio, true, false ) ),
	'galeria-wybor'   => array( 'noir', $markup->page( $gallery, $photos, $studio, true, false, $selection ) ),
	'galeria-doplata' => array( 'paper', $markup->page( array_merge( $gallery, array( 'theme' => 'paper' ) ), $photos, $studio, true, false, $overLimit ) ),
	'galeria-wyslany' => array( 'noir', $markup->page( $gallery, $photos, $studio, true, false, array_merge( $overLimit, array( 'status' => 'submitted' ) ) ) ),
	// Etap dostawy: wybór jest zamknięty, paczka gotowa, klientka wraca
	// po pliki. To jest moment, o którym opowiada znajomym.
	'galeria-pliki'   => array( 'noir', $markup->page( $gallery, $photos, $studio, true, true, null, true ) ),
	'galeria-pin'     => array( 'noir', $markup->gate( $studio ) ),
	'galeria-pin-blad' => array( 'noir', $markup->gate( $studio, 'Nieprawidłowy PIN.' ) ),
	'galeria-koniec'  => array( 'paper', $markup->unavailable() ),
);

@mkdir( "$root/dist/preview", 0o755, true );

foreach ( $pages as $file => [$theme, $body] ) {
	$html = <<<HTML
<!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="{$theme}">
<title>Ślub Marty i Piotra — podgląd</title>
<style>
$css
</style>
</head>
<body class="kadr-gallery" data-theme="$theme">
$body
<script>
$net
</script>
<script type="module">
$js
</script>
</body>
</html>
HTML;

	file_put_contents( "$root/dist/preview/$file.html", $html );

	printf( "\033[32mdist/preview/%s.html (%s KB)\033[0m\n", $file, number_format( strlen( $html ) / 1024, 1 ) );
}
