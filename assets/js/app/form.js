/**
 * Formularze panelu.
 *
 * Trzy rzeczy, które w formularzu SaaS-a psują się najczęściej, i dlatego
 * są tu rozwiązane raz, a nie w każdym widoku osobno:
 * 1. walidacja pokazywana w złym momencie — czerwień pod polem, zanim
 *    ktokolwiek zdążył je wypełnić,
 * 2. błąd z serwera wyświetlany jako toast, przez co nie widać, które pole
 *    go wywołało,
 * 3. utrata wpisanej treści po przypadkowym zamknięciu karty.
 */
import { html, useState, useRef, useEffect, useCallback, __ } from './runtime.js';
import { ApiError } from './api.js';

/**
 * Pojedyncze pole z etykietą, opisem i błędem powiązanym przez `aria-describedby`.
 *
 * Błąd jest opisem pola, nie osobnym komunikatem obok — czytnik ekranu czyta
 * go razem z etykietą, a nie dopiero wtedy, gdy użytkownik go znajdzie.
 */
export function Field( {
	name,
	label,
	value = '',
	error = '',
	hint = '',
	type = 'text',
	required = false,
	autocomplete = null,
	inputmode = null,
	placeholder = '',
	disabled = false,
	rows = 0,
	onInput,
	onBlur,
} ) {
	const id = `kadr-field-${ name }`;
	const hintId = hint ? `${ id }-hint` : null;
	const errorId = error ? `${ id }-error` : null;
	const describedBy = [ errorId, hintId ].filter( Boolean ).join( ' ' ) || null;

	const shared = {
		id,
		name,
		value,
		required,
		disabled,
		placeholder,
		autocomplete,
		inputmode,
		'aria-invalid': error ? 'true' : null,
		'aria-describedby': describedBy,
		class: 'kadr-field__input',
		onInput: ( event ) => onInput && onInput( name, event.target.value ),
		onBlur: () => onBlur && onBlur( name ),
	};

	return html`
		<div class="kadr-field">
			<label class="kadr-field__label" for=${ id }>
				${ label }
				${ required ? html`<span class="kadr-field__required" aria-hidden="true">*</span>` : null }
			</label>
			${ rows > 0
				? html`<textarea ...${ shared } rows=${ rows }></textarea>`
				: html`<input ...${ shared } type=${ type } />` }
			${ error
				? html`<span class="kadr-field__error" id=${ errorId }>${ error }</span>`
				: null }
			${ hint ? html`<p class="kadr-field__hint" id=${ hintId }>${ hint }</p>` : null }
		</div>
	`;
}

/**
 * Zestaw reguł walidacji. Zwracają komunikat albo pusty łańcuch.
 *
 * Komunikat mówi, co zrobić, a nie że coś jest źle: „Podaj adres e-mail”,
 * nie „Nieprawidłowa wartość”.
 */
export const rules = {
	required: ( message ) => ( value ) =>
		'' === String( value ?? '' ).trim() ? message || __( 'To pole jest wymagane.' ) : '',

	email: ( message ) => ( value ) => {
		const text = String( value ?? '' ).trim();

		if ( '' === text ) {
			return '';
		}

		// Celowo luźno: walidacja adresu regexem zawsze odrzuca jakiś
		// poprawny adres. Prawdziwym sprawdzeniem jest wysłana wiadomość.
		return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test( text )
			? ''
			: message || __( 'Podaj adres e-mail w formacie nazwa@domena.pl.' );
	},

	maxLength: ( limit, message ) => ( value ) =>
		String( value ?? '' ).length > limit
			? message || __( 'Tekst jest za długi.' )
			: '',
};

/**
 * Stan formularza: wartości, błędy, dotknięte pola, wysyłka.
 *
 * Walidacja uruchamia się przy opuszczeniu pola i przy wysyłce. Nigdy przy
 * pisaniu — poza polem, które już ma błąd: tam poprawka ma znikać od razu.
 *
 * @param {object} options
 * @param {object} options.initial  wartości początkowe
 * @param {object} options.validate mapa pole → tablica reguł
 * @param {Function} options.onSubmit wywoływane z wartościami; może rzucić ApiError
 */
export function useForm( { initial = {}, validate = {}, onSubmit } ) {
	const [ values, setValues ] = useState( initial );
	const [ errors, setErrors ] = useState( {} );
	const [ touched, setTouched ] = useState( {} );
	const [ submitting, setSubmitting ] = useState( false );
	const [ formError, setFormError ] = useState( '' );

	const runRules = useCallback(
		( name, value ) => {
			for ( const rule of validate[ name ] || [] ) {
				const message = rule( value, values );

				if ( message ) {
					return message;
				}
			}

			return '';
		},
		[ validate, values ]
	);

	const setValue = useCallback(
		( name, value ) => {
			setValues( ( previous ) => ( { ...previous, [ name ]: value } ) );
			setErrors( ( previous ) =>
				previous[ name ] ? { ...previous, [ name ]: runRules( name, value ) } : previous
			);
		},
		[ runRules ]
	);

	const touch = useCallback(
		( name ) => {
			setTouched( ( previous ) => ( { ...previous, [ name ]: true } ) );
			setErrors( ( previous ) => ( { ...previous, [ name ]: runRules( name, values[ name ] ) } ) );
		},
		[ runRules, values ]
	);

	const submit = useCallback(
		async ( event ) => {
			event?.preventDefault();
			setFormError( '' );

			const found = {};

			Object.keys( validate ).forEach( ( name ) => {
				const message = runRules( name, values[ name ] );

				if ( message ) {
					found[ name ] = message;
				}
			} );

			setErrors( found );

			if ( Object.keys( found ).length > 0 ) {
				// Fokus na pierwszym błędnym polu — inaczej na długim
				// formularzu nie widać, gdzie jest problem.
				document.getElementById( `kadr-field-${ Object.keys( found )[ 0 ] }` )?.focus();
				return false;
			}

			setSubmitting( true );

			try {
				await onSubmit( values );
				return true;
			} catch ( error ) {
				if ( error instanceof ApiError ) {
					// Serwer potrafi wskazać pole (`details.params`). Gdy wskazuje,
					// błąd ląduje pod tym polem, a nie w ogólnym komunikacie.
					const params = error.details?.params || {};
					const mapped = {};

					Object.entries( params ).forEach( ( [ name, message ] ) => {
						mapped[ name ] = String( message );
					} );

					setErrors( mapped );
					setFormError( Object.keys( mapped ).length > 0 ? '' : error.message );
				} else {
					setFormError( __( 'Nie udało się zapisać. Spróbuj ponownie.' ) );
				}

				return false;
			} finally {
				setSubmitting( false );
			}
		},
		[ onSubmit, runRules, validate, values ]
	);

	return {
		values,
		errors,
		touched,
		submitting,
		formError,
		setValue,
		setValues,
		touch,
		submit,
		field: ( name ) => ( {
			name,
			value: values[ name ] ?? '',
			error: errors[ name ] || '',
			onInput: setValue,
			onBlur: touch,
		} ),
	};
}

/**
 * Roboczy zapis formularza w `sessionStorage`.
 *
 * Chroni przed zamknięciem karty w trakcie wypełniania, ale nie udaje
 * synchronizacji z serwerem — kopia żyje w jednej karcie i znika po zapisie.
 * Wersja serwerowa przyjdzie tam, gdzie będzie potrzebna (opisy galerii,
 * sesja 7), i będzie miała własny endpoint, a nie ten mechanizm.
 */
export function useDraft( key, values, setValues, { enabled = true, delayMs = 600 } = {} ) {
	const storageKey = `kadr:draft:${ key }`;
	const restored = useRef( false );
	const timer = useRef( null );

	useEffect( () => {
		if ( ! enabled || restored.current ) {
			return;
		}

		restored.current = true;

		try {
			const saved = window.sessionStorage.getItem( storageKey );

			if ( saved ) {
				setValues( ( previous ) => ( { ...previous, ...JSON.parse( saved ) } ) );
			}
		} catch ( error ) {
			// Tryb prywatny albo zapełniony magazyn. Brak kopii roboczej
			// nie może wywrócić formularza.
		}
	}, [ enabled, storageKey, setValues ] );

	useEffect( () => {
		if ( ! enabled || ! restored.current ) {
			return;
		}

		clearTimeout( timer.current );
		timer.current = setTimeout( () => {
			try {
				window.sessionStorage.setItem( storageKey, JSON.stringify( values ) );
			} catch ( error ) {
				// jw.
			}
		}, delayMs );

		return () => clearTimeout( timer.current );
	}, [ enabled, storageKey, values, delayMs ] );

	return {
		discard: () => {
			try {
				window.sessionStorage.removeItem( storageKey );
			} catch ( error ) {
				// jw.
			}
		},
	};
}
