/**
 * Punkt wejścia panelu fotografa.
 *
 * Powłoka (nawigacja, pasek górny) jest renderowana przez serwer — przy
 * wejściu w panel od razu widać ramę aplikacji, a nie pusty ekran czekający
 * na moduły. Ten plik dokłada to, co wymaga JavaScriptu: widok w obszarze
 * treści, paletę poleceń i szufladę nawigacji na telefonie.
 */
import { html, render, __ } from './runtime.js';
import { bindShortcut, registerCommands, open as openPalette } from './palette.js';
import { toast } from './toast.js';
import { TodayView } from './views/today.js';
import { GalleriesView } from './views/galleries.js';
import { ClientsView } from './views/clients.js';

/**
 * Sekcje mające własny widok.
 *
 * Pozostałe pozycje nawigacji są w powłoce i prowadzą do adresów, które
 * dostaną widoki w kolejnych sesjach. Do tego czasu pokazują stan przejściowy
 * zamiast pustego obszaru — użytkownik ma wiedzieć, że trafił tam, gdzie
 * chciał, a nie że coś się zepsuło.
 */
const VIEWS = {
	'': TodayView,
	galerie: GalleriesView,
	klienci: ClientsView,
};

const SOON = {
	wybory: __( 'Wybory' ),
	kalendarz: __( 'Kalendarz' ),
	zamowienia: __( 'Zamówienia' ),
	produkty: __( 'Produkty' ),
	analityka: __( 'Analityka' ),
	ustawienia: __( 'Ustawienia' ),
	rozliczenia: __( 'Rozliczenia' ),
};

function NotReadyYet( { title } ) {
	return html`
		<div class="kadr-view__head"><div><h1 class="kadr-view__title">${ title }</h1></div></div>
		<div class="kadr-empty-panel">
			<p class="kadr-empty-panel__title">${ __( 'Ten widok powstaje' ) }</p>
			<p class="kadr-empty-panel__text">
				${ __( 'Sekcja jest częścią panelu, ale jej ekran nie jest jeszcze gotowy.' ) }
			</p>
		</div>
	`;
}

function mountView() {
	const mount = document.getElementById( 'kadr-app-view' );

	if ( ! mount ) {
		return;
	}

	const section = mount.dataset.section || '';
	const View = VIEWS[ section ];

	if ( View ) {
		render( html`<${View} />`, mount );
		return;
	}

	render( html`<${NotReadyYet} title=${ SOON[ section ] || __( 'Panel' ) } />`, mount );
}

/**
 * Polecenia palety pochodzą z nawigacji w dokumencie.
 *
 * Jedno źródło prawdy: dopisanie pozycji w powłoce po stronie serwera
 * automatycznie dokłada ją do palety. Druga lista w JavaScripcie
 * rozjechałaby się z pierwszą przy pierwszej zmianie.
 */
function commandsFromNavigation() {
	const commands = [];

	document.querySelectorAll( '.kadr-app__nav .kadr-nav__group' ).forEach( ( group ) => {
		const groupLabel = group.querySelector( '.kadr-nav__label' )?.textContent?.trim() || '';

		group.querySelectorAll( '.kadr-nav__item' ).forEach( ( item ) => {
			const label = item.querySelector( 'span' )?.textContent?.trim() || '';
			const path = item.getAttribute( 'href' );

			if ( '' === label || ! path ) {
				return;
			}

			commands.push( {
				id: path,
				label,
				group: groupLabel,
				run: () => {
					window.location.href = path;
				},
			} );
		} );
	} );

	return commands;
}

/**
 * Szuflada nawigacji na telefonie.
 *
 * Stan trzyma atrybut na elemencie nawigacji, a `aria-expanded` na przycisku —
 * czytnik ekranu ma wiedzieć, czy menu jest otwarte, zanim je usłyszy.
 */
function bindNavigationToggle() {
	const button = document.querySelector( '.kadr-app__menu' );
	const nav = document.querySelector( '.kadr-app__nav' );

	if ( ! button || ! nav ) {
		return;
	}

	const setOpen = ( open ) => {
		nav.dataset.open = open ? 'true' : 'false';
		button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	};

	setOpen( false );

	button.addEventListener( 'click', () => setOpen( 'true' !== nav.dataset.open ) );

	// Wybranie pozycji zamyka szufladę — inaczej po powrocie zasłania treść.
	nav.addEventListener( 'click', ( event ) => {
		if ( event.target.closest( '.kadr-nav__item' ) ) {
			setOpen( false );
		}
	} );

	document.addEventListener( 'keydown', ( event ) => {
		if ( 'Escape' === event.key && 'true' === nav.dataset.open ) {
			setOpen( false );
			button.focus();
		}
	} );
}

/**
 * Wyszukiwarka w pasku górnym otwiera paletę.
 *
 * Dwa osobne mechanizmy wyszukiwania w jednym widoku to dwa miejsca, w których
 * użytkownik może się pomylić. Pole jest wejściem do palety, nie jej kopią.
 */
function bindTopSearch() {
	const input = document.getElementById( 'kadr-app-search' );

	if ( ! input ) {
		return;
	}

	input.addEventListener( 'focus', () => {
		input.blur();
		openPalette();
	} );
}

registerCommands( commandsFromNavigation() );
bindShortcut();
bindNavigationToggle();
bindTopSearch();
mountView();

// Powiadomienia są dostępne dla pozostałych modułów panelu.
window.kadr = { toast };
