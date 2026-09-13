/**
 * Powiadomienia w interfejsie.
 *
 * Toast informuje o skutku akcji i — gdy akcja się nie udała — daje sposób
 * na ponowienie. Toast bez wyjścia z sytuacji jest tylko hałasem.
 */
import { html, render, signal, __ } from './runtime.js';

const toasts = signal( [] );
let nextId = 1;
let container = null;

function mount() {
	if ( container ) {
		return;
	}

	container = document.createElement( 'div' );
	container.className = 'kadr-toasts';
	// Komunikaty czyta czytnik ekranu, ale nie przerywa tego, co robi
	// użytkownik — stąd „polite”, nie „assertive”.
	container.setAttribute( 'role', 'status' );
	container.setAttribute( 'aria-live', 'polite' );
	document.body.appendChild( container );

	render( html`<${ToastList} />`, container );
}

function ToastList() {
	return html`
		${ toasts.value.map(
			( toast ) => html`
				<div class="kadr-toast kadr-toast--${ toast.tone }" key=${ toast.id }>
					<span>${ toast.message }</span>
					${ toast.action
						? html`<button
								type="button"
								class="kadr-toast__action"
								onClick=${ () => {
									dismiss( toast.id );
									toast.action.run();
								} }
						  >${ toast.action.label }</button>`
						: null }
				</div>
			`
		) }
	`;
}

function push( message, { tone = 'info', action = null, duration = 6000 } = {} ) {
	mount();

	const id = nextId++;

	toasts.value = [ ...toasts.value, { id, message, tone, action } ];

	// Komunikat z akcją zostaje dłużej — użytkownik musi zdążyć go przeczytać
	// i zdecydować.
	const visibleFor = action ? Math.max( duration, 10000 ) : duration;

	if ( visibleFor > 0 ) {
		setTimeout( () => dismiss( id ), visibleFor );
	}

	return id;
}

export function dismiss( id ) {
	toasts.value = toasts.value.filter( ( toast ) => toast.id !== id );
}

export const toast = {
	info: ( message, options ) => push( message, { ...options, tone: 'info' } ),
	success: ( message, options ) => push( message, { ...options, tone: 'success' } ),
	warning: ( message, options ) => push( message, { ...options, tone: 'warning' } ),

	/**
	 * Błąd zawsze z możliwością ponowienia, jeśli ponowienie ma sens.
	 */
	error: ( error, retry = null ) => {
		const message = error?.message || __( 'Coś poszło nie tak.' );
		const canRetry = retry && ( ! error?.isRetryable || error.isRetryable );

		return push( message, {
			tone: 'danger',
			duration: 0,
			action: canRetry ? { label: __( 'Spróbuj ponownie' ), run: retry } : null,
		} );
	},
};
