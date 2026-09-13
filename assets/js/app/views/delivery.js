/**
 * Pliki do pobrania.
 *
 * TO JEST TRZECI Z CZTERECH ETAPÓW, NA KTÓRYCH PRODUKT ZARABIA — i ten,
 * który generuje polecenia. Klientka opowiada znajomym o momencie, w którym
 * dostała zdjęcia, nie o tym, w którym je wybierała.
 *
 * Dziś kończy się to Dyskiem Google i linkiem, który wygasa, zanim klientka
 * zdąży pobrać.
 *
 * Ekran ma pokazywać POSTĘP, nie kręciołek: pakowanie wesela trwa
 * kilkanaście minut, a fotograf w tym czasie zamknie kartę i wróci. Bez
 * widocznego „340 z 1200" nie wie, czy coś się dzieje, czy się zawiesiło.
 */
import { html, useState, useEffect, useCallback, useRef, formatBytes, formatNumber, Plural, __ } from '../runtime.js';
import { api } from '../api.js';
import { toast } from '../toast.js';

/** Jak często pytamy o postęp, gdy paczka się pakuje. */
const POLL_MS = 3000;

export function DeliveryPanel( { galleryId } ) {
	const [ scope, setScope ] = useState( 'selected' );
	const [ state, setState ] = useState( { status: 'none' } );
	const [ issued, setIssued ] = useState( null );
	const [ busy, setBusy ] = useState( false );
	const timer = useRef( null );

	const load = useCallback(
		async ( which ) => {
			try {
				const { data } = await api.get( `galleries/${ galleryId }/archive`, { params: { scope: which } } );

				setState( data );

				return data;
			} catch ( error ) {
				return null;
			}
		},
		[ galleryId ]
	);

	useEffect( () => {
		// Zmiana zakresu to inna paczka — stary link przestaje pasować.
		setIssued( null );
		load( scope );

		return () => clearTimeout( timer.current );
	}, [ scope, load ] );

	/**
	 * Odpytywanie, dopóki paczka się pakuje.
	 *
	 * `setTimeout` po ODPOWIEDZI, nie `setInterval`: gdy serwer zwalnia,
	 * interwał nakłada żądania jedno na drugie i dobija go do reszty.
	 */
	useEffect( () => {
		clearTimeout( timer.current );

		if ( 'pending' !== state.status && 'packing' !== state.status ) {
			return;
		}

		timer.current = setTimeout( () => load( scope ), POLL_MS );

		return () => clearTimeout( timer.current );
	}, [ state.status, state.packed, scope, load ] );

	const prepare = async () => {
		setBusy( true );
		setIssued( null );

		try {
			await api.post( `galleries/${ galleryId }/archive`, { scope } );
			toast.success( __( 'Pakowanie ruszyło. Możesz zamknąć tę kartę — damy znać, gdy będzie gotowe.' ) );
			load( scope );
		} catch ( error ) {
			toast.error( error );
		} finally {
			setBusy( false );
		}
	};

	/**
	 * Powiadomienie klientki.
	 *
	 * Wysyłka jest jawną decyzją fotografa, nie efektem ubocznym spakowania:
	 * to on decyduje, kiedy klientka ma dostać wiadomość, i to jego nazwisko
	 * jest pod nią podpisane.
	 */
	const notifyClient = async () => {
		setBusy( true );

		try {
			const { data } = await api.post( `galleries/${ galleryId }/archive/notify`, { scope } );

			toast.success( `${ __( 'Wysłaliśmy wiadomość na adres' ) } ${ data.to }` );
		} catch ( error ) {
			toast.error( error );
		} finally {
			setBusy( false );
		}
	};

	const issue = async () => {
		setBusy( true );

		try {
			const { data } = await api.post( `galleries/${ galleryId }/archive/link`, { scope } );

			setIssued( data );
		} catch ( error ) {
			toast.error( error );
		} finally {
			setBusy( false );
		}
	};

	const packing = 'pending' === state.status || 'packing' === state.status;
	const ready = 'ready' === state.status;

	return html`
		<section class="kadr-panel" aria-labelledby="kadr-delivery">
			<div class="kadr-upload__head">
				<h2 class="kadr-panel__title" id="kadr-delivery">${ __( 'Pliki do pobrania' ) }</h2>
				${ ready
					? html`<span class="kadr-status kadr-status--published">${ __( 'Gotowe' ) }</span>`
					: null }
			</div>

			<div class="kadr-segmented" role="group" aria-label=${ __( 'Co spakować' ) }>
				<button
					type="button"
					class="kadr-segmented__item"
					aria-pressed=${ 'selected' === scope }
					onClick=${ () => setScope( 'selected' ) }
				>${ __( 'Wybrane zdjęcia' ) }</button>
				<button
					type="button"
					class="kadr-segmented__item"
					aria-pressed=${ 'everything' === scope }
					onClick=${ () => setScope( 'everything' ) }
				>${ __( 'Cała galeria' ) }</button>
			</div>

			${ packing ? html`<${Progress} state=${ state } />` : null }

			${ ready
				? html`<p class="kadr-field__hint">
						${ `${ formatNumber( state.total ) } ${ Plural.photos( state.total ) } · ${ formatBytes( state.bytes ) }` }
				  </p>`
				: null }

			${ 'failed' === state.status
				? html`<p class="kadr-field__error">
						${ state.error || __( 'Nie udało się spakować plików. Spróbuj jeszcze raz.' ) }
				  </p>`
				: null }

			${ 'none' === state.status
				? html`<p class="kadr-field__hint">
						${ __( 'Paczka powstaje w tle. Przy weselu to kilkanaście minut — nie musisz tu czekać.' ) }
				  </p>`
				: null }

			<div class="kadr-form__actions">
				<button
					type="button"
					class="kadr-btn ${ ready ? 'kadr-btn--secondary' : 'kadr-btn--primary' }"
					disabled=${ busy || packing }
					onClick=${ prepare }
				>
					${ ready ? __( 'Spakuj jeszcze raz' ) : __( 'Przygotuj pliki' ) }
				</button>

				${ ready
					? html`<button type="button" class="kadr-btn kadr-btn--secondary" disabled=${ busy } onClick=${ issue }>
							${ __( 'Wydaj link' ) }
					  </button>
					  <button type="button" class="kadr-btn kadr-btn--primary" disabled=${ busy } onClick=${ notifyClient }>
							${ __( 'Powiadom klientkę' ) }
					  </button>`
					: null }
			</div>

			${ issued ? html`<${IssuedDownload} link=${ issued } />` : null }
		</section>
	`;
}

function Progress( { state } ) {
	const total = state.total || 0;
	const packed = state.packed || 0;
	const percent = total > 0 ? Math.round( ( packed / total ) * 100 ) : 0;

	return html`
		<div class="kadr-meter kadr-meter--tall">
			<div class="kadr-meter__fill" style=${ `width:${ percent }%` }></div>
		</div>
		<p
			class="kadr-upload__total"
			role="status"
			aria-live="polite"
		>
			${ 'pending' === state.status
				? __( 'Zaczynamy pakować…' )
				: `${ __( 'Pakuję' ) } ${ formatNumber( packed ) } ${ __( 'z' ) } ${ formatNumber( total ) }` }
		</p>
	`;
}

/**
 * Wydany link.
 *
 * Pokazujemy go raz i mówimy wprost, że drugi raz go nie będzie — tak samo
 * jak przy linku do galerii. Jawna wartość tokenu nie istnieje w bazie.
 */
function IssuedDownload( { link } ) {
	const [ copied, setCopied ] = useState( false );

	const copy = async () => {
		try {
			await navigator.clipboard.writeText( link.url );
			setCopied( true );
			toast.success( __( 'Link skopiowany.' ) );
		} catch ( error ) {
			toast.warning( __( 'Nie udało się skopiować. Zaznacz adres i skopiuj ręcznie.' ) );
		}
	};

	return html`
		<div class="kadr-panel kadr-panel--accent" style="margin-top:16px">
			<h3 class="kadr-panel__title">${ __( 'Link do pobrania' ) }</h3>
			<p class="kadr-field__hint" style="margin-bottom:12px">
				${ __( 'Wyślij go klientce. Działa przez dobę i nie da się go odczytać później — po wygaśnięciu wydaj nowy.' ) }
			</p>

			<div class="kadr-share__row">
				<input
					class="kadr-field__input"
					readonly
					value=${ link.url }
					onFocus=${ ( event ) => event.target.select() }
				/>
				<button
					type="button"
					class="kadr-btn ${ copied ? 'kadr-btn--secondary' : 'kadr-btn--primary' }"
					onClick=${ copy }
				>${ copied ? __( 'Skopiowano' ) : __( 'Kopiuj' ) }</button>
			</div>

			<p class="kadr-field__hint">
				${ `${ link.filename } · ${ formatBytes( link.bytes ) }` }
			</p>
		</div>
	`;
}
