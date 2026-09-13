/**
 * Paleta poleceń (Cmd+K / Ctrl+K).
 *
 * Nawigacja i wyszukiwanie z klawiatury. Dla fotografa, który spędza
 * w panelu setki godzin, to różnica między pracą a klikaniem.
 */
import { html, render, signal, __ } from './runtime.js';

const query = signal( '' );
const active = signal( 0 );
let commands = [];
let element = null;

export function registerCommands( list ) {
	commands = list;
}

function matches() {
	const needle = query.value.trim().toLowerCase();

	if ( '' === needle ) {
		return commands.slice( 0, 8 );
	}

	return commands
		.filter( ( command ) => {
			const haystack = `${ command.label } ${ command.group || '' } ${ ( command.keywords || [] ).join( ' ' ) }`;

			return haystack.toLowerCase().includes( needle );
		} )
		.slice( 0, 10 );
}

function Palette() {
	const items = matches();

	return html`
		<input
			type="text"
			class="kadr-palette__input"
			placeholder=${ __( 'Szukaj klientów, galerii, zamówień…' ) }
			aria-label=${ __( 'Wyszukaj polecenie' ) }
			value=${ query.value }
			onInput=${ ( event ) => {
				query.value = event.target.value;
				active.value = 0;
			} }
		/>
		<ul class="kadr-palette__list" role="listbox" aria-label=${ __( 'Wyniki' ) }>
			${ items.length
				? items.map(
						( command, index ) => html`
							<li
								class="kadr-palette__item"
								role="option"
								key=${ command.id }
								aria-selected=${ index === active.value }
								onMouseEnter=${ () => ( active.value = index ) }
								onClick=${ () => run( command ) }
							>
								<span>${ command.label }</span>
								${ command.group ? html`<span class="kadr-palette__hint">${ command.group }</span>` : null }
							</li>
						`
				  )
				: html`<li class="kadr-palette__item" aria-disabled="true">${ __( 'Brak wyników' ) }</li>` }
		</ul>
	`;
}

function run( command ) {
	close();
	command.run();
}

function ensure() {
	if ( element ) {
		return element;
	}

	element = document.createElement( 'dialog' );
	element.className = 'kadr-palette';
	element.setAttribute( 'aria-label', __( 'Paleta poleceń' ) );
	document.body.appendChild( element );

	element.addEventListener( 'keydown', onKeyDown );
	element.addEventListener( 'click', ( event ) => {
		if ( event.target === element ) {
			close();
		}
	} );

	return element;
}

function onKeyDown( event ) {
	const items = matches();

	if ( 'ArrowDown' === event.key ) {
		event.preventDefault();
		active.value = ( active.value + 1 ) % Math.max( items.length, 1 );
	} else if ( 'ArrowUp' === event.key ) {
		event.preventDefault();
		active.value = ( active.value - 1 + items.length ) % Math.max( items.length, 1 );
	} else if ( 'Enter' === event.key ) {
		event.preventDefault();
		const command = items[ active.value ];

		if ( command ) {
			run( command );
		}
	}
}

export function open() {
	const dialog = ensure();

	query.value = '';
	active.value = 0;
	render( html`<${Palette} />`, dialog );
	dialog.showModal();
	dialog.querySelector( '.kadr-palette__input' )?.focus();
}

export function close() {
	element?.close();
}

/**
 * Skrót działa wszędzie poza polami tekstowymi — inaczej nie dałoby się
 * wpisać litery „k” w formularzu.
 */
export function bindShortcut() {
	document.addEventListener( 'keydown', ( event ) => {
		if ( 'k' !== event.key.toLowerCase() || ! ( event.metaKey || event.ctrlKey ) ) {
			return;
		}

		const target = event.target;
		const isTextField =
			target instanceof HTMLElement &&
			( 'INPUT' === target.tagName || 'TEXTAREA' === target.tagName || target.isContentEditable );

		if ( isTextField && ! target.closest( '.kadr-palette' ) ) {
			return;
		}

		event.preventDefault();
		open();
	} );
}
