/**
 * Katalog produktów fotografa.
 *
 * ETAP ④ Z CLAUDE.md §1: dziś odbitek nie sprzedaje się wcale. Ten ekran
 * jest warunkiem, żeby w ogóle było co sprzedawać — bez cennika Print Room
 * w galerii klientki nie ma czego pokazać.
 *
 * FORMATY SĄ DANYMI, NIE KODEM (skill photography-workflow §5). Jeden
 * fotograf pracuje z laboratorium robiącym 60×90, drugi sprzedaje kwadraty
 * 30×30. Dlatego wymiary wpisuje się tutaj, a nie zgłasza jako życzenie
 * do kolejnej wersji wtyczki.
 */
import { html, useState, useEffect, useCallback, formatMoney, __ } from '../runtime.js';
import { api } from '../api.js';
import { toast } from '../toast.js';
import { confirmDestructive } from '../dialog.js';
import { EmptyPanel } from '../table.js';
import { LoadFailure } from './today.js';

export function ProductsView() {
	const [ state, setState ] = useState( { status: 'loading', products: [], error: null } );
	const [ busy, setBusy ] = useState( false );

	const load = useCallback( async () => {
		try {
			const { data } = await api.get( 'catalogue' );

			setState( { status: 'ready', products: data, error: null } );
		} catch ( error ) {
			setState( { status: 'error', products: [], error } );
		}
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	const seed = async () => {
		setBusy( true );

		try {
			await api.post( 'catalogue/seed', {} );
			toast.success( __( 'Formaty dodane. Wpisz teraz swoje ceny — zostawiliśmy je puste.' ) );
			load();
		} catch ( error ) {
			toast.error( error );
		} finally {
			setBusy( false );
		}
	};

	if ( 'error' === state.status ) {
		return html`<${LoadFailure} error=${ state.error } />`;
	}

	return html`
		<div class="kadr-view__head">
			<div>
				<h1 class="kadr-view__title">${ __( 'Produkty' ) }</h1>
				<p class="kadr-view__subtitle">
					${ __( 'Co klientka może zamówić ze swoich zdjęć. Przy każdym formacie pokażemy jej, jak kadr zostanie przycięty.' ) }
				</p>
			</div>
		</div>

		${ 'loading' === state.status
			? null
			: 0 === state.products.length
				? html`<${EmptyPanel}
						title=${ __( 'Nie masz jeszcze cennika odbitek' ) }
						text=${ __( 'Bez niego klientka nie ma czego zamówić. Możemy wstawić sześć formatów, które oferuje każde polskie laboratorium — ceny wpiszesz sam, bo to Twoja marża.' ) }
						actionLabel=${ __( 'Wstaw typowe formaty' ) }
						onAction=${ seed }
				  />`
				: state.products.map(
						( product ) => html`<${Product} key=${ product.id } product=${ product } onChanged=${ load } />`
				  ) }
	`;
}

function Product( { product, onChanged } ) {
	return html`
		<section class="kadr-panel" aria-labelledby=${ `kadr-product-${ product.id }` }>
			<div class="kadr-upload__head">
				<h2 class="kadr-panel__title" id=${ `kadr-product-${ product.id }` }>${ product.name }</h2>
				<span class="kadr-status kadr-status--${ product.active ? 'published' : 'draft' }">
					${ product.active ? __( 'W sprzedaży' ) : __( 'Wyłączony' ) }
				</span>
			</div>

			${ product.description
				? html`<p class="kadr-field__hint">${ product.description }</p>`
				: null }

			<ul class="kadr-panel__list">
				${ product.variants.map(
					( variant ) => html`<${Variant} key=${ variant.id } variant=${ variant } onChanged=${ onChanged } />`
				) }
			</ul>

			${ 0 === product.variants.length
				? html`<p class="kadr-field__hint">
						${ __( 'Ten produkt nie ma jeszcze żadnego wariantu, więc klientka go nie zobaczy.' ) }
				  </p>`
				: null }
		</section>
	`;
}

function Variant( { variant, onChanged } ) {
	const [ price, setPrice ] = useState( () => zloty( variant.price ) );
	const [ busy, setBusy ] = useState( false );

	// Cena bez wartości to najczęstszy stan po wstawieniu typowych formatów
	// — i jedyny, który blokuje sprzedaż. Musi rzucać się w oczy.
	const missing = 0 === variant.price;

	const save = async () => {
		const minor = toMinor( price );

		if ( null === minor || minor === variant.price ) {
			setPrice( zloty( variant.price ) );

			return;
		}

		setBusy( true );

		try {
			await api.patch( `catalogue/variants/${ variant.id }`, { price: minor } );
			onChanged();
		} catch ( error ) {
			toast.error( error );
			setPrice( zloty( variant.price ) );
		} finally {
			setBusy( false );
		}
	};

	const remove = async () => {
		const confirmed = await confirmDestructive( {
			title: __( 'Usunąć ten wariant?' ),
			message: `${ variant.label } ${ __( 'zniknie z oferty. Zamówienia już złożone zachowają swoją nazwę i cenę.' ) }`,
		} );

		if ( ! confirmed ) {
			return;
		}

		try {
			await api.delete( `catalogue/variants/${ variant.id }` );
			onChanged();
		} catch ( error ) {
			toast.error( error );
		}
	};

	return html`
		<li class="kadr-panel__row">
			<span class="kadr-product__format">
				<span>${ variant.label }</span>
				${ variant.paper
					? html`<span class="kadr-table__meta">${ variant.paper }</span>`
					: null }
			</span>

			<label class="kadr-product__price">
				<span class="kadr-sr-only">${ `${ __( 'Cena' ) }: ${ variant.label }` }</span>
				<input
					class="kadr-field__input ${ missing ? 'kadr-field__input--empty' : '' }"
					inputmode="decimal"
					value=${ price }
					disabled=${ busy }
					placeholder="0"
					onInput=${ ( event ) => setPrice( event.target.value ) }
					onBlur=${ save }
				/>
				<span aria-hidden="true">${ __( 'zł' ) }</span>
			</label>

			<button
				type="button"
				class="kadr-btn kadr-btn--ghost kadr-btn--danger-ghost"
				aria-label=${ `${ __( 'Usuń wariant' ) }: ${ variant.label }` }
				onClick=${ remove }
			>×</button>
		</li>
	`;
}

/**
 * Grosze na złotówki do pola formularza.
 *
 * Pusta wartość przy zerze, nie „0" — fotograf ma wpisać cenę, a wpisane
 * zero wygląda jak decyzja i łatwo je przeoczyć.
 */
function zloty( minor ) {
	return 0 === minor ? '' : String( minor / 100 ).replace( '.', ',' );
}

/**
 * Złotówki z pola formularza na grosze.
 *
 * Przyjmujemy przecinek i kropkę — fotograf wpisze jedno albo drugie,
 * a odrzucanie któregokolwiek byłoby złośliwością.
 *
 * @return {number|null} `null`, gdy wpisano coś, co nie jest kwotą.
 */
function toMinor( value ) {
	const normalised = String( value ).trim().replace( ',', '.' );

	if ( '' === normalised ) {
		return 0;
	}

	if ( ! /^\d+(\.\d{1,2})?$/.test( normalised ) ) {
		return null;
	}

	return Math.round( parseFloat( normalised ) * 100 );
}
