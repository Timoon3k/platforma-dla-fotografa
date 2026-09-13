/**
 * Widok „Dzisiaj" — pierwszy ekran panelu.
 *
 * Jedno zapytanie do `/today`, nie sześć. Ten widok jest otwierany częściej
 * niż jakikolwiek inny, więc jego koszt mnoży się przez wszystko
 * (docs/PERFORMANCE.md §5).
 *
 * Nie ma tu ani jednej liczby, która nie przyszłaby z bazy. Puste studio
 * pokazuje zera i pusty stan — nigdy przykładowych danych (CLAUDE.md §9).
 */
import { html, useState, useEffect, formatNumber, formatBytes, __ } from '../runtime.js';
import { api, ApiError } from '../api.js';
import { EmptyPanel } from '../table.js';

export function TodayView() {
	const [ state, setState ] = useState( { status: 'loading', data: null, error: null } );

	useEffect( () => {
		const controller = new AbortController();

		api.get( 'today', { signal: controller.signal } )
			.then( ( { data } ) => setState( { status: 'ready', data, error: null } ) )
			.catch( ( error ) => {
				if ( 'AbortError' === error.name ) {
					return;
				}

				setState( { status: 'error', data: null, error } );
			} );

		return () => controller.abort();
	}, [] );

	if ( 'loading' === state.status ) {
		return html`<${TodaySkeleton} />`;
	}

	if ( 'error' === state.status ) {
		return html`<${LoadFailure} error=${ state.error } />`;
	}

	const { galleries, clients, storage, plan, expiring } = state.data;
	const nothingYet = 0 === galleries.draft + galleries.published + galleries.expired + galleries.archived;

	return html`
		<div class="kadr-view__head">
			<div>
				<h1 class="kadr-view__title">${ __( 'Dzisiaj' ) }</h1>
				<p class="kadr-view__subtitle">${ formatToday() }</p>
			</div>
		</div>

		<div class="kadr-tiles">
			<${Tile}
				label=${ __( 'Galerie opublikowane' ) }
				value=${ formatNumber( galleries.published ) }
				hint=${ galleries.draft > 0
					? `${ formatNumber( galleries.draft ) } ${ __( 'w szkicach' ) }`
					: __( 'Brak szkiców' ) }
			/>
			<${Tile}
				label=${ __( 'Klienci' ) }
				value=${ formatNumber( clients ) }
			/>
			<${Tile}
				label=${ __( 'Galerie w planie' ) }
				value=${ formatNumber( galleries.active ) }
				hint=${ null === plan.gallery_limit
					? __( 'Bez limitu' ) + ' · ' + plan.name
					: `${ __( 'z' ) } ${ formatNumber( plan.gallery_limit ) } ${ __( 'w planie' ) } ${ plan.name }` }
			/>
			<${StorageTile} storage=${ storage } plan=${ plan } />
		</div>

		${ expiring.length > 0 ? html`<${Expiring} items=${ expiring } />` : null }

		${ nothingYet
			? html`<${EmptyPanel}
					title=${ __( 'Jeszcze żadnej galerii' ) }
					text=${ __( 'Galeria to miejsce, w którym klient wybiera zdjęcia i dopłaca za te ponad pakiet. Pierwszą przygotujesz w kilka minut.' ) }
			  />`
			: null }
	`;
}

function Tile( { label, value, hint = '', tone = '' } ) {
	return html`
		<div class="kadr-tile">
			<span class="kadr-tile__label">${ label }</span>
			<span class="kadr-tile__value ${ tone }">${ value }</span>
			${ hint ? html`<span class="kadr-tile__hint">${ hint }</span>` : null }
		</div>
	`;
}

/**
 * Kafel miejsca na pliki.
 *
 * Wypełnienie paska liczy serwer (`used_ratio`), bo to on zna definicję
 * planu. Brak limitu to `null`, nie zero — plan bez ograniczenia nie może
 * wyglądać na zapełniony w stu procentach.
 */
function StorageTile( { storage, plan } ) {
	const ratio = storage.used_ratio;
	const percent = null === ratio ? null : Math.min( 100, Math.round( ratio * 100 ) );
	const tone = null !== percent && percent >= 85 ? 'kadr-tile__value--warning' : '';

	return html`
		<div class="kadr-tile">
			<span class="kadr-tile__label">${ __( 'Miejsce na pliki' ) }</span>
			<span class="kadr-tile__value ${ tone }">${ formatBytes( storage.used_bytes ) }</span>
			${ null !== percent
				? html`<div class="kadr-meter">
						<div
							class="kadr-meter__fill ${ percent >= 85 ? 'kadr-meter__fill--warning' : '' }"
							style=${ `width:${ percent }%` }
						></div>
				  </div>`
				: null }
			<span class="kadr-tile__hint">
				${ null === storage.limit_bytes
					? __( 'Bez limitu' )
					: `${ __( 'z' ) } ${ formatBytes( storage.limit_bytes ) } ${ __( 'w planie' ) } ${ plan.name }` }
			</span>
		</div>
	`;
}

/**
 * Galerie z kończącą się ważnością.
 *
 * To jedyna rzecz na tym ekranie, która wymaga reakcji dziś — wygaśnięcie
 * galerii oznacza, że klient traci dostęp do swoich zdjęć.
 */
function Expiring( { items } ) {
	return html`
		<section class="kadr-panel" aria-labelledby="kadr-expiring">
			<h2 class="kadr-panel__title" id="kadr-expiring">${ __( 'Ważność kończy się w tym tygodniu' ) }</h2>
			<ul class="kadr-panel__list">
				${ items.map(
					( item ) => html`
						<li key=${ item.id } class="kadr-panel__row">
							<span>${ item.title }</span>
							<span class="kadr-status kadr-status--warning">${ formatDate( item.expires_at ) }</span>
						</li>
					`
				) }
			</ul>
		</section>
	`;
}

function TodaySkeleton() {
	return html`
		<div aria-busy="true" aria-label=${ __( 'Wczytywanie' ) }>
			<div class="kadr-skeleton kadr-skeleton--title" style="margin-bottom:24px"></div>
			<div class="kadr-tiles">
				${ [ 0, 1, 2, 3 ].map(
					( index ) => html`
						<div class="kadr-tile" key=${ index }>
							<div class="kadr-skeleton kadr-skeleton--text" style="width:50%"></div>
							<div class="kadr-skeleton kadr-skeleton--title"></div>
						</div>
					`
				) }
			</div>
		</div>
	`;
}

/**
 * Błąd wczytania mówi, co się stało i co można zrobić.
 *
 * „Konto bez studia" to inna sytuacja niż awaria sieci i wymaga innej
 * odpowiedzi — dlatego kod błędu jest zachowany w `ApiError`.
 */
export function LoadFailure( { error, onRetry = null } ) {
	const noTenant = error instanceof ApiError && 'kadr_no_tenant' === error.code;

	return html`
		<${EmptyPanel}
			title=${ noTenant ? __( 'To konto nie ma studia' ) : __( 'Nie udało się wczytać danych' ) }
			text=${ noTenant
				? __( 'Panel działa w obrębie studia. To konto nie jest przypisane do żadnego — poproś właściciela studia o zaproszenie.' )
				: error.message }
			actionLabel=${ noTenant || ! error.isRetryable ? null : __( 'Spróbuj ponownie' ) }
			onAction=${ onRetry || ( () => window.location.reload() ) }
		/>
	`;
}

function formatToday() {
	return new Intl.DateTimeFormat( 'pl-PL', {
		weekday: 'long',
		day: 'numeric',
		month: 'long',
	} ).format( new Date() );
}

function formatDate( value ) {
	if ( ! value ) {
		return '';
	}

	// Daty z API są w UTC bez strefy — dopisujemy ją, żeby przeglądarka
	// nie potraktowała ich jako czasu lokalnego.
	const date = new Date( String( value ).replace( ' ', 'T' ) + 'Z' );

	return Number.isNaN( date.getTime() )
		? ''
		: new Intl.DateTimeFormat( 'pl-PL', { day: 'numeric', month: 'long' } ).format( date );
}
