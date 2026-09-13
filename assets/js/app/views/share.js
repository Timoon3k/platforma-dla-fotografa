/**
 * Udostępnienie galerii klientowi.
 *
 * Jeden link — to jest cały produkt z perspektywy klientki. Nie zakłada
 * konta, nie instaluje aplikacji, nie pamięta hasła: klika to, co dostała
 * SMS-em albo mailem (ADR-003).
 *
 * Dlatego ten widok robi dokładnie trzy rzeczy: tworzy link, pokazuje go
 * raz w sposób, który da się skopiować jednym kliknięciem, i pozwala go
 * unieważnić. Jawny adres istnieje TYLKO w odpowiedzi serwera — w bazie
 * jest wyłącznie hash, więc nie da się go potem odczytać.
 */
import { html, useState, useEffect, useCallback, __ } from '../runtime.js';
import { api } from '../api.js';
import { toast } from '../toast.js';
import { confirmDestructive } from '../dialog.js';

export function ShareGallery( { gallery } ) {
	const [ links, setLinks ] = useState( [] );
	const [ issued, setIssued ] = useState( null );
	const [ pin, setPin ] = useState( '' );
	const [ withPin, setWithPin ] = useState( false );
	const [ busy, setBusy ] = useState( false );

	const reload = useCallback( async () => {
		try {
			const { data } = await api.get( `galleries/${ gallery.id }/access` );

			setLinks( data );
		} catch ( error ) {
			toast.error( error, reload );
		}
	}, [ gallery.id ] );

	useEffect( () => {
		reload();
	}, [ reload ] );

	const create = async () => {
		setBusy( true );

		try {
			const { data } = await api.post( `galleries/${ gallery.id }/access`, {
				pin: withPin ? pin : null,
			} );

			setIssued( data );
			setPin( '' );
			setWithPin( false );
			reload();
		} catch ( error ) {
			toast.error( error );
		} finally {
			setBusy( false );
		}
	};

	const revoke = async ( link ) => {
		const confirmed = await confirmDestructive( {
			title: __( 'Unieważnić ten link?' ),
			message: __( 'Klient straci dostęp natychmiast. Jeśli już otworzył galerię, przestanie działać przy następnym wejściu.' ),
			confirmLabel: __( 'Unieważnij' ),
		} );

		if ( ! confirmed ) {
			return;
		}

		try {
			await api.delete( `galleries/${ gallery.id }/access/${ link.id }` );
			toast.warning( __( 'Link unieważniony.' ) );
			reload();
		} catch ( error ) {
			toast.error( error );
		}
	};

	/**
	 * Podgląd oczami klientki.
	 *
	 * Otwiera prawdziwy link w nowej karcie — nie makietę i nie „tryb
	 * podglądu". Fotograf ma zobaczyć dokładnie to, co zobaczy klientka,
	 * łącznie z PIN-em, jeśli go ustawił. Każda symulacja kłamałaby
	 * w szczegółach, a to w nich siedzą problemy.
	 */
	const preview = () => {
		if ( ! issued ) {
			toast.info( __( 'Utwórz link — podgląd otwiera dokładnie to, co zobaczy klientka.' ) );

			return;
		}

		window.open( issued.url, '_blank', 'noopener' );
	};

	if ( 'published' !== gallery.status ) {
		return html`
			<div class="kadr-empty-panel">
				<p class="kadr-empty-panel__title">${ __( 'Najpierw opublikuj galerię' ) }</p>
				<p class="kadr-empty-panel__text">
					${ __( 'Link działa dopiero dla opublikowanej galerii. Wycofanie publikacji zamyka wszystkie wydane linki naraz.' ) }
				</p>
			</div>
		`;
	}

	return html`
		${ issued ? html`<${IssuedLink} link=${ issued } />` : null }

		<section class="kadr-panel">
			<h2 class="kadr-panel__title">${ __( 'Nowy link' ) }</h2>

			<label class="kadr-check">
				<input type="checkbox" checked=${ withPin } onChange=${ ( event ) => setWithPin( event.target.checked ) } />
				<span>
					${ __( 'Zabezpiecz czterocyfrowym PIN-em' ) }
					<span class="kadr-field__hint">
						${ __( 'Klientka poda go raz. Zatrzyma kogoś, kto zobaczył link przez ramię.' ) }
					</span>
				</span>
			</label>

			${ withPin
				? html`<div class="kadr-field" style="max-width:12rem">
						<label class="kadr-field__label" for="kadr-share-pin">${ __( 'PIN' ) }</label>
						<input
							class="kadr-field__input"
							id="kadr-share-pin"
							inputmode="numeric"
							maxlength="4"
							placeholder="0000"
							value=${ pin }
							onInput=${ ( event ) => setPin( event.target.value.replace( /\D/g, '' ).slice( 0, 4 ) ) }
						/>
				  </div>`
				: null }

			<div class="kadr-form__actions">
				<button
					type="button"
					class="kadr-btn kadr-btn--primary"
					disabled=${ busy || ( withPin && 4 !== pin.length ) }
					onClick=${ create }
				>${ busy ? __( 'Tworzę…' ) : __( 'Utwórz link' ) }</button>
				<button type="button" class="kadr-btn kadr-btn--secondary" onClick=${ preview }>
					${ __( 'Zobacz oczami klientki' ) }
				</button>
			</div>
		</section>

		<${LinkList} links=${ links } onRevoke=${ revoke } />
	`;
}

/**
 * Świeżo utworzony link.
 *
 * Pokazujemy go raz i mówimy wprost, że drugi raz go nie będzie — inaczej
 * fotograf zamknie panel i zacznie szukać, gdzie ten adres się zapisał.
 */
function IssuedLink( { link } ) {
	const [ copied, setCopied ] = useState( false );

	const copy = async () => {
		try {
			await navigator.clipboard.writeText( link.url );
			setCopied( true );
			toast.success( __( 'Link skopiowany.' ) );
		} catch ( error ) {
			// Schowek bywa zablokowany (brak HTTPS, uprawnienia przeglądarki).
			// Adres jest widoczny w polu, więc da się go zaznaczyć ręcznie.
			toast.warning( __( 'Nie udało się skopiować. Zaznacz adres i skopiuj ręcznie.' ) );
		}
	};

	return html`
		<section class="kadr-panel kadr-panel--accent" aria-labelledby="kadr-new-link">
			<h2 class="kadr-panel__title" id="kadr-new-link">${ __( 'Link gotowy' ) }</h2>
			<p class="kadr-field__hint" style="margin-bottom:12px">
				${ __( 'Skopiuj go teraz — ze względów bezpieczeństwa nie da się go odczytać później. Zgubiony link zastępujemy nowym.' ) }
			</p>

			<div class="kadr-share__row">
				<input class="kadr-field__input" readonly value=${ link.url } onFocus=${ ( event ) => event.target.select() } />
				<button type="button" class="kadr-btn ${ copied ? 'kadr-btn--secondary' : 'kadr-btn--primary' }" onClick=${ copy }>
					${ copied ? __( 'Skopiowano' ) : __( 'Kopiuj' ) }
				</button>
			</div>

			${ link.has_pin
				? html`<p class="kadr-field__hint">${ __( 'Wyślij PIN osobno — najlepiej innym kanałem niż link.' ) }</p>`
				: null }
		</section>
	`;
}

function LinkList( { links, onRevoke } ) {
	if ( 0 === links.length ) {
		return null;
	}

	return html`
		<section class="kadr-panel">
			<h2 class="kadr-panel__title">${ __( 'Wydane linki' ) }</h2>
			<ul class="kadr-panel__list">
				${ links.map(
					( link ) => html`
						<li key=${ link.id } class="kadr-panel__row">
							<span class="kadr-share__link">
								<span>${ link.has_pin ? __( 'Link z PIN-em' ) : __( 'Link' ) }</span>
								<span class="kadr-table__meta">
									${ link.opened > 0
										? `${ __( 'otwarty' ) } ${ link.opened }×`
										: __( 'jeszcze nieotwarty' ) }
								</span>
							</span>
							${ link.active
								? html`<button type="button" class="kadr-btn kadr-btn--ghost kadr-btn--danger-text" onClick=${ () => onRevoke( link ) }>
										${ __( 'Unieważnij' ) }
								  </button>`
								: html`<span class="kadr-status kadr-status--draft">${ __( 'Nieaktywny' ) }</span>` }
						</li>
					`
				) }
			</ul>
		</section>
	`;
}
