/**
 * Klient REST API v1.
 *
 * Jedyny kanał danych panelu (CLAUDE.md §4). Zero `admin-ajax`,
 * zero danych przemycanych w globalnych zmiennych.
 */
import { __ } from './runtime.js';

const config = window.kadrApp || {};

/**
 * Błąd API zachowujący kod i status — widok decyduje, co z nim zrobić,
 * a nie zgaduje z treści komunikatu.
 */
export class ApiError extends Error {
	constructor( { code, message, status, details } ) {
		super( message || __( 'Coś poszło nie tak.' ) );
		this.name = 'ApiError';
		this.code = code || 'kadr_unknown';
		this.status = status || 0;
		this.details = details || {};
	}

	/** Czy warto ponowić — awaria sieci albo chwilowy problem serwera. */
	get isRetryable() {
		return 0 === this.status || this.status >= 500 || 429 === this.status;
	}

	/** Czy to reguła biznesowa (limit planu), a nie błąd żądania. */
	get isBusinessRule() {
		return 422 === this.status;
	}
}

async function request( method, path, { body, params, signal } = {} ) {
	const url = new URL( ( config.root || '/wp-json/kadr/v1/' ) + path.replace( /^\//, '' ), window.location.origin );

	Object.entries( params || {} ).forEach( ( [ key, value ] ) => {
		if ( null !== value && undefined !== value && '' !== value ) {
			url.searchParams.set( key, String( value ) );
		}
	} );

	let response;

	try {
		response = await fetch( url, {
			method,
			signal,
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				// Ochrona CSRF dla żądań z sesji przeglądarkowej.
				'X-WP-Nonce': config.nonce || '',
			},
			body: undefined === body ? undefined : JSON.stringify( body ),
		} );
	} catch ( error ) {
		if ( 'AbortError' === error.name ) {
			throw error;
		}

		// Brak sieci to nie jest „coś poszło nie tak” — to konkretna sytuacja,
		// w której warto zaproponować ponowienie.
		throw new ApiError( {
			code: 'kadr_network',
			message: __( 'Brak połączenia. Sprawdź sieć i spróbuj ponownie.' ),
			status: 0,
		} );
	}

	if ( 204 === response.status ) {
		return { data: null, meta: {} };
	}

	let payload = null;

	try {
		payload = await response.json();
	} catch ( error ) {
		payload = null;
	}

	if ( ! response.ok ) {
		throw new ApiError( {
			code: payload?.code,
			message: payload?.message,
			status: response.status,
			details: payload?.data || {},
		} );
	}

	return {
		data: payload?.data ?? null,
		meta: payload?.meta ?? {},
	};
}

export const api = {
	get: ( path, options ) => request( 'GET', path, options ),
	post: ( path, body, options ) => request( 'POST', path, { ...options, body } ),
	patch: ( path, body, options ) => request( 'PATCH', path, { ...options, body } ),
	put: ( path, body, options ) => request( 'PUT', path, { ...options, body } ),
	delete: ( path, options ) => request( 'DELETE', path, options ),
};

/**
 * Pobieranie kolejnych stron kursorem.
 *
 * Paginacja kursorowa, nie offsetowa — `OFFSET 50000` skanuje pięćdziesiąt
 * tysięcy wierszy, żeby oddać dwadzieścia.
 */
export async function* paginate( path, params = {} ) {
	let cursor = null;

	do {
		const { data, meta } = await api.get( path, { params: { ...params, cursor } } );

		yield data || [];

		cursor = meta?.next_cursor || null;
	} while ( cursor );
}
