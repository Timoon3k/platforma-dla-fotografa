<?php
declare( strict_types=1 );

namespace Kadr\Presentation\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Polska odmiana liczebnika.
 *
 * PO CO, SKORO WORDPRESS MA `_n()`.
 *
 * `_n()` przyjmuje DWIE formy — pojedynczą i mnogą — a formy dodatkowe bierze
 * z pliku tłumaczeń. Polski ma trzy formy (1 zdjęcie / 2 zdjęcia / 5 zdjęć),
 * a język źródłowy tego produktu JEST polski, więc pliku tłumaczeń dla niego
 * nie ma i nigdy nie będzie. Skutek: `_n()` odpala zapasową regułę
 * „1 albo reszta" i pokazuje **„Wybrałaś 4 zdjęć"**.
 *
 * To nie jest drobiazg kosmetyczny. Licznik pakietu jest najczęściej czytanym
 * tekstem w całym produkcie — widzi go każda klientka przy każdym kliknięciu.
 *
 * Każda z trzech form przechodzi osobno przez `__()`, więc tłumacz na inny
 * język dostaje je normalnie. Wybór formy robi ta klasa, bo reguła jest
 * językowa, a nie zależna od tłumaczenia.
 */
final class Plural {

	/**
	 * Wybór formy według reguł polszczyzny.
	 *
	 * Pułapka, o którą łatwo się potknąć: nastki (12, 13, 14) idą do formy
	 * dopełniaczowej mimo końcówki 2–4 — „dwanaście zdjęć", nie „zdjęcia".
	 */
	public static function pick( int $count, string $one, string $few, string $many ): string {
		$count = abs( $count );
		$last  = $count % 10;
		$teens = $count % 100;

		if ( 1 === $count ) {
			return $one;
		}

		if ( $last >= 2 && $last <= 4 && ( $teens < 12 || $teens > 14 ) ) {
			return $few;
		}

		return $many;
	}

	/**
	 * „1 zdjęcie" / „2 zdjęcia" / „5 zdjęć" z liczbą sformatowaną lokalnie.
	 */
	public static function photos( int $count ): string {
		$form = self::pick(
			$count,
			/* translators: %s: liczba zdjęć (forma dla jedynki) */
			__( '%s zdjęcie', 'kadr' ),
			/* translators: %s: liczba zdjęć (forma dla 2–4) */
			__( '%s zdjęcia', 'kadr' ),
			/* translators: %s: liczba zdjęć (forma dla 5 i więcej) */
			__( '%s zdjęć', 'kadr' )
		);

		return sprintf( $form, number_format_i18n( $count ) );
	}

	/**
	 * „Wybrałaś 1 zdjęcie" / „…2 zdjęcia" / „…5 zdjęć".
	 *
	 * Forma żeńska jest świadoma: klientkami fotografów rodzinnych,
	 * ślubnych i newbornowych są w przeważającej większości kobiety.
	 * Gdy pojawi się potrzeba formy neutralnej, będzie to ustawienie
	 * galerii, a nie zgadywanie po imieniu.
	 */
	public static function chosen( int $count ): string {
		$form = self::pick(
			$count,
			/* translators: %s: liczba wybranych zdjęć (forma dla jedynki) */
			__( 'Wybrałaś %s zdjęcie', 'kadr' ),
			/* translators: %s: liczba wybranych zdjęć (forma dla 2–4) */
			__( 'Wybrałaś %s zdjęcia', 'kadr' ),
			/* translators: %s: liczba wybranych zdjęć (forma dla 5 i więcej) */
			__( 'Wybrałaś %s zdjęć', 'kadr' )
		);

		return sprintf( $form, number_format_i18n( $count ) );
	}
}
