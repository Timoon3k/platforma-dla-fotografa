/**
 * Atrapa sieci dla podglądu panelu.
 *
 * Przechwytuje `fetch` i odpowiada w kształcie REST API v1: `{ data, meta }`,
 * kursor w `meta.next_cursor`, kwoty w groszach, identyfikatory jako ULID-y.
 * Dzięki temu `api.js` i widoki działają dokładnie tak, jak będą działać
 * na prawdziwych danych — łącznie z opóźnieniem, więc widać szkielety.
 */
const GALLERIES = [
	{ id: '01JB0000000000000000000001', title: 'Ślub Marty i Piotra', slug: 'slub-marty-piotra', status: 'published', theme: 'noir', client: 'Marta Nowak', client_id: '01JC0000000000000000000001', photos: 842, package_limit: 60, extra_photo_price: 6000, allow_download: true, published_at: '2026-09-02 10:00:00', expires_at: '2026-09-16 10:00:00', created_at: '2026-09-01 09:00:00' },
	{ id: '01JB0000000000000000000002', title: 'Kowalscy — sesja rodzinna', slug: 'kowalscy', status: 'published', theme: 'paper', client: 'Anna Kowalska', photos: 148, package_limit: 20, extra_photo_price: 5000, allow_download: false, published_at: '2026-09-08 12:00:00', expires_at: null, created_at: '2026-09-07 11:00:00' },
	{ id: '01JB0000000000000000000003', title: 'Zosia — newborn', slug: 'zosia-newborn', status: 'published', theme: 'minimal', client: 'Kasia Wiśniewska', photos: 96, package_limit: 15, extra_photo_price: 7000, allow_download: true, published_at: '2026-09-10 08:00:00', expires_at: null, created_at: '2026-09-09 20:00:00' },
	{ id: '01JB0000000000000000000004', title: 'Chrzciny Antka', slug: 'chrzciny-antka', status: 'expired', theme: 'paper', client: 'Paweł Lewandowski', photos: 212, package_limit: null, extra_photo_price: null, allow_download: false, published_at: '2026-06-01 10:00:00', expires_at: '2026-09-01 10:00:00', created_at: '2026-05-30 10:00:00' },
	{ id: '01JB0000000000000000000005', title: 'Sesja biznesowa — Lumen', slug: 'lumen', status: 'published', theme: 'minimal', client: 'Studio Lumen', photos: 54, package_limit: 10, extra_photo_price: 9000, allow_download: true, published_at: '2026-09-11 09:00:00', expires_at: null, created_at: '2026-09-10 16:00:00' },
	{ id: '01JB0000000000000000000006', title: 'Plener jesienny', slug: 'plener-jesienny', status: 'draft', theme: 'noir', client: null, photos: 0, package_limit: null, extra_photo_price: null, allow_download: false, published_at: null, expires_at: null, created_at: '2026-09-12 18:00:00' },
];

const CLIENTS = [
	{ id: '01JC0000000000000000000001', first_name: 'Marta', last_name: 'Nowak', email: 'marta@example.test', phone: '+48 600 100 200', created_at: '2026-09-01 09:00:00' },
	{ id: '01JC0000000000000000000002', first_name: 'Anna', last_name: 'Kowalska', email: 'anna@example.test', phone: null, created_at: '2026-09-07 11:00:00' },
	{ id: '01JC0000000000000000000003', first_name: 'Kasia', last_name: 'Wiśniewska', email: 'kasia@example.test', phone: '+48 601 300 400', created_at: '2026-09-09 20:00:00' },
	{ id: '01JC0000000000000000000004', first_name: 'Paweł', last_name: 'Lewandowski', email: 'pawel@example.test', phone: null, created_at: '2026-05-30 10:00:00' },
];

const TODAY = {
	studio: 'Studio Przykładowe (podgląd)',
	plan: { key: 'studio', name: 'Studio', gallery_limit: 150, storage_bytes: 250 * 1024 ** 3 },
	galleries: { draft: 1, published: 4, expired: 1, archived: 0, active: 6 },
	clients: CLIENTS.length,
	storage: { used_bytes: 184 * 1024 ** 3, limit_bytes: 250 * 1024 ** 3, used_ratio: 0.736 },
	expiring: [ { id: GALLERIES[0].id, title: GALLERIES[0].title, expires_at: GALLERIES[0].expires_at } ],
};

window.kadrApp = { root: '/wp-json/kadr/v1/', nonce: 'podglad', locale: 'pl_PL', app: '#' };

/*
 * Zdjęcia w galerii podglądowej.
 *
 * Miniatury to wygenerowane w locie SVG w adresie `data:` — podgląd ma
 * pokazać zachowanie siatki (wirtualizację, proporcje, stan przetwarzania),
 * a nie czyjeś zdjęcia.
 */
const THUMB_COLOURS = [ '#1d2430', '#2a2330', '#22302b', '#302a22', '#242a36', '#2e2431' ];

const thumbFor = ( index ) =>
	'data:image/svg+xml;utf8,' +
	encodeURIComponent(
		`<svg xmlns="http://www.w3.org/2000/svg" width="320" height="213">` +
			`<rect width="320" height="213" fill="${ THUMB_COLOURS[ index % THUMB_COLOURS.length ] }"/>` +
			`<text x="160" y="115" fill="rgba(255,255,255,0.4)" font-family="monospace" font-size="18" text-anchor="middle">` +
			`${ String( index + 1 ).padStart( 4, '0' ) }</text></svg>`
	);

const LINKS = [];

/* Wybór klientki: 28 zdjęć przy pakiecie 20 — scenariusz z bramki sesji. */
const SELECTION = {
	status: 'submitted',
	states: {},
	tally: {
		selected: 28,
		package_limit: 20,
		included: 20,
		extra: 8,
		unit_price: 6000,
		total: 48000,
		remaining: 0,
		at_limit: false,
		needs_payment: true,
	},
	favorites: 46,
	rejected: 12,
};
/*
 * Skrzynka wyborów: dwa wybory zatwierdzone (jeden z dopłatą, jeden
 * mieszczący się w pakiecie) i dwa w toku. Taki właśnie zestaw stanów
 * fotograf ma naprawdę — ekran musi wyglądać dobrze w każdym z nich.
 */
const INBOX = [
	{
		id: '01JD00000000000000000001',
		status: 'submitted',
		gallery_id: '01JCXYZ00000000000000001',
		gallery: 'Ślub Marty i Piotra',
		client: 'Marta Nowak',
		submitted_at: '2026-09-11 18:40:00',
		tally: {
			selected: 28,
			package_limit: 20,
			included: 20,
			extra: 8,
			total: 48000,
			needs_payment: true,
		},
	},
	{
		id: '01JD00000000000000000002',
		status: 'submitted',
		gallery_id: '01JCXYZ00000000000000002',
		gallery: 'Chrzciny Zosi',
		client: 'Anna Lis',
		submitted_at: '2026-09-10 09:15:00',
		tally: {
			selected: 15,
			package_limit: 20,
			included: 15,
			extra: 0,
			total: 0,
			needs_payment: false,
		},
	},
	{
		id: '01JD00000000000000000003',
		status: 'open',
		gallery_id: '01JCXYZ00000000000000003',
		gallery: 'Sesja rodzinna Kowalskich',
		client: 'Piotr Kowalski',
		submitted_at: null,
		tally: null,
	},
	{
		id: '01JD00000000000000000004',
		status: 'reopened',
		gallery_id: '01JCXYZ00000000000000004',
		gallery: 'Plener w Kazimierzu',
		client: null,
		submitted_at: null,
		tally: null,
	},
];

const INBOX_SUMMARY = { submitted: 2, in_progress: 2, due: 48000 };

/*
 * Paczka plików. Zaczyna jako gotowa, żeby podgląd pokazywał od razu stan,
 * w którym fotograf wydaje link — a zlecenie pakowania przestawia ją
 * w pakowanie i licznik rusza.
 */
const ARCHIVE = {
	status: 'ready',
	packed: 28,
	total: 28,
	bytes: 117_600_000,
	ready_at: '2026-09-13 17:40:00',
	error: null,
};

const UPLOADS = new Map();
const CHUNKS = new Map();

const ASSETS = Array.from( { length: 420 }, ( _, index ) => ( {
	id: `01JD${ String( index ).padStart( 22, '0' ) }`,
	name: `DSC_${ String( 1000 + index ) }.jpg`,
	// Co dziesiąte zdjęcie czeka na warianty — tak wygląda galeria zaraz
	// po wysłaniu, gdy kolejka jeszcze pracuje.
	status: index % 10 === 3 ? 'pending' : 'ready',
	width: 3000,
	height: 2000,
	bytes: 4_200_000 + index * 1000,
	sort_order: index,
	thumb: index % 10 === 3 ? null : thumbFor( index ),
} ) );

const json = ( payload ) =>
	new Response( JSON.stringify( payload ), {
		status: 200,
		headers: { 'Content-Type': 'application/json' },
	} );

function matches( text, term ) {
	return String( text ).toLocaleLowerCase( 'pl' ).includes( term.toLocaleLowerCase( 'pl' ) );
}

window.fetch = async ( input, init ) => {
	const url = new URL( input instanceof Request ? input.url : String( input ), window.location.origin );
	const path = url.pathname.split( '/kadr/v1/' ).pop() || '';
	const query = url.searchParams;
	const method = ( init?.method || 'GET' ).toUpperCase();

	// Krótkie opóźnienie, żeby w podglądzie widać było szkielety ładowania —
	// bez niego stan „loading" migałby i nigdy nie dałoby się go obejrzeć.
	await new Promise( ( resolve ) => setTimeout( resolve, 260 ) );

	// --- Wysyłanie zdjęć ------------------------------------------------
	//
	// Atrapa SPRAWDZA skrót przysłany przez przeglądarkę, licząc go
	// niezależnie przez SubtleCrypto. Dzięki temu test w przeglądarce
	// weryfikuje naszą własną implementację SHA-256 na prawdziwym pliku,
	// a nie tylko na wektorach testowych.
	if ( /^galleries\/[^/]+\/uploads$/.test( path ) && 'POST' === method ) {
		const body = JSON.parse( init?.body || '{}' );
		const chunkBytes = 5 * 1024 * 1024;

		UPLOADS.set( 'upload-' + body.hash.slice( 0, 8 ), { hash: body.hash, chunks: [] } );

		return json( {
			data: {
				upload_id: '01JU' + body.hash.slice( 0, 22 ).toUpperCase().replace( /[^0-9A-HJKMNP-TV-Z]/g, '0' ),
				gallery_id: path.split( '/' )[ 1 ],
				chunk_bytes: chunkBytes,
				chunk_count: Math.max( 1, Math.ceil( body.bytes / chunkBytes ) ),
				duplicate_of: null,
			},
			meta: {},
		} );
	}

	if ( /^uploads\/[^/]+\/chunks\/\d+$/.test( path ) && 'PUT' === method ) {
		const uploadId = path.split( '/' )[ 1 ];
		const parts = CHUNKS.get( uploadId ) || [];

		parts.push( new Uint8Array( await new Response( init.body ).arrayBuffer() ) );
		CHUNKS.set( uploadId, parts );

		return json( { data: { index: Number( path.split( '/' )[ 3 ] ) }, meta: {} } );
	}

	if ( /^uploads\/[^/]+\/complete$/.test( path ) && 'POST' === method ) {
		const uploadId = path.split( '/' )[ 1 ];
		const body = JSON.parse( init?.body || '{}' );
		const parts = CHUNKS.get( uploadId ) || [];

		const merged = new Blob( parts );
		const digest = await crypto.subtle.digest( 'SHA-256', await merged.arrayBuffer() );
		const expected = Array.from( new Uint8Array( digest ) )
			.map( ( byte ) => byte.toString( 16 ).padStart( 2, '0' ) )
			.join( '' );

		CHUNKS.delete( uploadId );

		if ( expected !== body.hash ) {
			return json(
				{
					code: 'kadr_upload_corrupted',
					message: 'Plik dotarł uszkodzony.',
					data: { status: 400, expected, got: body.hash },
				},
				400
			);
		}

		ASSETS.unshift( {
			id: '01JE' + String( ASSETS.length ).padStart( 22, '0' ),
			name: body.filename,
			status: 'pending',
			width: 3000,
			height: 2000,
			bytes: merged.size,
			sort_order: 0,
			thumb: null,
		} );

		return json( { data: { id: body.filename }, meta: {} }, 201 );
	}

	// --- Paczka plików ---------------------------------------------------
	if ( /^galleries\/[^/]+\/archive\/notify$/.test( path ) ) {
		return json( { data: { to: 'marta.nowak@example.test' }, meta: {} } );
	}

	if ( /^galleries\/[^/]+\/archive\/link$/.test( path ) ) {
		return json(
			{
				data: {
					url: 'https://studio.example/d/9f2c4a1b8e7d6c5a4b3e2d1c0f9a8b7c6d5e4f3a2b1c0d9e',
					expires_at: '2026-09-14 18:00:00',
					bytes: ARCHIVE.bytes,
					filename: 'slub-marty-i-piotra-wybrane.zip',
				},
				meta: {},
			},
			201
		);
	}

	if ( /^galleries\/[^/]+\/archive$/.test( path ) ) {
		if ( 'POST' === method ) {
			// Zlecenie zaczyna pakowanie od zera; kolejne odpytania
			// pokazują postęp tak, jak robi to prawdziwa kolejka.
			ARCHIVE.status = 'pending';
			ARCHIVE.packed = 0;
			ARCHIVE.bytes = 0;

			return json( { data: { archive_id: '01JD00000000000000000009', total: ARCHIVE.total, status: 'pending' }, meta: {} }, 202 );
		}

		if ( 'pending' === ARCHIVE.status || 'packing' === ARCHIVE.status ) {
			ARCHIVE.status = 'packing';
			// Porcja mniejsza niż produkcyjna (50), żeby podgląd pokazał
			// stan pakowania, a nie przeskoczył od razu do gotowego.
			ARCHIVE.packed = Math.min( ARCHIVE.total, ARCHIVE.packed + 6 );
			ARCHIVE.bytes = ARCHIVE.packed * 4_200_000;

			if ( ARCHIVE.packed >= ARCHIVE.total ) {
				ARCHIVE.status = 'ready';
				ARCHIVE.ready_at = '2026-09-13 17:40:00';
			}
		}

		return json( { data: { ...ARCHIVE }, meta: {} } );
	}

	// --- Kolejność kadrów ------------------------------------------------
	if ( /^galleries\/[^/]+\/assets\/order$/.test( path ) ) {
		const body = JSON.parse( init?.body || '{}' );
		const moving = new Set( body.move || [] );
		const rest = ASSETS.filter( ( asset ) => ! moving.has( asset.id ) );
		const picked = ASSETS.filter( ( asset ) => moving.has( asset.id ) );
		const target = body.before || '';
		const next = [];

		for ( const asset of rest ) {
			if ( asset.id === target ) {
				next.push( ...picked );
			}

			next.push( asset );
		}

		if ( '' === target ) {
			next.push( ...picked );
		}

		ASSETS.length = 0;
		ASSETS.push( ...next );
		ASSETS.forEach( ( asset, index ) => {
			asset.sort_order = index;
		} );

		return json( { data: { moved: picked.length }, meta: {} } );
	}

	// --- Skrzynka wyborów -----------------------------------------------
	if ( 'selections' === path ) {
		return json( { data: INBOX, meta: INBOX_SUMMARY } );
	}

	// --- Wybór klientki -------------------------------------------------
	if ( /^galleries\/[^/]+\/selection$/.test( path ) ) {
		if ( 'DELETE' === method ) {
			SELECTION.status = 'reopened';

			return json( { data: SELECTION, meta: {} } );
		}

		return json( { data: SELECTION, meta: {} } );
	}

	// --- Linki dla klienta ----------------------------------------------
	if ( /^galleries\/[^/]+\/access$/.test( path ) ) {
		if ( 'POST' === method ) {
			const body = JSON.parse( init?.body || '{}' );
			const link = {
				id: LINKS.length + 1,
				has_pin: Boolean( body.pin ),
				opened: 0,
				expires_at: '2026-12-12 23:59:59',
				revoked_at: null,
				active: true,
				created_at: '2026-09-13 10:00:00',
			};

			LINKS.unshift( link );

			return json(
				{
					data: {
						id: link.id,
						url: 'https://studio.example/g/pOdGlAd-ToKeN-dEmOnStRaCyJnY-0000000000',
						has_pin: link.has_pin,
						expires_in: 90,
					},
					meta: {},
				},
				201
			);
		}

		return json( { data: LINKS, meta: { count: LINKS.length, next_cursor: null, has_more: false } } );
	}

	if ( /^galleries\/[^/]+\/access\/\d+$/.test( path ) && 'DELETE' === method ) {
		const id = Number( path.split( '/' ).pop() );
		const link = LINKS.find( ( row ) => row.id === id );

		if ( link ) {
			link.active = false;
			link.revoked_at = '2026-09-13 11:00:00';
		}

		return new Response( null, { status: 204 } );
	}

	// Zdjęcia galerii: `galleries/{id}/assets`.
	if ( /^galleries\/[^/]+\/assets/.test( path ) ) {
		const page = Number( query.get( 'page' ) || 1 );
		const size = 100;
		const slice = ASSETS.slice( ( page - 1 ) * size, page * size );

		return json( {
			data: slice,
			meta: { count: slice.length, total: ASSETS.length, page, has_more: page * size < ASSETS.length },
		} );
	}

	if ( path.startsWith( 'assets/' ) && 'DELETE' === method ) {
		const id = path.split( '/' )[ 1 ];
		const index = ASSETS.findIndex( ( row ) => row.id === id );

		if ( index >= 0 ) {
			ASSETS.splice( index, 1 );
		}

		return new Response( null, { status: 204 } );
	}

	if ( path.startsWith( 'today' ) ) {
		return json( { data: TODAY, meta: {} } );
	}

	if ( path.startsWith( 'galleries' ) && 'GET' !== method ) {
		const body = JSON.parse( init?.body || '{}' );

		if ( path.endsWith( '/publish' ) ) {
			const id = path.split( '/' )[ 1 ];
			const gallery = GALLERIES.find( ( row ) => row.id === id );

			if ( gallery && 0 === gallery.photos ) {
				return json(
					{
						code: 'kadr_gallery_empty',
						message: 'Galeria nie ma jeszcze zdjęć. Wyślij je, zanim wyślesz ją klientowi.',
						data: { status: 422 },
					},
					422
				);
			}

			if ( gallery ) {
				gallery.status = 'published';
			}

			return json( { data: { id }, meta: {} }, 200 );
		}

		if ( 'DELETE' === method ) {
			const id = path.split( '/' )[ 1 ];
			const index = GALLERIES.findIndex( ( row ) => row.id === id );

			if ( index >= 0 ) {
				GALLERIES.splice( index, 1 );
			}

			return new Response( null, { status: 204 } );
		}

		if ( 'PATCH' === method ) {
			const id = path.split( '/' )[ 1 ];
			const gallery = GALLERIES.find( ( row ) => row.id === id );

			if ( gallery ) {
				Object.assign( gallery, body );
			}

			return json( { data: { id }, meta: {} }, 200 );
		}

		// Limit planu w podglądzie: dziesiąta galeria już się nie mieści,
		// żeby dało się zobaczyć komunikat reguły biznesowej.
		if ( GALLERIES.length >= 10 ) {
			return json(
				{
					code: 'kadr_limit_reached',
					message: 'Plan Studio obejmuje 10 galerii. Zarchiwizuj zakończoną albo zmień plan.',
					data: { status: 422, limit: 10 },
				},
				422
			);
		}

		const created = {
			id: `01JB00000000000000000000${ String( GALLERIES.length + 10 ) }`,
			title: body.title,
			slug: 'nowa-galeria',
			status: 'draft',
			theme: body.theme || 'noir',
			client: null,
			client_id: body.client_id || null,
			photos: 0,
			package_limit: body.package_limit ?? null,
			extra_photo_price: body.extra_photo_price ?? null,
			allow_download: Boolean( body.allow_download ),
			published_at: null,
			expires_at: body.expires_at || null,
			created_at: '2026-09-13 09:00:00',
		};

		GALLERIES.unshift( created );

		return json( { data: created, meta: {} }, 201 );
	}

	if ( path.startsWith( 'galleries' ) ) {
		const status = query.get( 'status' ) || '';
		const term = query.get( 'q' ) || '';

		const rows = GALLERIES.filter(
			( row ) =>
				( '' === status || row.status === status ) &&
				( '' === term || matches( row.title, term ) )
		);

		return json( { data: rows, meta: { count: rows.length, next_cursor: null, has_more: false } } );
	}

	if ( path.startsWith( 'clients' ) ) {
		const term = query.get( 'q' ) || '';
		const rows = CLIENTS.filter( ( row ) => '' === term || matches( row.last_name, term ) );

		return json( { data: rows, meta: { count: rows.length, next_cursor: null, has_more: false } } );
	}

	return new Response( JSON.stringify( { code: 'kadr_not_found', message: 'Nie znaleziono.' } ), {
		status: 404,
		headers: { 'Content-Type': 'application/json' },
	} );
};
