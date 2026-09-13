/**
 * Galeria klienta — lightbox i doczytywanie kadrów.
 *
 * Bez frameworka i bez zależności. Powód nie jest ideologiczny: ten skrypt
 * ładuje się na telefonie, wieczorem, czasem na słabym zasięgu, a wszystko,
 * co robi, to obsługa jednego dialogu i dopisywanie HTML-a przysłanego
 * przez serwer. Preact nie dodałby tu nic poza kilobajtami (ADR-018 dotyczy
 * panelu, nie tego widoku).
 *
 * Siatka DZIAŁA BEZ TEGO PLIKU: zdjęcia są w dokumencie, mają wymiary
 * i ładują się same. Skrypt dokłada powiększenie i wygodę.
 */

const grid = document.getElementById( 'zdjecia' );

if ( grid ) {
	setUpImageReveal();
	setUpLightbox();
	setUpLoadMore();
}

/**
 * Zdjęcie pojawia się dopiero, gdy naprawdę się wczyta.
 *
 * Do tego czasu widać miniaturę zastępczą wpisaną w tło kadru. Bez tego
 * przeglądarka pokazuje zdjęcie liniami w miarę pobierania, co przy serii
 * portretów wygląda jak awaria.
 */
function setUpImageReveal() {
	const mark = ( image ) => image.classList.add( 'kadr-g-item__image--loaded' );

	const observe = ( image ) => {
		if ( image.complete && image.naturalWidth > 0 ) {
			mark( image );
			return;
		}

		image.addEventListener( 'load', () => mark( image ), { once: true } );

		// Nieudane zdjęcie też odsłaniamy: lepiej pokazać złamaną ikonę
		// niż zostawić kadr, który udaje, że się jeszcze ładuje.
		image.addEventListener( 'error', () => mark( image ), { once: true } );
	};

	grid.querySelectorAll( '.kadr-g-item__image' ).forEach( observe );

	// Kadry doczytane później też muszą się odsłonić.
	new MutationObserver( ( records ) => {
		records.forEach( ( record ) =>
			record.addedNodes.forEach( ( node ) => {
				if ( node.querySelectorAll ) {
					node.querySelectorAll( '.kadr-g-item__image' ).forEach( observe );
				}
			} )
		);
	} ).observe( grid, { childList: true } );
}

/* ---------------------------------------------------------------------
 * Lightbox
 * ------------------------------------------------------------------- */

let dialog = null;
let current = 0;

function photos() {
	return Array.from( grid.querySelectorAll( '.kadr-g-item' ) );
}

function setUpLightbox() {
	grid.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '.kadr-g-item__button' );

		if ( ! button ) {
			return;
		}

		open( photos().indexOf( button.closest( '.kadr-g-item' ) ) );
	} );
}

function ensureDialog() {
	if ( dialog ) {
		return dialog;
	}

	dialog = document.createElement( 'dialog' );
	dialog.className = 'kadr-g-lightbox';
	dialog.setAttribute( 'aria-label', document.title );

	dialog.innerHTML = `
		<div class="kadr-g-lightbox__bar">
			<span class="kadr-g-lightbox__counter" aria-live="polite"></span>
			<button class="kadr-g-lightbox__close" type="button" data-close aria-label="Zamknij">×</button>
		</div>
		<div class="kadr-g-lightbox__stage">
			<button class="kadr-g-nav kadr-g-nav--prev" type="button" data-step="-1" aria-label="Poprzednie zdjęcie">‹</button>
			<img class="kadr-g-lightbox__image" alt="">
			<button class="kadr-g-nav kadr-g-nav--next" type="button" data-step="1" aria-label="Następne zdjęcie">›</button>
		</div>
		<div class="kadr-g-lightbox__actions">
			<a class="kadr-g-btn kadr-g-btn--solid" data-download hidden download>Pobierz</a>
			<button class="kadr-g-btn" type="button" data-fullscreen>Pełny ekran</button>
		</div>
	`;

	document.body.appendChild( dialog );

	dialog.addEventListener( 'click', ( event ) => {
		const step = event.target.closest( '[data-step]' );

		if ( step ) {
			move( Number( step.dataset.step ) );
			return;
		}

		if ( event.target.closest( '[data-close]' ) ) {
			dialog.close();
			return;
		}

		if ( event.target.closest( '[data-fullscreen]' ) ) {
			toggleFullscreen();
			return;
		}

		// Kliknięcie w tło zamyka — na desktopie to najszybszy sposób wyjścia.
		if ( event.target === dialog || event.target.classList.contains( 'kadr-g-lightbox__stage' ) ) {
			dialog.close();
		}
	} );

	dialog.addEventListener( 'keydown', ( event ) => {
		if ( 'ArrowRight' === event.key ) {
			event.preventDefault();
			move( 1 );
		}

		if ( 'ArrowLeft' === event.key ) {
			event.preventDefault();
			move( -1 );
		}
	} );

	bindSwipe( dialog );

	// Po zamknięciu fokus wraca na kadr, z którego weszliśmy — inaczej
	// osoba korzystająca z klawiatury ląduje na początku strony.
	dialog.addEventListener( 'close', () => {
		photos()[ current ]?.querySelector( '.kadr-g-item__button' )?.focus();
	} );

	return dialog;
}

function open( index ) {
	if ( index < 0 ) {
		return;
	}

	const element = ensureDialog();

	current = index;
	show();

	if ( ! element.open ) {
		// `showModal` daje pułapkę fokusu, Escape i tło modalne bez ani jednej
		// linii własnego kodu — a przeglądarka robi to poprawnie.
		element.showModal();
	}
}

function move( step ) {
	const all = photos();
	const next = current + step;

	if ( next < 0 || next >= all.length ) {
		return;
	}

	current = next;
	show();
}

function show() {
	const all = photos();
	const item = all[ current ];

	if ( ! item ) {
		return;
	}

	const button = item.querySelector( '.kadr-g-item__button' );
	const thumb = item.querySelector( '.kadr-g-item__image' );
	const image = dialog.querySelector( '.kadr-g-lightbox__image' );

	// Adres pełnej wersji przychodzi z serwera w atrybucie, a nie z przeróbki
	// adresu miniatury — inaczej zmiana ścieżki psuje lightbox po cichu.
	image.src = button.dataset.full || thumb.src;
	image.alt = thumb.alt;

	dialog.querySelector( '.kadr-g-lightbox__counter' ).textContent = `${ current + 1 } / ${ all.length }`;

	dialog.querySelector( '.kadr-g-nav--prev' ).disabled = 0 === current;
	dialog.querySelector( '.kadr-g-nav--next' ).disabled = current === all.length - 1;

	// Pobieranie pokazujemy tylko wtedy, gdy fotograf je włączył. Przy
	// proofingu jest wyłączone celowo: plik pobrany przed wyborem to plik,
	// za który nikt nie dopłaci.
	const download = dialog.querySelector( '[data-download]' );

	if ( 'true' === grid.dataset.download && button.dataset.file ) {
		download.hidden = false;
		download.href = button.dataset.file;
	} else {
		download.hidden = true;
	}
}

function toggleFullscreen() {
	if ( document.fullscreenElement ) {
		document.exitFullscreen();
		return;
	}

	dialog.requestFullscreen?.().catch( () => {
		// iOS na telefonie nie pozwala na pełny ekran dla dowolnego elementu.
		// Brak reakcji jest lepszy niż komunikat o błędzie, którego klientka
		// i tak nie może naprawić.
	} );
}

/**
 * Przesunięcie palcem.
 *
 * Próg 40 px i wymóg przewagi ruchu poziomego nad pionowym — bez tego
 * przewijanie kciukiem przeskakuje zdjęcia.
 */
function bindSwipe( element ) {
	let startX = 0;
	let startY = 0;

	element.addEventListener(
		'touchstart',
		( event ) => {
			startX = event.touches[ 0 ].clientX;
			startY = event.touches[ 0 ].clientY;
		},
		{ passive: true }
	);

	element.addEventListener(
		'touchend',
		( event ) => {
			const deltaX = event.changedTouches[ 0 ].clientX - startX;
			const deltaY = event.changedTouches[ 0 ].clientY - startY;

			if ( Math.abs( deltaX ) < 40 || Math.abs( deltaX ) < Math.abs( deltaY ) ) {
				return;
			}

			move( deltaX < 0 ? 1 : -1 );
		},
		{ passive: true }
	);
}

/* ---------------------------------------------------------------------
 * Doczytywanie kadrów
 * ------------------------------------------------------------------- */

function setUpLoadMore() {
	const button = document.getElementById( 'kadr-g-more' );

	if ( ! button ) {
		return;
	}

	let page = 1;
	let busy = false;

	const load = async () => {
		if ( busy ) {
			return;
		}

		busy = true;
		button.disabled = true;

		try {
			const response = await fetch( `${ window.location.pathname.replace( /\/$/, '' ) }/dalej/${ page + 1 }`, {
				credentials: 'same-origin',
			} );

			if ( ! response.ok ) {
				throw new Error( String( response.status ) );
			}

			grid.insertAdjacentHTML( 'beforeend', await response.text() );
			page++;

			if ( '1' !== response.headers.get( 'X-Kadr-More' ) ) {
				button.parentElement.remove();
				observer?.disconnect();
			}
		} catch ( error ) {
			// Zerwane połączenie na 4G nie jest niczym nadzwyczajnym.
			// Przycisk wraca, żeby dało się spróbować jeszcze raz.
			button.textContent = 'Nie udało się wczytać. Spróbuj ponownie';
		} finally {
			busy = false;
			button.disabled = false;
		}
	};

	button.addEventListener( 'click', load );

	// Doczytywanie samo, gdy przycisk zbliża się do ekranu. Przycisk zostaje
	// dla osób korzystających z klawiatury i czytnika ekranu — i jako wyjście
	// awaryjne, gdy obserwator nie zadziała.
	const observer = new IntersectionObserver(
		( entries ) => entries.forEach( ( entry ) => entry.isIntersecting && load() ),
		{ rootMargin: '800px' }
	);

	observer.observe( button );
}

/* ---------------------------------------------------------------------
 * Wybór zdjęć
 *
 * Kliknięcie ma być natychmiastowe: stan zmienia się od razu, a żądanie
 * leci w tle. Klientka przechodzi galerię w tempie przewijania i czekanie
 * na odpowiedź serwera przy każdym serduszku zamieniłoby to w mękę.
 *
 * Licznik jest jednak PRZELICZANY PRZEZ SERWER i nadpisywany jego wynikiem.
 * Kwota dopłaty nie może zależeć od tego, co policzyła przeglądarka.
 * ------------------------------------------------------------------- */

if ( grid?.dataset.selection ) {
	setUpChoices();
	setUpSubmit();
}

function selectionBase() {
	// Adres endpointów wyboru wyprowadzamy z adresu galerii: token jest
	// jedynym uprawnieniem klientki, a ma go już w pasku adresu.
	const token = window.location.pathname.replace( /\/$/, '' ).split( '/g/' ).pop().split( '/' )[ 0 ];

	return `/wp-json/kadr/v1/shared/${ token }/selection`;
}

function setUpChoices() {
	grid.addEventListener( 'click', async ( event ) => {
		const choice = event.target.closest( '.kadr-g-choice' );

		if ( ! choice ) {
			return;
		}

		// Kliknięcie w serduszko nie może otwierać lightboxa.
		event.stopPropagation();
		event.preventDefault();

		const item = choice.closest( '.kadr-g-item' );
		const button = item.querySelector( '.kadr-g-item__button' );
		const wanted = choice.dataset.choice;
		const isActive = 'true' === choice.getAttribute( 'aria-pressed' );
		const next = isActive ? '' : wanted;

		applyState( item, next );

		try {
			const response = await fetch( `${ selectionBase() }/${ choice.dataset.asset }`, {
				method: 'PUT',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( { state: '' === next ? null : next } ),
			} );

			const payload = await response.json();

			if ( ! response.ok ) {
				throw new Error( payload?.message || 'blad' );
			}

			paintCounter( payload.data );
		} catch ( error ) {
			// Cofamy do stanu sprzed kliknięcia: interfejs nie może twierdzić,
			// że zdjęcie jest wybrane, jeśli serwer o tym nie wie.
			applyState( item, isActive ? wanted : '' );
			announce( error.message );
		}
	} );
}

/**
 * @param {Element} item
 * @param {string} state
 */
function applyState( item, state ) {
	item.querySelector( '.kadr-g-item__button' ).dataset.state = state;

	item.querySelectorAll( '.kadr-g-choice' ).forEach( ( button ) => {
		button.setAttribute( 'aria-pressed', button.dataset.choice === state ? 'true' : 'false' );
	} );
}

/**
 * Przepisanie licznika wynikiem z serwera.
 *
 * Teksty budujemy z liczb przysłanych przez serwer, a nie sklejamy zdań
 * w przeglądarce — polska odmiana („1 zdjęcie”, „2 zdjęcia”, „5 zdjęć”)
 * i tak wymaga reguły, którą ma już PHP.
 */
function paintCounter( data ) {
	const counter = document.getElementById( 'kadr-g-count' );

	if ( ! counter || ! data?.tally ) {
		return;
	}

	const tally = data.tally;
	const main = counter.querySelector( '.kadr-g-count__main' );
	const detail = counter.querySelector( '.kadr-g-count__detail' );

	main.textContent = `${ plural( tally.selected, 'Wybrałaś %s zdjęcie', 'Wybrałaś %s zdjęcia', 'Wybrałaś %s zdjęć' ) }`;

	if ( ! detail ) {
		return;
	}

	if ( null === tally.package_limit ) {
		detail.textContent = '';
		return;
	}

	if ( tally.extra > 0 ) {
		detail.textContent =
			`${ photoCount( tally.included ) } w pakiecie · ${ photoCount( tally.extra ) } dodatkowo ` +
			`× ${ money( tally.unit_price ) } = ${ money( tally.total ) }`;

		return;
	}

	detail.textContent = 0 === tally.remaining
		? `Pakiet obejmuje ${ photoCount( tally.package_limit ) } — kolejne będą dodatkowo płatne`
		: `Pakiet obejmuje ${ photoCount( tally.package_limit ) } · zostało ${ photoCount( tally.remaining ) }`;
}

/** Polska odmiana liczebnika: 1 / 2–4 / 5+ z wyjątkiem nastek. */
function plural( count, one, few, many ) {
	const number = new Intl.NumberFormat( 'pl-PL' ).format( count );
	const last = count % 10;
	const teens = count % 100;

	if ( 1 === count ) {
		return one.replace( '%s', number );
	}

	if ( last >= 2 && last <= 4 && ( teens < 12 || teens > 14 ) ) {
		return few.replace( '%s', number );
	}

	return many.replace( '%s', number );
}

const photoCount = ( count ) => plural( count, '%s zdjęcie', '%s zdjęcia', '%s zdjęć' );

const money = ( minor ) =>
	`${ new Intl.NumberFormat( 'pl-PL', { maximumFractionDigits: minor % 100 === 0 ? 0 : 2 } ).format( minor / 100 ) } zł`;

function setUpSubmit() {
	const button = document.getElementById( 'kadr-g-submit' );

	if ( ! button ) {
		return;
	}

	button.addEventListener( 'click', async () => {
		button.disabled = true;

		try {
			const response = await fetch( selectionBase(), {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
			} );

			const payload = await response.json();

			if ( ! response.ok ) {
				throw new Error( payload?.message || 'blad' );
			}

			// Wybór wysłany: odświeżamy stronę, żeby serwer narysował stan
			// zamknięty. Prościej i pewniej niż przepisywanie go tutaj.
			window.location.reload();
		} catch ( error ) {
			announce( error.message );
			button.disabled = false;
		}
	} );
}

/**
 * Komunikat dla klientki.
 *
 * Bez biblioteki powiadomień: jeden element pod licznikiem, czytany przez
 * czytnik ekranu, znikający sam.
 */
function announce( message ) {
	const counter = document.getElementById( 'kadr-g-count' );

	if ( ! counter ) {
		return;
	}

	let note = counter.querySelector( '.kadr-g-count__note' );

	if ( ! note ) {
		note = document.createElement( 'p' );
		note.className = 'kadr-g-count__note';
		note.setAttribute( 'role', 'alert' );
		counter.querySelector( '.kadr-g-count__inner' ).appendChild( note );
	}

	note.textContent = message;

	clearTimeout( note.dataset.timer );
	note.dataset.timer = setTimeout( () => note.remove(), 6000 );
}
