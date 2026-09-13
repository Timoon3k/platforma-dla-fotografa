/**
 * Lista galerii.
 *
 * Filtrowanie, wyszukiwanie i paginację wykonuje serwer. Przy fotografie
 * z tysiącem galerii ściąganie wszystkiego, żeby przefiltrować w przeglądarce,
 * jest bez sensu — i przestaje działać dokładnie wtedy, gdy klient rośnie.
 */
import { html, useState, useEffect, useRef, useCallback, formatNumber, formatMoney, debounce, __ } from '../runtime.js';
import { api } from '../api.js';
import { DataTable } from '../table.js';
import { openDrawer } from '../drawer.js';
import { GalleryForm } from './gallery-form.js';
import { LoadFailure } from './today.js';

const STATUSES = [
	{ value: '', label: 'Wszystkie' },
	{ value: 'published', label: 'Opublikowane' },
	{ value: 'draft', label: 'Szkice' },
	{ value: 'expired', label: 'Wygasłe' },
	{ value: 'archived', label: 'Zarchiwizowane' },
];

const STATUS_TONE = {
	draft: 'draft',
	published: 'published',
	expired: 'danger',
	archived: 'draft',
};

const STATUS_LABEL = {
	draft: 'Szkic',
	published: 'Opublikowana',
	expired: 'Wygasła',
	archived: 'Zarchiwizowana',
};

export function GalleriesView() {
	const [ status, setStatus ] = useState( '' );
	const [ search, setSearch ] = useState( '' );
	const [ reloads, setReloads ] = useState( 0 );
	const [ state, setState ] = useState( { status: 'loading', rows: [], cursor: null, error: null } );

	const load = useCallback( ( params, signal ) => {
		return api.get( 'galleries', { params, signal } );
	}, [] );

	useEffect( () => {
		const controller = new AbortController();

		setState( ( previous ) => ( { ...previous, status: 'loading' } ) );

		load( { status, q: search }, controller.signal )
			.then( ( { data, meta } ) =>
				setState( { status: 'ready', rows: data, cursor: meta.next_cursor, error: null } )
			)
			.catch( ( error ) => {
				if ( 'AbortError' === error.name ) {
					return;
				}

				setState( { status: 'error', rows: [], cursor: null, error } );
			} );

		return () => controller.abort();
	}, [ status, search, reloads, load ] );

	const loadMore = async () => {
		const { data, meta } = await load( { status, q: search, cursor: state.cursor } );

		setState( ( previous ) => ( {
			...previous,
			rows: [ ...previous.rows, ...data ],
			cursor: meta.next_cursor,
		} ) );
	};

	/**
	 * Otwarcie szuflady z ustawieniami.
	 *
	 * Po zapisie przeładowujemy listę zamiast doklejać wiersz w pamięci:
	 * serwer mógł nadać inny slug, a stan galerii zależy od zdjęć, których
	 * ten widok nie zna.
	 */
	const openForm = ( gallery ) => {
		openDrawer( {
			title: gallery ? __( 'Ustawienia galerii' ) : __( 'Nowa galeria' ),
			content: ( close ) => html`<${GalleryForm}
				gallery=${ gallery }
				onSaved=${ () => setReloads( ( count ) => count + 1 ) }
				onClose=${ close }
			/>`,
		} );
	};

	if ( 'error' === state.status ) {
		return html`<${LoadFailure} error=${ state.error } />`;
	}

	return html`
		<div class="kadr-view__head">
			<div>
				<h1 class="kadr-view__title">${ __( 'Galerie' ) }</h1>
			</div>
			<div class="kadr-view__actions">
				<button type="button" class="kadr-btn kadr-btn--primary" onClick=${ () => openForm( null ) }>
					${ __( 'Nowa galeria' ) }
				</button>
			</div>
		</div>

		<${Toolbar} status=${ status } onStatus=${ setStatus } onSearch=${ setSearch } />

		<${DataTable}
			columns=${ columns }
			rows=${ state.rows }
			loading=${ 'loading' === state.status }
			caption=${ __( 'Galerie' ) }
			onSelect=${ openForm }
			empty=${ {
				title: '' !== search || '' !== status
					? __( 'Nic nie pasuje do tych kryteriów' )
					: __( 'Jeszcze żadnej galerii' ),
				text: '' !== search || '' !== status
					? __( 'Zmień filtr albo wyczyść wyszukiwanie.' )
					: __( 'Galeria to miejsce, w którym klient wybiera zdjęcia i dopłaca za te ponad pakiet.' ),
				actionLabel: '' === search && '' === status ? __( 'Utwórz galerię' ) : null,
				onAction: () => openForm( null ),
			} }
		/>

		${ state.cursor
			? html`<div class="kadr-more">
					<button type="button" class="kadr-btn kadr-btn--secondary" onClick=${ loadMore }>
						${ __( 'Wczytaj kolejne' ) }
					</button>
			  </div>`
			: null }
	`;
}

/**
 * Pasek filtrów.
 *
 * Wyszukiwarka jest odwlekana: bez tego każde naciśnięcie klawisza wysyła
 * zapytanie do serwera (docs/PERFORMANCE.md §5).
 */
function Toolbar( { status, onStatus, onSearch } ) {
	const debounced = useRef( null );

	if ( null === debounced.current ) {
		debounced.current = debounce( ( value ) => onSearch( value ), 300 );
	}

	return html`
		<div class="kadr-toolbar">
			<div class="kadr-search">
				<span class="kadr-search__icon" aria-hidden="true">⌕</span>
				<input
					class="kadr-search__input"
					type="search"
					placeholder=${ __( 'Szukaj po nazwie galerii…' ) }
					aria-label=${ __( 'Szukaj galerii' ) }
					onInput=${ ( event ) => debounced.current( event.target.value ) }
				/>
			</div>

			<div class="kadr-segmented" role="group" aria-label=${ __( 'Filtruj po stanie' ) }>
				${ STATUSES.map(
					( option ) => html`
						<button
							key=${ option.value }
							type="button"
							class="kadr-segmented__item"
							aria-pressed=${ status === option.value }
							onClick=${ () => onStatus( option.value ) }
						>${ __( option.label ) }</button>
					`
				) }
			</div>
		</div>
	`;
}

/**
 * Adres galerii w panelu.
 *
 * Baza przychodzi z konfiguracji wstrzykniętej przez serwer, a nie ze
 * zgadywania z bieżącego adresu — panel może stać pod dowolną ścieżką.
 */
const galleryHref = ( id ) => `${ ( window.kadrApp?.app || '/app/' ).replace( /\/$/, '' ) }/galerie/${ id }`;

const columns = [
	{
		key: 'title',
		label: __( 'Galeria' ),
		render: ( row ) => html`
			<div>
				<a
					class="kadr-table__link"
					href=${ galleryHref( row.id ) }
					onClick=${ ( event ) => event.stopPropagation() }
				>${ row.title }</a>
				${ row.client
					? html`<div class="kadr-table__meta">${ row.client }</div>`
					: html`<div class="kadr-table__meta">${ __( 'Bez przypisanego klienta' ) }</div>` }
			</div>
		`,
	},
	{
		key: 'status',
		label: __( 'Stan' ),
		render: ( row ) => html`
			<span class="kadr-status kadr-status--${ STATUS_TONE[ row.status ] || 'draft' }">
				${ __( STATUS_LABEL[ row.status ] || row.status ) }
			</span>
		`,
	},
	{
		key: 'photos',
		label: __( 'Zdjęć' ),
		numeric: true,
		render: ( row ) => formatNumber( row.photos ),
	},
	{
		key: 'extra_photo_price',
		label: __( 'Zdjęcie ponad pakiet' ),
		numeric: true,
		render: ( row ) =>
			null === row.extra_photo_price
				? html`<span class="kadr-table__meta">—</span>`
				: formatMoney( row.extra_photo_price ),
	},
];
