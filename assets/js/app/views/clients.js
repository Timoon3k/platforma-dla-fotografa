/**
 * Lista klientów.
 *
 * Klient fotografa nie jest użytkownikiem WordPressa (ADR-003) — i nie musi
 * niczego zakładać, żeby obejrzeć swoje zdjęcia. Ta lista jest kartoteką
 * fotografa, nie listą kont.
 */
import { html, useState, useEffect, useRef, useCallback, debounce, __ } from '../runtime.js';
import { api } from '../api.js';
import { DataTable } from '../table.js';
import { LoadFailure } from './today.js';

export function ClientsView() {
	const [ search, setSearch ] = useState( '' );
	const [ state, setState ] = useState( { status: 'loading', rows: [], cursor: null, error: null } );

	const load = useCallback( ( params, signal ) => api.get( 'clients', { params, signal } ), [] );

	useEffect( () => {
		const controller = new AbortController();

		setState( ( previous ) => ( { ...previous, status: 'loading' } ) );

		load( { q: search }, controller.signal )
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
	}, [ search, load ] );

	const loadMore = async () => {
		const { data, meta } = await load( { q: search, cursor: state.cursor } );

		setState( ( previous ) => ( {
			...previous,
			rows: [ ...previous.rows, ...data ],
			cursor: meta.next_cursor,
		} ) );
	};

	if ( 'error' === state.status ) {
		return html`<${LoadFailure} error=${ state.error } />`;
	}

	return html`
		<div class="kadr-view__head">
			<div><h1 class="kadr-view__title">${ __( 'Klienci' ) }</h1></div>
		</div>

		<${SearchBar} onSearch=${ setSearch } />

		<${DataTable}
			columns=${ columns }
			rows=${ state.rows }
			loading=${ 'loading' === state.status }
			caption=${ __( 'Klienci' ) }
			empty=${ {
				title: '' !== search ? __( 'Nikt nie pasuje do tego wyszukiwania' ) : __( 'Jeszcze żadnego klienta' ),
				text: '' !== search
					? __( 'Szukamy po nazwisku.' )
					: __( 'Klient trafia tu przy pierwszej galerii albo rezerwacji. Nie musi zakładać konta.' ),
			} }
		/>

		${ state.cursor
			? html`<div class="kadr-more">
					<button type="button" class="kadr-btn kadr-btn--secondary" onClick=${ loadMore }>
						${ __( 'Wczytaj kolejnych' ) }
					</button>
			  </div>`
			: null }
	`;
}

function SearchBar( { onSearch } ) {
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
					placeholder=${ __( 'Szukaj po nazwisku…' ) }
					aria-label=${ __( 'Szukaj klientów' ) }
					onInput=${ ( event ) => debounced.current( event.target.value ) }
				/>
			</div>
		</div>
	`;
}

const columns = [
	{
		key: 'last_name',
		label: __( 'Klient' ),
		render: ( row ) => html`<div>${ `${ row.first_name } ${ row.last_name }`.trim() }</div>`,
	},
	{
		key: 'email',
		label: __( 'E-mail' ),
		render: ( row ) => html`<span class="kadr-table__meta">${ row.email }</span>`,
	},
	{
		key: 'phone',
		label: __( 'Telefon' ),
		render: ( row ) =>
			row.phone ? row.phone : html`<span class="kadr-table__meta">—</span>`,
	},
];
