/**
 * Skrypt startowy podglądu panelu.
 *
 * Montuje prawdziwe komponenty na przykładowych danych. Dane są tu jawnie
 * demonstracyjne — w kodzie produkcyjnym nie ma ani jednego wymyślonego
 * klienta czy zamówienia (CLAUDE.md §9).
 */
import { html, render, signal, formatMoney, formatNumber } from './runtime.js';
import { toast } from './toast.js';
import { confirmDialog, confirmDestructive } from './dialog.js';
import { registerCommands, bindShortcut, open as openPalette } from './palette.js';
import { DataTable } from './table.js';
import { openDrawer } from './drawer.js';
import { Field, rules, useForm, useDraft } from './form.js';

/* --- Przykładowe dane wyłącznie na potrzeby podglądu --------------------- */
const SAMPLE = [
	{ id: '1', gallery: 'Kowalscy — sesja rodzinna', client: 'Anna Kowalska', status: 'waiting', statusLabel: 'Czeka na klienta', photos: 148, extra: 0, days: 2 },
	{ id: '2', gallery: 'Ślub Marty i Piotra', client: 'Marta Nowak', status: 'accent', statusLabel: 'Wybór gotowy', photos: 842, extra: 48000, days: 0 },
	{ id: '3', gallery: 'Zosia — newborn', client: 'Kasia Wiśniewska', status: 'accent', statusLabel: 'Wybór gotowy', photos: 96, extra: 18000, days: 1 },
	{ id: '4', gallery: 'Chrzciny Antka', client: 'Paweł Lewandowski', status: 'danger', statusLabel: 'Wygasa jutro', photos: 212, extra: 0, days: 89 },
	{ id: '5', gallery: 'Sesja biznesowa — Lumen', client: 'Studio Lumen', status: 'published', statusLabel: 'Opublikowana', photos: 54, extra: 0, days: 12 },
	{ id: '6', gallery: 'Plener jesienny', client: 'Ewa Zielińska', status: 'draft', statusLabel: 'Szkic', photos: 0, extra: 0, days: 0 },
];

const rows = signal( SAMPLE );
const sortKey = signal( 'gallery' );
const sortDirection = signal( 'asc' );
const loading = signal( false );
const selectedId = signal( null );

const columns = [
	{
		key: 'gallery',
		label: 'Galeria',
		sortable: true,
		render: ( row ) => html`
			<div>
				<div>${ row.gallery }</div>
				<div style="font-size:12px;color:var(--kadr-ink-subtle)">${ row.client }</div>
			</div>
		`,
	},
	{
		key: 'status',
		label: 'Stan',
		sortable: true,
		render: ( row ) => html`<span class="kadr-status kadr-status--${ row.status }">${ row.statusLabel }</span>`,
	},
	{
		key: 'photos',
		label: 'Zdjęć',
		sortable: true,
		numeric: true,
		render: ( row ) => formatNumber( row.photos ),
	},
	{
		key: 'extra',
		label: 'Do dopłaty',
		sortable: true,
		numeric: true,
		render: ( row ) =>
			row.extra > 0
				? html`<strong style="color:var(--kadr-signal)">${ formatMoney( row.extra ) }</strong>`
				: html`<span style="color:var(--kadr-ink-subtle)">—</span>`,
	},
];

function sorted() {
	const key = sortKey.value;
	const direction = 'asc' === sortDirection.value ? 1 : -1;

	return [ ...rows.value ].sort( ( a, b ) => {
		const left = a[ key ];
		const right = b[ key ];

		if ( 'number' === typeof left ) {
			return ( left - right ) * direction;
		}

		return String( left ).localeCompare( String( right ), 'pl' ) * direction;
	} );
}

function Panel() {
	return html`<${DataTable}
		columns=${ columns }
		rows=${ sorted() }
		loading=${ loading.value }
		sortKey=${ sortKey.value }
		sortDirection=${ sortDirection.value }
		selectedId=${ selectedId.value }
		caption="Galerie wymagające uwagi"
		onSort=${ ( key, direction ) => {
			sortKey.value = key;
			sortDirection.value = direction;
			paint();
		} }
		onSelect=${ ( row ) => {
			selectedId.value = selectedId.value === row.id ? null : row.id;
			paint();
		} }
		empty=${ {
			title: 'Jeszcze żadnej galerii',
			text: 'Galeria to miejsce, w którym klient wybiera zdjęcia i dopłaca za te ponad pakiet. Pierwszą przygotujesz w kilka minut.',
			actionLabel: 'Utwórz galerię',
			onAction: () => toast.info( 'Kreator galerii powstaje w sesji 7.' ),
		} }
	/>`;
}

const mount = document.getElementById( 'kadr-table-mount' );

function paint() {
	render( html`<${Panel} />`, mount );
}

paint();


/* --- Formularz w szufladzie ---------------------------------------------- */
/*
 * Prawdziwy komponent formularza: walidacja przy opuszczeniu pola, poprawka
 * kasująca błąd od razu, kopia robocza w sessionStorage. Zapis jest tu
 * pozorowany opóźnieniem — endpoint powstaje w sesji 7.
 */
function GalleryForm( { onDone } ) {
	const form = useForm( {
		initial: { name: '', email: '', extraPrice: '60', note: '' },
		validate: {
			name: [ rules.required( 'Podaj nazwę galerii — klient zobaczy ją w wiadomości.' ) ],
			email: [ rules.required( 'Podaj adres klientki.' ), rules.email() ],
			note: [ rules.maxLength( 400, 'Wiadomość może mieć najwyżej 400 znaków.' ) ],
		},
		onSubmit: async () => {
			await new Promise( ( resolve ) => setTimeout( resolve, 700 ) );
			draft.discard();
			toast.success( 'Ustawienia zapisane. Zapis do bazy podłączymy w sesji 7.' );
			onDone();
		},
	} );

	const draft = useDraft( 'preview-gallery', form.values, form.setValues );

	return html`
		<form onSubmit=${ form.submit } novalidate>
			${ form.formError ? html`<p class="kadr-form__error">${ form.formError }</p>` : null }

			<${Field}
				...${ form.field( 'name' ) }
				label="Nazwa galerii"
				required
				placeholder="Ślub Marty i Piotra"
				hint="Widoczna dla klienta w wiadomości i w nagłówku galerii."
			/>
			<${Field}
				...${ form.field( 'email' ) }
				label="Adres e-mail klientki"
				type="email"
				required
				autocomplete="email"
				placeholder="marta@example.com"
			/>
			<${Field}
				...${ form.field( 'extraPrice' ) }
				label="Cena zdjęcia ponad pakiet"
				inputmode="numeric"
				hint="W złotych. Zostaw puste, jeśli pakiet jest bez limitu."
			/>
			<${Field}
				...${ form.field( 'note' ) }
				label="Wiadomość do klientki"
				rows=${ 4 }
				hint="Kopia robocza zapisuje się automatycznie — zamknięcie karty jej nie skasuje."
			/>

			<div class="kadr-form__actions">
				<button type="submit" class="kadr-btn kadr-btn--primary" disabled=${ form.submitting }>
					${ form.submitting ? 'Zapisywanie…' : 'Zapisz' }
				</button>
				<button type="button" class="kadr-btn kadr-btn--secondary" onClick=${ onDone }>Anuluj</button>
			</div>
		</form>
	`;
}

/* --- Paleta poleceń ------------------------------------------------------ */
registerCommands( [
	{ id: 'new-gallery', label: 'Nowa galeria', group: 'Galerie', keywords: [ 'utwórz', 'dodaj' ], run: () => toast.info( 'Kreator galerii powstaje w sesji 7.' ) },
	{ id: 'upload', label: 'Wyślij zdjęcia', group: 'Galerie', keywords: [ 'upload', 'dodaj' ], run: () => toast.info( 'Wysyłanie działa po stronie serwera od sesji 5.' ) },
	{ id: 'new-client', label: 'Dodaj klienta', group: 'Klienci', run: () => toast.info( 'CRM powstaje w sesji 14.' ) },
	{ id: 'orders', label: 'Nieopłacone zamówienia', group: 'Sprzedaż', run: () => toast.warning( 'Cztery zamówienia czekają na płatność.' ) },
	{ id: 'storage', label: 'Zużycie miejsca', group: 'Rozliczenia', keywords: [ 'storage', 'limit' ], run: () => toast.info( 'Wykorzystano 184 GB z 250 GB.' ) },
	{ id: 'settings', label: 'Ustawienia studia', group: 'Studio', run: () => toast.info( 'Ustawienia powstają w sesji 7.' ) },
] );

bindShortcut();

/* --- Demonstracja komponentów ------------------------------------------- */
document.addEventListener( 'click', async ( event ) => {
	const action = event.target.closest?.( '[data-demo]' )?.dataset?.demo;

	if ( ! action ) {
		return;
	}

	if ( 'toast' === action ) {
		toast.success( 'Galeria „Kowalscy" została wysłana klientce.', {
			action: { label: 'Cofnij', run: () => toast.info( 'Wysyłka cofnięta.' ) },
		} );
	}

	if ( 'new-gallery' === action ) {
		const ok = await confirmDialog( {
			title: 'Nowa galeria',
			message: 'Kreator galerii powstaje w sesji 7. Ten dialog pokazuje komponent: pułapkę fokusu, obsługę Escape i tło modalne.',
			confirmLabel: 'Rozumiem',
			cancelLabel: 'Zamknij',
		} );

		if ( ok ) {
			toast.info( 'Dialog potwierdzony.' );
		}
	}

	if ( 'destructive' === action ) {
		const ok = await confirmDestructive( {
			title: 'Usunąć galerię?',
			message: 'Galeria trafi do kosza i będzie można ją przywrócić przez 30 dni. Klient straci do niej dostęp natychmiast.',
			confirmLabel: 'Przenieś do kosza',
		} );

		toast[ ok ? 'warning' : 'info' ]( ok ? 'Galeria w koszu. Masz 30 dni na przywrócenie.' : 'Nic nie usunięto.' );
	}

	if ( 'loading' === action ) {
		loading.value = true;
		paint();
		setTimeout( () => {
			loading.value = false;
			paint();
		}, 1600 );
	}

	if ( 'empty' === action ) {
		rows.value = [];
		paint();
	}

	if ( 'data' === action ) {
		rows.value = SAMPLE;
		paint();
	}

	if ( 'drawer' === action ) {
		openDrawer( {
			title: 'Ustawienia galerii',
			content: ( close ) => html`<${GalleryForm} onDone=${ close } />`,
		} );
	}

	if ( 'menu' === action ) {
		const nav = document.querySelector( '.kadr-app__nav' );
		nav.dataset.open = 'true' === nav.dataset.open ? 'false' : 'true';
	}
} );

// Podpowiedź przy starcie, żeby było wiadomo, co można kliknąć.
setTimeout( () => {
	toast.info( 'Naciśnij ⌘K albo Ctrl+K, żeby otworzyć paletę poleceń.', { duration: 9000 } );
}, 900 );
