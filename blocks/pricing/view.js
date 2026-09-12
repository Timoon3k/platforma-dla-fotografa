/**
 * Przełącznik cyklu rozliczeniowego.
 *
 * WordPress Interactivity API. Moduł ES bez kroku budowania — import
 * `@wordpress/interactivity` rozwiązuje mapa importów WordPressa 6.5+.
 * Bez JavaScriptu strona pokazuje ceny miesięczne, co jest poprawnym
 * stanem domyślnym, a nie awarią.
 */
import { store, getContext } from '@wordpress/interactivity';

store( 'kadr/pricing', {
	state: {
		get isYearly() {
			return getContext().yearly === true;
		},
		get isMonthly() {
			return getContext().yearly !== true;
		},
	},
	actions: {
		showMonthly() {
			getContext().yearly = false;
		},
		showYearly() {
			getContext().yearly = true;
		},
	},
} );
