/**
 * Katalog komponentów — skrypt demonstracyjny.
 *
 * Montuje prawdziwe komponenty panelu, każdy w komplecie stanów. Służy
 * jako referencja przy budowaniu kolejnych widoków i jako miejsce, w którym
 * widać regresję wizualną, zanim trafi do panelu.
 */
import { html, render } from './runtime.js';
import { toast } from './toast.js';
import { confirmDialog, confirmDestructive } from './dialog.js';
import { openDrawer } from './drawer.js';
import { Field, rules, useForm, useDraft } from './form.js';
import { DataTable } from './table.js';

/* --- Przykładowe dane wyłącznie na potrzeby katalogu -------------------- */
const ROWS = [
	{ id: '1', gallery: 'Ślub Marty i Piotra', client: 'Marta Nowak', status: 'published', statusLabel: 'Opublikowana', photos: 842 },
	{ id: '2', gallery: 'Kowalscy — sesja rodzinna', client: 'Anna Kowalska', status: 'waiting', statusLabel: 'Czeka na klienta', photos: 148 },
	{ id: '3', gallery: 'Plener jesienny', client: null, status: 'draft', statusLabel: 'Szkic', photos: 0 },
];

const COLUMNS = [
	{
		key: 'gallery',
		label: 'Galeria',
		sortable: true,
		render: ( row ) => html`
			<div>
				<div>${ row.gallery }</div>
				<div class="kadr-table__meta">${ row.client || 'Bez przypisanego klienta' }</div>
			</div>
		`,
	},
	{
		key: 'status',
		label: 'Stan',
		sortable: true,
		render: ( row ) => html`<span class="kadr-status kadr-status--${ row.status }">${ row.statusLabel }</span>`,
	},
	{ key: 'photos', label: 'Zdjęć', numeric: true, sortable: true },
];

const state = { rows: ROWS, loading: false, sortKey: 'gallery', sortDirection: 'asc' };
const mount = document.getElementById( 'kadr-table-mount' );

function sorted() {
	const direction = 'asc' === state.sortDirection ? 1 : -1;

	return [ ...state.rows ].sort( ( a, b ) => {
		const left = a[ state.sortKey ];
		const right = b[ state.sortKey ];

		return 'number' === typeof left
			? ( left - right ) * direction
			: String( left ).localeCompare( String( right ), 'pl' ) * direction;
	} );
}

function paint() {
	render(
		html`<${DataTable}
			columns=${ COLUMNS }
			rows=${ sorted() }
			loading=${ state.loading }
			sortKey=${ state.sortKey }
			sortDirection=${ state.sortDirection }
			caption="Przykładowa tabela"
			onSort=${ ( key, direction ) => {
				state.sortKey = key;
				state.sortDirection = direction;
				paint();
			} }
			empty=${ {
				title: 'Jeszcze żadnej galerii',
				text: 'Galeria to miejsce, w którym klient wybiera zdjęcia i dopłaca za te ponad pakiet.',
				actionLabel: 'Utwórz galerię',
				onAction: () => toast.info( 'To jest katalog komponentów — akcja nic nie tworzy.' ),
			} }
		/>`,
		mount
	);
}

paint();

/* --- Formularz w szufladzie --------------------------------------------- */
function GalleryForm( { onDone } ) {
	const form = useForm( {
		initial: { name: '', email: '', extraPrice: '60', note: '' },
		validate: {
			name: [ rules.required( 'Podaj nazwę galerii — klient zobaczy ją w wiadomości.' ) ],
			email: [ rules.required( 'Podaj adres klientki.' ), rules.email() ],
			note: [ rules.maxLength( 400, 'Wiadomość może mieć najwyżej 400 znaków.' ) ],
		},
		onSubmit: async () => {
			await new Promise( ( resolve ) => setTimeout( resolve, 600 ) );
			draft.discard();
			toast.success( 'Formularz przeszedł walidację.' );
			onDone();
		},
	} );

	const draft = useDraft( 'katalog-galeria', form.values, form.setValues );

	return html`
		<form onSubmit=${ form.submit } novalidate>
			${ form.formError ? html`<p class="kadr-form__error">${ form.formError }</p>` : null }
			<${Field} ...${ form.field( 'name' ) } label="Nazwa galerii" required placeholder="Ślub Marty i Piotra"
				hint="Widoczna dla klienta w wiadomości i w nagłówku galerii." />
			<${Field} ...${ form.field( 'email' ) } label="Adres e-mail klientki" type="email" required autocomplete="email" />
			<${Field} ...${ form.field( 'extraPrice' ) } label="Cena zdjęcia ponad pakiet" inputmode="numeric"
				hint="W złotych. Zostaw puste, jeśli pakiet jest bez limitu." />
			<${Field} ...${ form.field( 'note' ) } label="Wiadomość do klientki" rows=${ 4 }
				hint="Kopia robocza zapisuje się automatycznie — zamknięcie karty jej nie skasuje." />
			<div class="kadr-form__actions">
				<button type="submit" class="kadr-btn kadr-btn--primary" disabled=${ form.submitting }>
					${ form.submitting ? 'Zapisywanie…' : 'Zapisz' }
				</button>
				<button type="button" class="kadr-btn kadr-btn--secondary" onClick=${ onDone }>Anuluj</button>
			</div>
		</form>
	`;
}

/* --- Obsługa przycisków katalogu ---------------------------------------- */
document.addEventListener( 'click', async ( event ) => {
	const action = event.target.closest?.( '[data-demo]' )?.dataset?.demo;

	if ( ! action ) {
		return;
	}

	if ( 'toast' === action ) {
		toast.success( 'Galeria została wysłana klientce.', {
			action: { label: 'Cofnij', run: () => toast.info( 'Wysyłka cofnięta.' ) },
		} );
	}

	if ( 'dialog' === action ) {
		const ok = await confirmDialog( {
			title: 'Opublikować galerię?',
			message: 'Klientka dostanie wiadomość z odnośnikiem i będzie mogła zacząć wybierać zdjęcia.',
			confirmLabel: 'Opublikuj',
		} );

		toast.info( ok ? 'Potwierdzono.' : 'Anulowano.' );
	}

	if ( 'destructive' === action ) {
		const ok = await confirmDestructive( {
			title: 'Usunąć galerię?',
			message: 'Galeria trafi do kosza i będzie można ją przywrócić przez 30 dni. Klient straci do niej dostęp natychmiast.',
			confirmLabel: 'Przenieś do kosza',
		} );

		toast[ ok ? 'warning' : 'info' ]( ok ? 'Galeria w koszu. Masz 30 dni na przywrócenie.' : 'Nic nie usunięto.' );
	}

	if ( 'drawer' === action ) {
		openDrawer( {
			title: 'Ustawienia galerii',
			content: ( close ) => html`<${GalleryForm} onDone=${ close } />`,
		} );
	}

	if ( 'loading' === action ) {
		state.loading = true;
		paint();
		setTimeout( () => {
			state.loading = false;
			paint();
		}, 1400 );
	}

	if ( 'empty' === action ) {
		state.rows = [];
		paint();
	}

	if ( 'data' === action ) {
		state.rows = ROWS;
		paint();
	}
} );
