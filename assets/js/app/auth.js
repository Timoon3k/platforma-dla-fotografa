/**
 * Rejestracja i logowanie fotografa.
 *
 * Ten sam komponent formularza, co w panelu — walidacja przy opuszczeniu
 * pola, błąd z serwera pod polem, którego dotyczy. Różni się tylko zestawem
 * pól i endpointem.
 *
 * Formularz działa bez JavaScriptu w jednym sensie: bez niego strona nadal
 * pokazuje komunikat, co zrobić. Sam zapis wymaga skryptu, bo idzie przez
 * REST API — jedyny kanał danych w tym produkcie (CLAUDE.md §4.3).
 */
import { html, render, __ } from './runtime.js';
import { Field, rules, useForm } from './form.js';
import { ApiError } from './api.js';

const mount = document.getElementById( 'kadr-auth-form' );

if ( mount ) {
	const mode = mount.dataset.mode || 'signin';
	const nonce = mount.dataset.nonce || '';
	const returnPath = mount.dataset.return || '/app/';

	render(
		html`<${'register' === mode ? RegisterForm : SignInForm} nonce=${ nonce } returnPath=${ returnPath } />`,
		mount
	);
}

/**
 * Wysłanie formularza uwierzytelniania.
 *
 * Nie używa `api.js`, bo tamten klient dokłada nonce REST-owe zalogowanego
 * użytkownika — a tutaj z definicji nikt nie jest zalogowany. Zamiast tego
 * idzie jednorazowy nonce formularza, wygenerowany przy renderowaniu strony.
 */
async function post( path, body, nonce ) {
	const root = window.kadrAuth?.root || '/wp-json/kadr/v1/';

	let response;

	try {
		response = await fetch( root + path, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-Kadr-Nonce': nonce,
			},
			body: JSON.stringify( body ),
		} );
	} catch ( error ) {
		throw new ApiError( {
			code: 'kadr_network',
			message: __( 'Brak połączenia. Sprawdź sieć i spróbuj ponownie.' ),
			status: 0,
		} );
	}

	const payload = await response.json().catch( () => null );

	if ( ! response.ok ) {
		throw new ApiError( {
			code: payload?.code,
			message: payload?.message,
			status: response.status,
			details: payload?.data || {},
		} );
	}

	return payload?.data ?? {};
}

function RegisterForm( { nonce } ) {
	const form = useForm( {
		initial: { studio: '', email: '', password: '' },
		validate: {
			studio: [ rules.required( __( 'Podaj nazwę studia — klient zobaczy ją w galerii i w wiadomościach.' ) ) ],
			email: [ rules.required( __( 'Podaj adres e-mail.' ) ), rules.email() ],
			password: [
				rules.required( __( 'Ustaw hasło.' ) ),
				( value ) =>
					String( value ).length < 10
						? __( 'Hasło musi mieć co najmniej 10 znaków. Najlepsze są długie i łatwe do zapamiętania.' )
						: '',
			],
		},
		onSubmit: async ( values ) => {
			const data = await post( 'register', values, nonce );

			window.location.href = data.redirect || '/app/';
		},
	} );

	return html`
		<form onSubmit=${ form.submit } novalidate>
			${ form.formError ? html`<p class="kadr-form__error">${ form.formError }</p>` : null }

			<${Field}
				...${ form.field( 'studio' ) }
				label=${ __( 'Nazwa studia' ) }
				required
				autocomplete="organization"
				placeholder=${ __( 'Studio Kadr' ) }
				hint=${ __( 'Zobaczy ją klient. Zmienisz ją później w ustawieniach.' ) }
			/>
			<${Field}
				...${ form.field( 'email' ) }
				label=${ __( 'Adres e-mail' ) }
				type="email"
				required
				autocomplete="email"
			/>
			<${Field}
				...${ form.field( 'password' ) }
				label=${ __( 'Hasło' ) }
				type="password"
				required
				autocomplete="new-password"
				hint=${ __( 'Co najmniej 10 znaków.' ) }
			/>

			<div class="kadr-form__actions">
				<button type="submit" class="kadr-btn kadr-btn--primary kadr-btn--block" disabled=${ form.submitting }>
					${ form.submitting ? __( 'Zakładam studio…' ) : __( 'Załóż studio' ) }
				</button>
			</div>
		</form>
	`;
}

function SignInForm( { nonce, returnPath } ) {
	const form = useForm( {
		initial: { email: '', password: '' },
		validate: {
			email: [ rules.required( __( 'Podaj adres e-mail.' ) ) ],
			password: [ rules.required( __( 'Podaj hasło.' ) ) ],
		},
		onSubmit: async ( values ) => {
			const data = await post( 'session', { ...values, wroc: returnPath }, nonce );

			window.location.href = data.redirect || '/app/';
		},
	} );

	return html`
		<form onSubmit=${ form.submit } novalidate>
			${ form.formError ? html`<p class="kadr-form__error">${ form.formError }</p>` : null }

			<${Field}
				...${ form.field( 'email' ) }
				label=${ __( 'Adres e-mail' ) }
				type="email"
				required
				autocomplete="username"
			/>
			<${Field}
				...${ form.field( 'password' ) }
				label=${ __( 'Hasło' ) }
				type="password"
				required
				autocomplete="current-password"
			/>

			<div class="kadr-form__actions">
				<button type="submit" class="kadr-btn kadr-btn--primary kadr-btn--block" disabled=${ form.submitting }>
					${ form.submitting ? __( 'Loguję…' ) : __( 'Zaloguj się' ) }
				</button>
			</div>
		</form>
	`;
}
