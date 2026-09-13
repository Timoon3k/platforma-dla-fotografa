/*
 * Atrapa sieci dla podglądu wyboru zdjęć.
 *
 * Zwykły skrypt, nie moduł: musi wykonać się PRZED modułem galerii,
 * a klasyczne skrypty idą pierwsze.
 *
 * Liczy dokładnie tę samą arytmetykę, co `PackageTally` po stronie serwera —
 * podgląd ma pokazywać prawdziwe zachowanie licznika, a nie jego imitację.
 */
( function () {
	var PACKAGE = 20;
	var PRICE = 6000;

	function selectedCount() {
		return document.querySelectorAll( '.kadr-g-item__button[data-state="selected"]' ).length;
	}

	function tally() {
		var selected = selectedCount();
		var included = Math.min( selected, PACKAGE );
		var extra = Math.max( 0, selected - PACKAGE );

		return {
			selected: selected,
			package_limit: PACKAGE,
			included: included,
			extra: extra,
			unit_price: PRICE,
			total: extra * PRICE,
			remaining: Math.max( 0, PACKAGE - selected ),
			at_limit: selected === PACKAGE,
			needs_payment: extra > 0,
		};
	}

	var realFetch = window.fetch.bind( window );

	window.fetch = function ( input, init ) {
		var url = String( input instanceof Request ? input.url : input );

		/*
		 * Doczytywanie kolejnej strony kadrów. Galeria odpala je sama, gdy
		 * przycisk zbliży się do ekranu — a że w podglądzie strona stoi pod
		 * `file://`, prawdziwy fetch kończył się błędem CORS. Bramka widziała
		 * ten błąd RAZ NA KILKA URUCHOMIEŃ, zależnie od tego, jak daleko
		 * przewinął ją test telefonu. Bramka, która czasem świeci na czerwono
		 * bez powodu, przestaje cokolwiek znaczyć, więc odpowiadamy tu wprost:
		 * pusta strona i „nie ma więcej".
		 */
		if ( url.indexOf( '/dalej/' ) !== -1 ) {
			return Promise.resolve(
				new Response( '', { status: 200, headers: { 'X-Kadr-More': '0' } } )
			);
		}

		if ( url.indexOf( '/shared/' ) === -1 ) {
			return realFetch( input, init );
		}

		var body = JSON.stringify(
			'POST' === ( init && init.method )
				? { data: { status: 'submitted', tally: tally(), states: {} }, meta: {} }
				: { data: { status: 'open', tally: tally(), states: {} }, meta: {} }
		);

		return Promise.resolve(
			new Response( body, { status: 200, headers: { 'Content-Type': 'application/json' } } )
		);
	};
} )();
