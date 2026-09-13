/**
 * Szuflada boczna i popover.
 *
 * Szuflada to miejsce na szczegóły i edycję bez opuszczania listy: fotograf
 * poprawia opis galerii i dalej widzi, przy którym wierszu jest. Dialog
 * przykryłby kontekst, osobny ekran by go zgubił.
 *
 * Oparta o natywny `<dialog>` — pułapka fokusu, Escape i tło modalne
 * są w przeglądarce i działają lepiej niż ich odpowiedniki pisane ręcznie.
 */
import { html, render, __ } from './runtime.js';

/**
 * Otwiera szufladę.
 *
 * @param {object}   options
 * @param {string}   options.title    tytuł, czytany przez `aria-labelledby`
 * @param {Function} options.content  funkcja `(close) => szablon`
 * @param {string}   options.side     'right' (domyślnie) albo 'left'
 * @return {{close: Function}} uchwyt pozwalający zamknąć szufladę z zewnątrz
 */
export function openDrawer( { title, content, side = 'right' } ) {
	const element = document.createElement( 'dialog' );
	element.className = `kadr-drawer kadr-drawer--${ side }`;
	element.setAttribute( 'aria-labelledby', 'kadr-drawer-title' );

	const close = () => {
		if ( ! element.isConnected ) {
			return;
		}

		element.close();
		element.remove();
	};

	render(
		html`
			<header class="kadr-drawer__head">
				<h2 class="kadr-drawer__title" id="kadr-drawer-title">${ title }</h2>
				<button
					type="button"
					class="kadr-drawer__close"
					aria-label=${ __( 'Zamknij' ) }
					onClick=${ close }
				>×</button>
			</header>
			<div class="kadr-drawer__body">${ content( close ) }</div>
		`,
		element
	);

	document.body.appendChild( element );

	element.addEventListener( 'cancel', () => close() );

	// Kliknięcie w tło zamyka. W szufladzie z formularzem to ryzykowne,
	// więc widok, który ma niezapisane zmiany, przechwytuje `close` sam.
	element.addEventListener( 'click', ( event ) => {
		if ( event.target === element ) {
			close();
		}
	} );

	element.showModal();
	element.querySelector( '.kadr-drawer__close' )?.focus();

	return { close };
}

/**
 * Popover przypięty do przycisku: menu kontekstowe, filtr, lista akcji wiersza.
 *
 * Używa Popover API — element leży w warstwie nadrzędnej, więc nie da się go
 * przyciąć `overflow: hidden` rodzica, co jest klasyczną awarią menu w tabeli.
 * Bez Popover API (starsza przeglądarka) przycisk dalej działa: wtedy
 * pokazujemy panel zwykłym atrybutem `hidden`.
 */
export function bindPopover( trigger, panel ) {
	const supported = HTMLElement.prototype.hasOwnProperty( 'popover' );

	panel.classList.add( 'kadr-popover' );
	trigger.setAttribute( 'aria-haspopup', 'menu' );
	trigger.setAttribute( 'aria-expanded', 'false' );

	if ( supported ) {
		panel.popover = 'auto';
		trigger.addEventListener( 'click', () => panel.togglePopover() );
		panel.addEventListener( 'toggle', ( event ) => {
			trigger.setAttribute( 'aria-expanded', 'open' === event.newState ? 'true' : 'false' );
		} );

		return;
	}

	panel.hidden = true;

	const toggle = ( open ) => {
		panel.hidden = ! open;
		trigger.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	};

	trigger.addEventListener( 'click', () => toggle( panel.hidden ) );

	document.addEventListener( 'click', ( event ) => {
		if ( ! panel.hidden && ! panel.contains( event.target ) && event.target !== trigger ) {
			toggle( false );
		}
	} );

	document.addEventListener( 'keydown', ( event ) => {
		if ( 'Escape' === event.key && ! panel.hidden ) {
			toggle( false );
			trigger.focus();
		}
	} );
}
