/**
 * Atrapa sieci dla podglądu ekranów uwierzytelniania.
 *
 * Odpowiada w kształcie REST API v1, żeby dało się zobaczyć wszystkie stany
 * formularza: błąd walidacji pod polem, zajęty adres, złe hasło i sukces.
 * Reguła jest umowna i dotyczy wyłącznie podglądu.
 */
window.kadrAuth = { root: '/wp-json/kadr/v1/' };

const json = ( payload, status ) =>
	new Response( JSON.stringify( payload ), {
		status,
		headers: { 'Content-Type': 'application/json' },
	} );

window.fetch = async ( input, init ) => {
	const url = String( input instanceof Request ? input.url : input );
	const body = JSON.parse( init?.body || '{}' );

	await new Promise( ( resolve ) => setTimeout( resolve, 400 ) );

	if ( url.endsWith( '/register' ) ) {
		if ( 'zajety@example.test' === body.email ) {
			return json(
				{
					code: 'kadr_invalid_input',
					message: 'Popraw zaznaczone pola.',
					data: { status: 422, params: { email: 'Konto z tym adresem już istnieje. Zaloguj się.' } },
				},
				422
			);
		}

		return json( { data: { studio: body.studio, redirect: '#' }, meta: {} }, 201 );
	}

	if ( url.endsWith( '/session' ) ) {
		if ( 'poprawne-haslo' !== body.password ) {
			return json(
				{
					code: 'kadr_invalid_credentials',
					message: 'Nieprawidłowy adres e-mail lub hasło.',
					data: { status: 401 },
				},
				401
			);
		}

		return json( { data: { redirect: '#' }, meta: {} }, 200 );
	}

	return json( { code: 'kadr_not_found', message: 'Nie znaleziono.' }, 404 );
};
