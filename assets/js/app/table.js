/**
 * Tabela danych: sortowanie, zaznaczanie, stany puste i ładowania.
 *
 * Sortowanie po stronie serwera, nie w przeglądarce — przy tysiącach wierszy
 * nie ma sensu ściągać wszystkiego, żeby posortować lokalnie.
 */
import { html, __ } from './runtime.js';

/**
 * @param {object} props
 * @param {Array<{key: string, label: string, sortable?: boolean, numeric?: boolean, render?: Function}>} props.columns
 * @param {Array<object>} props.rows
 * @param {string|null} props.sortKey
 * @param {'asc'|'desc'} props.sortDirection
 * @param {Function} props.onSort
 * @param {boolean} props.loading
 * @param {object|null} props.empty  { title, text, action }
 */
export function DataTable( {
	columns,
	rows = [],
	sortKey = null,
	sortDirection = 'asc',
	onSort = null,
	loading = false,
	selectedId = null,
	onSelect = null,
	empty = null,
	caption = '',
} ) {
	if ( loading ) {
		return html`<${TableSkeleton} columns=${ columns } />`;
	}

	if ( 0 === rows.length ) {
		return html`<${EmptyPanel} ...${ empty || {} } />`;
	}

	return html`
		<div class="kadr-table__wrap">
			<table class="kadr-table">
				${ caption ? html`<caption class="kadr-sr-only">${ caption }</caption>` : null }
				<thead>
					<tr>
						${ columns.map(
							( column ) => html`
								<th
									key=${ column.key }
									scope="col"
									class=${ column.numeric ? 'kadr-table__numeric' : '' }
									aria-sort=${ sortKey === column.key
										? ( 'asc' === sortDirection ? 'ascending' : 'descending' )
										: 'none' }
								>
									${ column.sortable && onSort
										? html`<button
												type="button"
												class="kadr-table__sort"
												aria-sort=${ sortKey === column.key
													? ( 'asc' === sortDirection ? 'ascending' : 'descending' )
													: 'none' }
												onClick=${ () =>
													onSort(
														column.key,
														sortKey === column.key && 'asc' === sortDirection ? 'desc' : 'asc'
													) }
										  >${ column.label }</button>`
										: column.label }
								</th>
							`
						) }
					</tr>
				</thead>
				<tbody>
					${ rows.map(
						( row ) => html`
							<tr
								key=${ row.id }
								aria-selected=${ selectedId === row.id }
								onClick=${ onSelect ? () => onSelect( row ) : null }
							>
								${ columns.map(
									( column ) => html`
										<td key=${ column.key } class=${ column.numeric ? 'kadr-table__numeric' : '' }>
											${ column.render ? column.render( row ) : row[ column.key ] }
										</td>
									`
								) }
							</tr>
						`
					) }
				</tbody>
			</table>
		</div>
	`;
}

/**
 * Szkielet o tej samej strukturze co tabela — dzięki temu układ nie skacze,
 * gdy dane dojdą (zero CLS).
 */
export function TableSkeleton( { columns, rows = 6 } ) {
	return html`
		<div class="kadr-table__wrap" aria-busy="true" aria-label=${ __( 'Wczytywanie' ) }>
			<table class="kadr-table">
				<thead>
					<tr>${ columns.map( ( column ) => html`<th key=${ column.key }>${ column.label }</th>` ) }</tr>
				</thead>
				<tbody>
					${ Array.from( { length: rows }, ( _, index ) => html`
						<tr key=${ index }>
							${ columns.map(
								( column ) => html`<td key=${ column.key }><div class="kadr-skeleton kadr-skeleton--text"></div></td>`
							) }
						</tr>
					` ) }
				</tbody>
			</table>
		</div>
	`;
}

/**
 * Pusty stan jest częścią onboardingu, nie komunikatem o błędzie:
 * mówi, co to jest, dlaczego warto i jaki jest następny krok.
 */
export function EmptyPanel( { title, text, actionLabel, onAction } ) {
	return html`
		<div class="kadr-empty-panel">
			<p class="kadr-empty-panel__title">${ title || __( 'Nic tu jeszcze nie ma' ) }</p>
			${ text ? html`<p class="kadr-empty-panel__text">${ text }</p>` : null }
			${ actionLabel && onAction
				? html`<button type="button" class="kadr-btn kadr-btn--primary" onClick=${ onAction }>
						${ actionLabel }
				  </button>`
				: null }
		</div>
	`;
}
