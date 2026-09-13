/**
 * Formularz galerii — tworzenie i ustawienia.
 *
 * Otwiera się w szufladzie, nie na osobnym ekranie: fotograf poprawia
 * ustawienia i przez cały czas widzi, przy którym wierszu listy jest.
 *
 * Dwa pola są tu ważniejsze niż reszta i dlatego stoją razem, z własnym
 * wyjaśnieniem: limit pakietu i cena zdjęcia ponad pakiet. To z nich bierze
 * się przychód, który dziś fotografom wyparowuje (CLAUDE.md §1).
 */
import { html, useState, useEffect, formatMoney, __ } from '../runtime.js';
import { api, ApiError } from '../api.js';
import { Field, rules, useForm } from '../form.js';
import { toast } from '../toast.js';
import { confirmDestructive } from '../dialog.js';

const THEMES = [
	{ value: 'noir', label: 'Noir' },
	{ value: 'paper', label: 'Paper' },
	{ value: 'minimal', label: 'Minimal' },
];

/**
 * Złote na grosze i z powrotem.
 *
 * Kwoty jeżdżą do API w groszach i nigdy nie są liczone na liczbach
 * zmiennoprzecinkowych; fotograf wpisuje złote.
 */
const toMinor = ( value ) => {
	const normalised = String( value ?? '' ).replace( ',', '.' ).trim();

	return '' === normalised ? null : Math.round( Number( normalised ) * 100 );
};

const toMajor = ( minor ) => ( null === minor || undefined === minor ? '' : String( minor / 100 ) );

export function GalleryForm( { gallery = null, onSaved, onClose } ) {
	const editing = null !== gallery;
	const [ clients, setClients ] = useState( [] );

	useEffect( () => {
		const controller = new AbortController();

		api.get( 'clients', { signal: controller.signal } )
			.then( ( { data } ) => setClients( data ) )
			.catch( () => {
				// Lista klientów jest wygodą, nie warunkiem utworzenia galerii.
				// Jej brak nie może zablokować formularza.
			} );

		return () => controller.abort();
	}, [] );

	const form = useForm( {
		initial: {
			title: gallery?.title ?? '',
			client_id: gallery?.client_id ?? '',
			theme: gallery?.theme ?? 'noir',
			package_limit: gallery?.package_limit ?? '',
			extra_photo_price: toMajor( gallery?.extra_photo_price ),
			expires_at: ( gallery?.expires_at ?? '' ).slice( 0, 10 ),
			allow_download: gallery?.allow_download ?? false,
		},
		validate: {
			title: [ rules.required( __( 'Podaj nazwę galerii — klient zobaczy ją w wiadomości.' ) ) ],
		},
		onSubmit: async ( values ) => {
			const payload = {
				title: values.title,
				client_id: values.client_id,
				theme: values.theme,
				package_limit: '' === String( values.package_limit ).trim()
					? null
					: Number( values.package_limit ),
				extra_photo_price: toMinor( values.extra_photo_price ),
				expires_at: values.expires_at,
				allow_download: Boolean( values.allow_download ),
			};

			const { data } = editing
				? await api.patch( `galleries/${ gallery.id }`, payload )
				: await api.post( 'galleries', payload );

			toast.success( editing ? __( 'Ustawienia zapisane.' ) : __( 'Galeria utworzona.' ) );
			onSaved( data );
			onClose();
		},
	} );

	const remove = async () => {
		const confirmed = await confirmDestructive( {
			title: __( 'Usunąć galerię?' ),
			message: __( 'Galeria trafi do kosza i będzie można ją przywrócić. Klient straci do niej dostęp natychmiast.' ),
			confirmLabel: __( 'Przenieś do kosza' ),
		} );

		if ( ! confirmed ) {
			return;
		}

		try {
			await api.delete( `galleries/${ gallery.id }` );
			toast.warning( __( 'Galeria w koszu.' ) );
			onSaved( null );
			onClose();
		} catch ( error ) {
			toast.error( error, remove );
		}
	};

	const publish = async () => {
		try {
			await api.post( `galleries/${ gallery.id }/publish`, {} );
			toast.success( __( 'Galeria opublikowana. Możesz wysłać ją klientowi.' ) );
			onSaved( null );
			onClose();
		} catch ( error ) {
			// Publikacja pustej galerii to reguła produktu, nie awaria —
			// mówimy, co jest nie tak, zamiast pokazywać czerwony komunikat
			// z propozycją ponowienia, które i tak się nie uda.
			if ( error instanceof ApiError && error.isBusinessRule ) {
				toast.warning( error.message );
			} else {
				toast.error( error, publish );
			}
		}
	};

	return html`
		<form onSubmit=${ form.submit } novalidate>
			${ form.formError ? html`<p class="kadr-form__error">${ form.formError }</p>` : null }

			<${Field}
				...${ form.field( 'title' ) }
				label=${ __( 'Nazwa galerii' ) }
				required
				placeholder=${ __( 'Ślub Marty i Piotra' ) }
				hint=${ __( 'Klient zobaczy ją w wiadomości i w nagłówku galerii.' ) }
			/>

			<div class="kadr-field">
				<label class="kadr-field__label" for="kadr-field-client_id">${ __( 'Klient' ) }</label>
				<select
					class="kadr-field__input"
					id="kadr-field-client_id"
					value=${ form.values.client_id }
					onChange=${ ( event ) => form.setValue( 'client_id', event.target.value ) }
				>
					<option value="">${ __( 'Bez przypisanego klienta' ) }</option>
					${ clients.map(
						( client ) => html`
							<option key=${ client.id } value=${ client.id }>
								${ `${ client.first_name } ${ client.last_name }`.trim() } · ${ client.email }
							</option>
						`
					) }
				</select>
				<p class="kadr-field__hint">${ __( 'Przypisanie klienta włącza jego historię i ponowne rezerwacje.' ) }</p>
			</div>

			<fieldset class="kadr-fieldset">
				<legend class="kadr-fieldset__legend">${ __( 'Pakiet i dopłata' ) }</legend>
				<p class="kadr-fieldset__note">
					${ __( 'Klient wybierze zdjęcia ponad pakiet i dopłaci za nie przy zatwierdzeniu wyboru.' ) }
				</p>

				<div class="kadr-field-pair">
					<${Field}
						...${ form.field( 'package_limit' ) }
						label=${ __( 'Zdjęć w pakiecie' ) }
						inputmode="numeric"
						placeholder=${ __( 'np. 20' ) }
						hint=${ __( 'Zostaw puste, jeśli pakiet jest bez limitu.' ) }
					/>
					<${Field}
						...${ form.field( 'extra_photo_price' ) }
						label=${ __( 'Cena ponad pakiet' ) }
						inputmode="decimal"
						placeholder=${ __( 'np. 60' ) }
						hint=${ __( 'W złotych. Wymagana przy limicie.' ) }
					/>
				</div>

				${ surchargeHint( form.values ) }
			</fieldset>

			<div class="kadr-field">
				<label class="kadr-field__label" for="kadr-field-theme">${ __( 'Motyw galerii' ) }</label>
				<select
					class="kadr-field__input"
					id="kadr-field-theme"
					value=${ form.values.theme }
					onChange=${ ( event ) => form.setValue( 'theme', event.target.value ) }
				>
					${ THEMES.map( ( theme ) => html`<option key=${ theme.value } value=${ theme.value }>${ theme.label }</option>` ) }
				</select>
			</div>

			<${Field}
				...${ form.field( 'expires_at' ) }
				label=${ __( 'Galeria dostępna do' ) }
				type="date"
				hint=${ __( 'Klient ma dostęp przez cały ten dzień. Puste znaczy bez terminu.' ) }
			/>

			<label class="kadr-check">
				<input
					type="checkbox"
					checked=${ Boolean( form.values.allow_download ) }
					onChange=${ ( event ) => form.setValue( 'allow_download', event.target.checked ) }
				/>
				<span>
					${ __( 'Pozwól pobierać zdjęcia z galerii' ) }
					<span class="kadr-field__hint">${ __( 'Wyłączone przy proofingu — pobieranie przed wyborem zabija dopłatę.' ) }</span>
				</span>
			</label>

			<div class="kadr-form__actions">
				<button type="submit" class="kadr-btn kadr-btn--primary" disabled=${ form.submitting }>
					${ form.submitting ? __( 'Zapisuję…' ) : ( editing ? __( 'Zapisz' ) : __( 'Utwórz galerię' ) ) }
				</button>
				<button type="button" class="kadr-btn kadr-btn--secondary" onClick=${ onClose }>${ __( 'Anuluj' ) }</button>

				${ editing && 'published' !== gallery.status
					? html`<button type="button" class="kadr-btn kadr-btn--ghost" onClick=${ publish }>
							${ __( 'Opublikuj' ) }
					  </button>`
					: null }

				${ editing
					? html`<button type="button" class="kadr-btn kadr-btn--ghost kadr-btn--danger-text" onClick=${ remove }>
							${ __( 'Usuń' ) }
					  </button>`
					: null }
			</div>
		</form>
	`;
}

/**
 * Podpowiedź licząca dopłatę na żywo.
 *
 * Ruch ma coś pokazywać, nie zdobić (CLAUDE.md §7): fotograf widzi, ile
 * zarobi, jeśli klientka wybierze dziesięć zdjęć ponad pakiet — i to jest
 * moment, w którym rozumie, po co ten produkt istnieje.
 */
function surchargeHint( values ) {
	const limit = Number( String( values.package_limit ).trim() );
	const price = toMinor( values.extra_photo_price );

	if ( ! Number.isFinite( limit ) || limit < 1 || null === price || price <= 0 ) {
		return null;
	}

	// Jedna linia celowo: htm zwija odstępy między wyrażeniami, więc
	// rozbicie tego na trzy linijki sklejałoby słowa z kwotą.
	return html`<p class="kadr-fieldset__result">${ __( 'Dziesięć zdjęć ponad pakiet to' ) } <strong>${ formatMoney( price * 10 ) }</strong> ${ __( 'dopłaty.' ) }</p>`;
}
