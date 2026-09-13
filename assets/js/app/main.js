/**
 * Punkt wejścia panelu fotografa.
 *
 * Widoki dochodzą w sesjach 7–10. Dziś moduł uruchamia to, co jest gotowe:
 * paletę poleceń i system powiadomień — żeby powłoka była używalna od razu,
 * a nie dopiero po dołożeniu pierwszego widoku.
 */
import { bindShortcut, registerCommands } from './palette.js';
import { toast } from './toast.js';
import { __ } from './runtime.js';

const routes = [
	{ id: 'today', label: __( 'Dzisiaj' ), group: __( 'Praca' ), path: '/app/' },
	{ id: 'galleries', label: __( 'Galerie' ), group: __( 'Praca' ), path: '/app/galerie' },
	{ id: 'clients', label: __( 'Klienci' ), group: __( 'Praca' ), path: '/app/klienci' },
	{ id: 'orders', label: __( 'Zamówienia' ), group: __( 'Sprzedaż' ), path: '/app/zamowienia' },
	{ id: 'billing', label: __( 'Rozliczenia' ), group: __( 'Studio' ), path: '/app/rozliczenia' },
];

registerCommands(
	routes.map( ( route ) => ( {
		...route,
		run: () => {
			window.location.href = route.path;
		},
	} ) )
);

bindShortcut();

// Panel jest aplikacją jednoekranową w sensie nawigacji klawiaturą, ale nie
// przechwytuje routingu — każdy adres da się otworzyć i udostępnić wprost.
window.kadr = { toast };
