<?php
/**
 * Odinstalowanie wtyczki Kadr.
 *
 * DOMYŚLNIE NIE USUWA ŻADNYCH DANYCH.
 *
 * Odinstalowanie wtyczki i usunięcie danych to dwie różne decyzje
 * (docs/ARCHITECTURE.md §9). Fotograf, który odinstalował wtyczkę przez
 * pomyłkę albo przenosi ją na inny serwer, nie może przy okazji stracić
 * zdjęć swoich klientów.
 *
 * Usunięcie danych wymaga jawnej, osobno potwierdzonej decyzji administratora,
 * która ustawia opcję `kadr_delete_all_data_on_uninstall`.
 *
 * @package Kadr
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( true !== get_option( 'kadr_delete_all_data_on_uninstall', false ) ) {
	return;
}

// Usuwamy wyłącznie ustawienia wtyczki. Dane aplikacyjne (własne tabele)
// trafią tu w Session 3 — również za tą samą, jawną flagą.
delete_option( 'kadr_version' );
delete_option( 'kadr_schema_version' );
delete_option( 'kadr_delete_all_data_on_uninstall' );
