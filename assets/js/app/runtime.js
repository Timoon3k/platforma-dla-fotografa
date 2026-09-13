/**
 * Kadr — wspólny runtime panelu (ADR-018).
 *
 * Jedno miejsce, z którego komponenty biorą Preact, sygnały i `html`.
 * Dzięki temu ścieżki do dołączonych bibliotek są w jednym pliku,
 * a nie rozsiane po kilkunastu importach.
 */
import { h, render, Fragment } from '../../vendor/preact.js';
import { useState, useEffect, useRef, useCallback, useMemo } from '../../vendor/preact-hooks.js';
/*
 * UWAGA: importujemy z `signals.js`, czyli z integracji @preact/signals,
 * a NIE z `signals-core.js`. Rdzeń sam w sobie liczy poprawnie, ale nie wie
 * nic o Preakcie — komponenty nie przerysowują się przy zmianie sygnału.
 * Wykrył to test w przeglądarce: tabela i paleta nie reagowały na zmiany.
 */
import { signal, computed, effect, batch } from '../../vendor/signals.js';
import { useSignal, useComputed, useSignalEffect } from '../../vendor/signals.js';
import htmFactory from '../../vendor/htm.js';

/** Szablony tagowane zamiast JSX — JSX bez kompilacji nie działa. */
export const html = htmFactory.bind( h );

export { h, render, Fragment, useState, useEffect, useRef, useCallback, useMemo };
export { signal, computed, effect, batch };
export { useSignal, useComputed, useSignalEffect };

/**
 * Odwlekanie wywołania. Wyszukiwarka bez tego wysyła zapytanie przy każdym
 * naciśnięciu klawisza (docs/PERFORMANCE.md §5).
 */
export function debounce( fn, waitMs = 250 ) {
	let timer = null;

	return function debounced( ...args ) {
		clearTimeout( timer );
		timer = setTimeout( () => fn.apply( this, args ), waitMs );
	};
}

/**
 * Formatowanie liczb i kwot zgodnie z polską konwencją.
 *
 * Kwoty przychodzą z API w groszach — nigdy nie liczymy pieniędzy
 * na liczbach zmiennoprzecinkowych.
 */
const numberFormat = new Intl.NumberFormat( 'pl-PL' );
const decimalFormat = new Intl.NumberFormat( 'pl-PL', { maximumFractionDigits: 1 } );

export function formatNumber( value ) {
	return numberFormat.format( Number( value ) || 0 );
}

export function formatMoney( minorUnits, currency = 'PLN' ) {
	return new Intl.NumberFormat( 'pl-PL', {
		style: 'currency',
		currency,
		minimumFractionDigits: ( Number( minorUnits ) || 0 ) % 100 === 0 ? 0 : 2,
	} ).format( ( Number( minorUnits ) || 0 ) / 100 );
}

/**
 * Polski ma TRZY formy mnogie, nie dwie.
 *
 * `_n()` WordPressa przyjmuje dwie i przy polskim jako języku źródłowym nie
 * ma z czego wziąć trzeciej — stąd „Wybrałaś 4 zdjęć". Reguła jest prosta
 * i nie zmienia się od stuleci, więc trzymamy ją w jednym miejscu zamiast
 * powtarzać w każdym komponencie.
 *
 * @param {number} count Liczba, do której dobieramy formę.
 * @param {string} one   Forma dla 1: „zdjęcie".
 * @param {string} few   Forma dla 2–4 (poza 12–14): „zdjęcia".
 * @param {string} many  Forma dla reszty: „zdjęć".
 */
export function plural( count, one, few, many ) {
	const value = Math.abs( Number( count ) || 0 );
	const last = value % 10;
	const teens = value % 100;

	if ( 1 === value ) {
		return one;
	}

	if ( last >= 2 && last <= 4 && ( teens < 12 || teens > 14 ) ) {
		return few;
	}

	return many;
}

/**
 * Formy, które w panelu powtarzają się najczęściej. Każda idzie przez `__()`
 * osobno, bo tłumacz musi móc odmienić je niezależnie.
 */
export const Plural = {
	photos: ( count ) => plural( count, __( 'zdjęcie' ), __( 'zdjęcia' ), __( 'zdjęć' ) ),
	chosen: ( count ) => plural( count, __( 'zaznaczone' ), __( 'zaznaczone' ), __( 'zaznaczonych' ) ),
};

/**
 * Rozmiar pliku w jednostce, która coś znaczy.
 *
 * Zaokrąglanie wszystkiego do megabajtów sprawiało, że plik 300 kB
 * pokazywał się jako „0 MB”. Poniżej megabajta schodzimy na kilobajty,
 * a między 1 a 10 GB pokazujemy jedno miejsce po przecinku — przy limicie
 * planu różnica między 5 a 5,4 GB jest istotna.
 */
export function formatBytes( bytes ) {
	const value = Number( bytes ) || 0;
	const kb = 1024;
	const mb = 1024 ** 2;
	const gb = 1024 ** 3;
	const tb = 1024 ** 4;

	if ( value >= tb ) {
		return `${ decimalFormat.format( value / tb ) } TB`;
	}

	if ( value >= gb ) {
		return value < 10 * gb
			? `${ decimalFormat.format( value / gb ) } GB`
			: `${ numberFormat.format( Math.round( value / gb ) ) } GB`;
	}

	if ( value >= mb ) {
		return `${ numberFormat.format( Math.round( value / mb ) ) } MB`;
	}

	if ( value >= kb ) {
		return `${ numberFormat.format( Math.round( value / kb ) ) } kB`;
	}

	return `${ numberFormat.format( value ) } B`;
}

/**
 * Tłumaczenia z WordPressa, z bezpiecznym zapasem.
 *
 * Panel może wystartować, zanim `wp.i18n` się załaduje — brak tłumaczenia
 * nie może wywrócić interfejsu.
 */
export function __( text, domain = 'kadr' ) {
	return window.wp?.i18n?.__ ? window.wp.i18n.__( text, domain ) : text;
}
