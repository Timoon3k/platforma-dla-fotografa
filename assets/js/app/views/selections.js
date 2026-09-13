/**
 * Skrzynka wyborów — ekran, od którego zaczyna się dzień fotografa.
 *
 * Odpowiada na dwa pytania i na nic więcej: gdzie klientka już wybrała
 * zdjęcia (czyli gdzie zaczyna się obróbka i gdzie czeka dopłata) oraz
 * kto jeszcze się zastanawia (czyli komu warto dziś przypomnieć).
 *
 * Dziś ta wiedza siedzi w skrzynce mailowej i w pamięci — i dlatego
 * regularnie wyparowuje razem z przychodem.
 */
import { html, useState, useEffect, formatMoney, formatNumber, __ } from '../runtime.js';
import { api } from '../api.js';
import { DataTable } from '../table.js';
import { LoadFailure } from './today.js';

const STATUS = {
	submitted: { label: 'Czeka na Ciebie', tone: 'accent' },
	open: { label: 'Klientka wybiera', tone: 'draft' },
	reopened: { label: 'Otwarty ponownie', tone: 'draft' },
};

export function SelectionsView() {
	const [ state, setState ] = useState( { status: 'loading', rows: [], summary: null, error: null } );

	useEffect( () => {
		const controller = new AbortController();

		api.get( 'selections', { signal: controller.signal } )
			.then( ( { data, meta } ) =>
				setState( { status: 'ready', rows: data, summary: meta, error: null } )
			)
			.catch( ( error ) => {
				if ( 'AbortError' === error.name ) {
					return;
				}

				setState( { status: 'error', rows: [], summary: null, error } );
			} );

		return () => controller.abort();
	}, [] );

	if ( 'error' === state.status ) {
		return html`<${LoadFailure} error=${ state.error } />`;
	}

	const summary = state.summary;

	return html`
		<div class="kadr-view__head">
			<div>
				<h1 class="kadr-view__title">${ __( 'Wybory' ) }</h1>
				<p class="kadr-view__subtitle">
					${ __( 'Zdjęcia, które klientki wskazały do obróbki — i dopłaty, które z tego wynikają.' ) }
				</p>
			</div>
		</div>

		${ summary && ( summary.submitted > 0 || summary.in_progress > 0 )
			? html`<div class="kadr-tiles">
					<div class="kadr-tile">
						<span class="kadr-tile__label">${ __( 'Czeka na Ciebie' ) }</span>
						<span class="kadr-tile__value">${ formatNumber( summary.submitted ) }</span>
					</div>
					<div class="kadr-tile">
						<span class="kadr-tile__label">${ __( 'Klientki jeszcze wybierają' ) }</span>
						<span class="kadr-tile__value">${ formatNumber( summary.in_progress ) }</span>
					</div>
					<div class="kadr-tile">
						<span class="kadr-tile__label">${ __( 'Dopłaty do rozliczenia' ) }</span>
						<span class="kadr-tile__value ${ summary.due > 0 ? 'kadr-tile__value--signal' : '' }">
							${ formatMoney( summary.due ) }
						</span>
					</div>
			  </div>`
			: null }

		<${DataTable}
			columns=${ columns }
			rows=${ state.rows }
			loading=${ 'loading' === state.status }
			caption=${ __( 'Wybory klientek' ) }
			empty=${ {
				title: __( 'Żadna klientka jeszcze nie wybierała' ),
				text: __( 'Wybór zaczyna się w chwili, gdy wyślesz klientce link do galerii. Licznik dopłaty liczy się jej na bieżąco, więc kwota nie będzie dla nikogo niespodzianką.' ),
			} }
		/>
	`;
}

/**
 * Adres galerii budujemy z konfiguracji powłoki, nie zgadujemy z bieżącego
 * adresu — panel może stać pod dowolną ścieżką.
 */
const galleryHref = ( id ) => `${ ( window.kadrApp?.app || '/app/' ).replace( /\/$/, '' ) }/galerie/${ id }`;

const columns = [
	{
		key: 'gallery',
		label: __( 'Galeria' ),
		render: ( row ) => html`
			<div>
				<a class="kadr-table__link" href=${ galleryHref( row.gallery_id ) }>${ row.gallery }</a>
				<div class="kadr-table__meta">
					${ row.client || __( 'Bez przypisanego klienta' ) }
				</div>
			</div>
		`,
	},
	{
		key: 'status',
		label: __( 'Stan' ),
		render: ( row ) => html`
			<span class="kadr-status kadr-status--${ ( STATUS[ row.status ] || STATUS.open ).tone }">
				${ __( ( STATUS[ row.status ] || STATUS.open ).label ) }
			</span>
		`,
	},
	{
		key: 'selected',
		label: __( 'Wybrane' ),
		numeric: true,
		// Kreska zamiast zera: wybór w toku nie ma jeszcze liczby, która
		// coś znaczy, a zero sugerowałoby, że klientka nic nie zaznaczyła.
		render: ( row ) =>
			row.tally
				? html`<span>${ formatNumber( row.tally.selected ) }</span>`
				: html`<span class="kadr-table__meta">—</span>`,
	},
	{
		key: 'extra',
		label: __( 'Ponad pakiet' ),
		numeric: true,
		render: ( row ) => {
			if ( ! row.tally ) {
				return html`<span class="kadr-table__meta">—</span>`;
			}

			return row.tally.extra > 0
				? html`<span class="kadr-table__warning">${ formatNumber( row.tally.extra ) }</span>`
				: html`<span class="kadr-table__meta">${ __( 'mieści się' ) }</span>`;
		},
	},
	{
		key: 'total',
		label: __( 'Do dopłaty' ),
		numeric: true,
		render: ( row ) => {
			if ( ! row.tally ) {
				return html`<span class="kadr-table__meta">—</span>`;
			}

			return row.tally.needs_payment
				? html`<strong class="kadr-table__signal">${ formatMoney( row.tally.total ) }</strong>`
				: html`<span class="kadr-table__meta">${ formatMoney( 0 ) }</span>`;
		},
	},
];
