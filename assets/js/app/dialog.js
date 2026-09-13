/**
 * Dialogi.
 *
 * Oparte o natywny element `<dialog>` i `showModal()`, który sam zapewnia
 * pułapkę fokusu, obsługę Escape i tło modalne. Własna implementacja tego
 * wszystkiego to kilkaset linii, które przeglądarka ma już poprawnie.
 */
import { html, render, __ } from './runtime.js';

/**
 * Otwiera dialog i zwraca obietnicę rozwiązywaną wyborem użytkownika.
 *
 * @return {Promise<boolean>} true gdy potwierdzono.
 */
export function confirmDialog( {
	title,
	message,
	confirmLabel = __( 'Potwierdź' ),
	cancelLabel = __( 'Anuluj' ),
	danger = false,
} ) {
	return new Promise( ( resolve ) => {
		const element = document.createElement( 'dialog' );
		element.className = `kadr-dialog${ danger ? ' kadr-dialog--danger' : '' }`;

		const close = ( result ) => {
			element.close();
			element.remove();
			resolve( result );
		};

		render(
			html`
				<div class="kadr-dialog__head">
					<h2 class="kadr-dialog__title">${ title }</h2>
				</div>
				<div class="kadr-dialog__body"><p>${ message }</p></div>
				<div class="kadr-dialog__foot">
					<button type="button" class="kadr-btn kadr-btn--secondary" onClick=${ () => close( false ) }>
						${ cancelLabel }
					</button>
					<button
						type="button"
						class="kadr-btn ${ danger ? 'kadr-btn--danger' : 'kadr-btn--primary' }"
						onClick=${ () => close( true ) }
					>${ confirmLabel }</button>
				</div>
			`,
			element
		);

		document.body.appendChild( element );

		// Escape i kliknięcie w tło traktujemy jako rezygnację — nigdy
		// jako potwierdzenie. Przy operacji nieodwracalnej to jest różnica
		// między pomyłką a katastrofą.
		element.addEventListener( 'cancel', ( event ) => {
			event.preventDefault();
			close( false );
		} );

		element.addEventListener( 'click', ( event ) => {
			if ( event.target === element ) {
				close( false );
			}
		} );

		element.showModal();

		// Fokus na akcji bezpiecznej, nie na destrukcyjnej.
		element.querySelector( '.kadr-btn--secondary' )?.focus();
	} );
}

/**
 * Potwierdzenie operacji nieodwracalnej.
 *
 * Osobna funkcja, żeby w kodzie było widać, że coś jest nie do cofnięcia —
 * i żeby wygląd różnił się, zanim ktoś kliknie.
 */
export function confirmDestructive( { title, message, confirmLabel } ) {
	return confirmDialog( {
		title,
		message,
		confirmLabel: confirmLabel || __( 'Usuń' ),
		danger: true,
	} );
}
