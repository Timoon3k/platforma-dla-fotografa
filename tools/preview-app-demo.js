/**
 * Skrypt startowy podglądu panelu.
 *
 * Podgląd uruchamia PRODUKCYJNY `main.js` — te same widoki, ten sam klient
 * REST, ta sama powłoka. Jedyne, co jest tu podstawione, to sieć: `fetch`
 * zwraca przykładowe odpowiedzi w kształcie prawdziwego API.
 *
 * Dane są jawnie demonstracyjne i istnieją wyłącznie w tym pliku narzędziowym.
 * W kodzie wtyczki nie ma ani jednego wymyślonego klienta czy zamówienia
 * (CLAUDE.md §9).
 *
 * Kolejność ma znaczenie: atrapa sieci musi być zainstalowana, zanim
 * `main.js` zamontuje widok. Moduły ES wykonują się w kolejności importów,
 * więc `./preview-net.js` idzie pierwszy i nie wolno tego zamienić miejscami.
 */
import './preview-net.js';
import './main.js';
