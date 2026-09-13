/**
 * Wybór klientki oczami fotografa.
 *
 * Fotograf potrzebuje tu trzech rzeczy i niczego więcej:
 *  1. **ile zdjęć wybrała** i ile z tego jest ponad pakiet,
 *  2. **ile to jest pieniędzy** — bo to on wystawi rachunek,
 *  3. **możliwość otwarcia wyboru ponownie**, bo klientka się rozmyśli.
 *
 * Kwoty przychodzą z serwera policzone przez `PackageTally`. Panel ich nie
 * przelicza: gdyby liczył po swojemu, prędzej czy później pokazałby inną
 * kwotę niż ta, którą zobaczyła klientka.
 */
import { html, useState, useEffect, useCallback, formatMoney, formatNumber, __ } from '../runtime.js';
import { api } from '../api.js';
import { toast } from '../toast.js';
import { confirmDialog } from '../dialog.js';

const STATUS_LABEL = {
	open: 'Klientka jeszcze wybiera',
	reopened: 'Otwarty ponownie',
	submitted: 'Wybór wysłany',
};

export function SelectionPanel( { galleryId, packageLimit } ) {
	const [ state, setState ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	const reload = useCallback( async () => {
		try {
			const { data } = await api.get( `galleries/${ galleryId }/selection` );

			setState( data );
		} catch ( error ) {
			// Brak wyboru nie jest awarią — po prostu nikt jeszcze nie klikał.
		}
	}, [ galleryId ] );

	useEffect( () => {
		reload();
	}, [ reload ] );

	if ( null === state ) {
		return null;
	}

	const { tally } = state;
	const submitted = 'submitted' === state.status;

	const reopen = async () => {
		const confirmed = await confirmDialog( {
			title: __( 'Otworzyć wybór ponownie?' ),
			message: __( 'Klientka znów będzie mogła zmieniać zaznaczenia. Dotychczasowy wybór zostaje — nie musi zaczynać od nowa.' ),
			confirmLabel: __( 'Otwórz ponownie' ),
		} );

		if ( ! confirmed ) {
			return;
		}

		setBusy( true );

		try {
			await api.delete( `galleries/${ galleryId }/selection` );
			toast.success( __( 'Wybór otwarty. Napisz klientce, że może poprawić zaznaczenia.' ) );
			reload();
		} catch ( error ) {
			toast.error( error, reopen );
		} finally {
			setBusy( false );
		}
	};

	return html`
		<section class="kadr-panel ${ submitted ? 'kadr-panel--accent' : '' }" aria-labelledby="kadr-selection">
			<div class="kadr-upload__head">
				<h2 class="kadr-panel__title" id="kadr-selection">${ __( 'Wybór klientki' ) }</h2>
				<span class="kadr-status kadr-status--${ submitted ? 'accent' : 'draft' }">
					${ __( STATUS_LABEL[ state.status ] || state.status ) }
				</span>
			</div>

			<div class="kadr-tiles">
				<${Tile} label=${ __( 'Wybrane' ) } value=${ formatNumber( tally.selected ) } />
				<${Tile} label=${ __( 'Ulubione' ) } value=${ formatNumber( state.favorites ) } />
				${ null === packageLimit || null === tally.package_limit
					? null
					: html`<${Tile}
							label=${ __( 'Ponad pakiet' ) }
							value=${ formatNumber( tally.extra ) }
							tone=${ tally.extra > 0 ? 'kadr-tile__value--warning' : '' }
					  />` }
				<${Tile}
					label=${ __( 'Do dopłaty' ) }
					value=${ formatMoney( tally.total ) }
					tone=${ tally.needs_payment ? 'kadr-tile__value--signal' : '' }
				/>
			</div>

			${ submitted
				? html`<div class="kadr-form__actions">
						<button type="button" class="kadr-btn kadr-btn--secondary" disabled=${ busy } onClick=${ reopen }>
							${ __( 'Otwórz wybór ponownie' ) }
						</button>
				  </div>`
				: html`<p class="kadr-field__hint">
						${ __( 'Wybór jest otwarty — klientka może jeszcze zmieniać zaznaczenia.' ) }
				  </p>` }
		</section>
	`;
}

function Tile( { label, value, tone = '' } ) {
	return html`
		<div class="kadr-tile">
			<span class="kadr-tile__label">${ label }</span>
			<span class="kadr-tile__value ${ tone }">${ value }</span>
		</div>
	`;
}
