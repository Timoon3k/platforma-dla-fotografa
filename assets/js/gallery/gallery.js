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
