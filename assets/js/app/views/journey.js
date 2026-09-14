/**
 * Oś procesu w panelu fotografa.
 *
 * Odpowiada na pytanie, które klientka zadaje kilka razy przy każdej sesji:
 * „kiedy będą zdjęcia?". Fotograf odpowiada dziś ręcznie — to jedno
 * z dwudziestu przerwań, z których składają się 2–4 godziny administracji.
 *
 * Nic tu nie jest przechowywane: każdy etap wynika z danych, które i tak
 * istnieją. Oś, która jest kopią stanu, prędzej czy później skłamie.
 */
import { html, useState, useEffect, __ } from '../runtime.js';
import { api } from '../api.js';

export function JourneyStrip( { galleryId } ) {
	const [ steps, setSteps ] = useState( null );

	useEffect( () => {
		const controller = new AbortController();

		api.get( `galleries/${ galleryId }/journey`, { signal: controller.signal } )
			.then( ( { data } ) => setSteps( data ) )
			.catch( () => {
				// Oś jest informacją towarzyszącą, nie treścią widoku.
				// Gdy jej nie ma, galeria działa dalej — pokazywanie błędu
				// nad zdjęciami przeszkadzałoby bardziej niż jej brak.
			} );

		return () => controller.abort();
	}, [ galleryId ] );

	if ( null === steps ) {
		return null;
	}

	return html`
		<nav class="kadr-journey" aria-label=${ __( 'Etap sesji' ) }>
			<ol class="kadr-journey__list">
				${ steps.map(
					( step ) => html`
						<li
							key=${ step.stage }
							class="kadr-journey__step kadr-journey__step--${ step.state }"
							aria-current=${ 'current' === step.state ? 'step' : null }
						>
							<span class="kadr-journey__mark" aria-hidden="true">
								${ 'done' === step.state ? '✓' : '•' }
							</span>
							<span class="kadr-journey__label">${ __( step.label ) }</span>
							${ 'current' === step.state
								? html`<span class="kadr-sr-only">${ __( '— etap bieżący' ) }</span>`
								: null }
						</li>
					`
				) }
			</ol>
		</nav>
	`;
}
