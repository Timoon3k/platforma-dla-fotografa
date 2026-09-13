/**
 * Weryfikacja panelu w prawdziwej przeglądarce.
 *
 * Sprawdza to, czego testy PHP nie dosięgną: czy widoki się renderują, czy
 * dane z API trafiają na ekran, czy reaktywność działa i czy obsługa
 * klawiatury jest poprawna.
 *
 * Uruchamiane są PRODUKCYJNE moduły panelu na powłoce renderowanej przez
 * produkcyjną klasę `Shell`. Podstawiona jest wyłącznie sieć.
 *
 * Ten test wykrył dotąd: import sygnałów z rdzenia zamiast z integracji
 * z Preactem (wszystko liczyło się poprawnie, nic się nie przerysowywało)
 * oraz nawigację odjeżdżającą razem ze stroną.
 *
 * Wymaga `npm install playwright-core` (narzędzie deweloperskie, nie trafia
 * do wydania). Ścieżkę do przeglądarki nadpisuje zmienna KADR_CHROMIUM.
 *
 * Użycie:  php tools/preview-app.php && php tools/preview-components.php
 *          && php tools/preview-auth.php && node tools/check-panel.mjs
 */
import { chromium } from 'playwright-core';

const root = new URL('..', import.meta.url).pathname.replace(/\/$/, '');
const preview = `file://${root}/dist/preview`;
const errors = [];
const out = {};

const executablePath =
  process.env.KADR_CHROMIUM ||
  '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';

const browser = await chromium.launch({ executablePath });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
page.on('pageerror', e => errors.push('pageerror: ' + e.message));

const open = async (file) => {
  await page.goto(`${preview}/${file}`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(900);
};

/* ---------------------------------------------------------------------
 * 1. Powłoka renderowana przez serwer
 * ------------------------------------------------------------------- */
await open('panel.html');

out['powłoka jest w HTML-u'] = await page.locator('.kadr-app__nav .kadr-nav__item').count();
out['podświetlona sekcja'] = await page.locator('.kadr-nav__item[aria-current="page"]').count() === 1;
out['nawigacja nie przewija się z treścią'] = await page.evaluate(() => {
  const main = document.querySelector('.kadr-app__main');
  const nav = document.querySelector('.kadr-app__nav');
  const before = nav.getBoundingClientRect().top;
  main.scrollTop = 400;
  return nav.getBoundingClientRect().top === before;
});

/* ---------------------------------------------------------------------
 * 2. Widok „Dzisiaj" na danych z API
 * ------------------------------------------------------------------- */
out['kafle widoku Dzisiaj'] = await page.locator('.kadr-tiles .kadr-tile').count();
out['liczba klientów z API'] = await page.locator('.kadr-tile__value').nth(1).innerText();
out['pasek zużycia miejsca'] = await page.locator('.kadr-meter__fill').count() > 0;
out['galerie z kończącą się ważnością'] = await page.locator('.kadr-panel__row').count() > 0;

/* ---------------------------------------------------------------------
 * 3. Lista galerii: filtr i wyszukiwarka po stronie serwera
 * ------------------------------------------------------------------- */
await open('panel-galerie.html');

out['wiersze listy galerii'] = await page.locator('.kadr-table tbody tr').count();

await page.locator('.kadr-segmented__item', { hasText: 'Szkice' }).click();
await page.waitForTimeout(500);
out['filtr "Szkice" zawęża listę'] = await page.locator('.kadr-table tbody tr').count() === 1;

await page.locator('.kadr-segmented__item', { hasText: 'Wszystkie' }).click();
await page.waitForTimeout(500);

await page.locator('.kadr-toolbar .kadr-search__input').fill('Marty');
await page.waitForTimeout(700);
out['wyszukiwanie zawęża listę'] = await page.locator('.kadr-table tbody tr').count() === 1;

await page.locator('.kadr-toolbar .kadr-search__input').fill('nie ma takiej galerii');
await page.waitForTimeout(700);
out['pusty wynik ma własny komunikat'] = await page.locator('.kadr-empty-panel').count() > 0;

/* ---------------------------------------------------------------------
 * 3b. Szuflada z ustawieniami galerii
 * ------------------------------------------------------------------- */
await open('panel-galerie.html');

await page.locator('.kadr-view__actions .kadr-btn--primary').click();
await page.waitForTimeout(500);
out['szuflada nowej galerii'] = await page.locator('.kadr-drawer[open]').count() > 0;

// Limit pakietu bez ceny za nadmiar to darmowe zdjęcia ponad pakiet —
// formularz musi to zatrzymać, zanim pójdzie do serwera.
await page.locator('#kadr-field-title').fill('Ślub Marty i Piotra');
await page.locator('#kadr-field-package_limit').fill('20');
await page.waitForTimeout(200);
out['licznik dopłaty milczy bez ceny'] = await page.locator('.kadr-fieldset__result').count() === 0;

await page.locator('#kadr-field-extra_photo_price').fill('60');
await page.waitForTimeout(250);
const surcharge = await page.locator('.kadr-fieldset__result').innerText();
out['licznik dopłaty liczy na żywo'] = surcharge.includes('600');

out['klienci wczytani do wyboru'] = await page.locator('#kadr-field-client_id option').count() > 1;

await page.locator('.kadr-drawer button[type="submit"]').click();
await page.waitForTimeout(800);
out['utworzenie zamyka szufladę'] = await page.locator('.kadr-drawer[open]').count() === 0;
out['nowa galeria na liście'] = (await page.locator('.kadr-table tbody tr').first().innerText())
  .includes('Ślub Marty i Piotra');

// Kliknięcie wiersza otwiera ustawienia istniejącej galerii.
await page.locator('.kadr-table tbody tr').nth(1).click();
await page.waitForTimeout(500);
out['wiersz otwiera ustawienia'] = await page.evaluate(() =>
  document.querySelector('.kadr-drawer #kadr-field-title')?.value?.length > 0);
await page.keyboard.press('Escape');
await page.waitForTimeout(300);

/* ---------------------------------------------------------------------
 * 3c. Widok galerii: wirtualizacja siatki i wysyłanie zdjęć
 * ------------------------------------------------------------------- */
await open('panel-galeria.html');

out['liczba zdjęć z API'] = (await page.locator('.kadr-view__subtitle').innerText()).includes('420');

const gridHeight = await page.locator('.kadr-grid').evaluate(el => parseInt(el.style.height, 10));
const drawnFirst = await page.locator('.kadr-grid__item').count();

// Wirtualizacja: rysujemy garść kadrów, ale rezerwujemy wysokość wszystkich,
// więc pasek przewijania nie kłamie.
out['siatka rysuje tylko widoczne'] = drawnFirst > 0 && drawnFirst < 100;
out['wysokość zarezerwowana dla wszystkich'] = gridHeight > 10000;

await page.evaluate(() => { document.querySelector('.kadr-app__main').scrollTop = 6000; });
await page.waitForTimeout(700);
out['przewinięcie doczytuje kolejne kadry'] =
  (await page.locator('.kadr-grid__item').first().innerText()).includes('DSC_11');
out['liczba narysowanych nadal ograniczona'] = await page.locator('.kadr-grid__item').count() < 100;

await page.evaluate(() => { document.querySelector('.kadr-app__main').scrollTop = 0; });
await page.waitForTimeout(400);

// Zdjęcie czekające na warianty ma zarezerwowane miejsce, nie puste pole.
out['zdjęcie w przetwarzaniu ma placeholder'] = await page.locator('.kadr-grid__pending').count() > 0;

// Wysyłka pliku od początku do końca. Atrapa serwera liczy SHA-256
// niezależnie (SubtleCrypto) i odrzuca niezgodny — więc ten test weryfikuje
// naszą własną implementację skrótu na prawdziwym pliku.
await page.setInputFiles('.kadr-drop input[type=file]', [
  { name: 'DSC_9001.jpg', mimeType: 'image/jpeg', buffer: Buffer.alloc(300000, 7) },
]);
await page.waitForTimeout(2500);

out['plik trafił do kolejki'] = await page.locator('.kadr-upload__row').count() === 1;
// Rozmiar pliku w jednostce, która coś znaczy: 300 kB nie może pokazywać
// się jako „0 MB”.
out['rozmiar pliku czytelny'] = (await page.locator('.kadr-upload__size').first().innerText())
  .includes('kB');
out['skrót zgodny, wysyłka ukończona'] =
  (await page.locator('.kadr-upload__state').first().innerText()).includes('Gotowe');

await page.screenshot({ path: `${root}/dist/preview/panel-upload.png` });

/* ---------------------------------------------------------------------
 * 3c-quater. Dostawa plików
 *
 * Trzeci z czterech etapów, na których produkt zarabia — i ten, który
 * generuje polecenia. Pakowanie wesela trwa kilkanaście minut, więc ekran
 * musi pokazywać POSTĘP, a nie kręciołek: bez „12 z 28" fotograf nie wie,
 * czy coś się dzieje, czy się zawiesiło.
 * ------------------------------------------------------------------- */
const delivery = page.locator('.kadr-panel:has-text("Pliki do pobrania")');

out['panel dostawy widoczny'] = await delivery.count() === 1;
out['gotowa paczka podaje rozmiar'] =
  (await delivery.locator('.kadr-field__hint').first().innerText()).includes('112 MB');
// htm zwija odstępy między wyrażeniami — ta sama pułapka zjadła już
// etykietę linku do galerii.
out['rozmiar nie sklejony z liczbą'] =
  (await delivery.locator('.kadr-field__hint').first().innerText()).includes('· 112');

// Wydanie linku: jawna wartość tokenu istnieje wyłącznie w tej odpowiedzi.
// Celujemy w przycisk po nazwie, nie po pozycji: dołożenie drugiej akcji
// obok przesunęłoby „ostatni primary" na coś zupełnie innego.
await delivery.getByRole('button', { name: 'Wydaj link' }).click();
await page.waitForTimeout(700);
out['link do pobrania pokazany'] =
  (await delivery.locator('.kadr-panel--accent input').inputValue()).includes('/d/');
out['ostrzeżenie o wygaśnięciu'] =
  (await delivery.locator('.kadr-panel--accent').innerText()).includes('przez dobę');

await page.screenshot({ path: `${root}/dist/preview/panel-dostawa.png` });

// Powiadomienie klientki jest JAWNĄ decyzją fotografa, nie efektem
// ubocznym spakowania — więc ma własny przycisk.
await delivery.getByRole('button', { name: 'Powiadom klientkę' }).click();
await page.waitForTimeout(700);
out['powiadomienie potwierdza adres'] =
  (await page.locator('.kadr-toast').last().innerText()).includes('@');

// Zlecenie pakowania: pasek postępu mówi, ile z ilu.
await delivery.getByRole('button', { name: 'Spakuj jeszcze raz' }).click();
await page.waitForTimeout(3800);
const packing = await delivery.locator('.kadr-upload__total').innerText();

out['pakowanie pokazuje postęp'] = /Pakuj\u0119 \d+ z 28/.test(packing);
// Postęp czytany przez czytnik ekranu, nie tylko widziany.
out['postęp ogłaszany na żywo'] =
  await delivery.locator('[role="status"][aria-live="polite"]').count() === 1;
out['w trakcie pakowania nie da się zlecić drugi raz'] =
  await delivery.getByRole('button', { name: 'Przygotuj pliki' }).isDisabled();

/* ---------------------------------------------------------------------
 * 3c-ter. Zaznaczanie wielu kadrów i układanie kolejności
 *
 * Kolejność decyduje o tym, czy klientka przewinie galerię dalej —
 * pierwsze dwadzieścia kadrów to całe otwarcie. Fotograf układa je ręcznie,
 * więc te ruchy muszą działać dokładnie tak, jak wyglądają.
 * ------------------------------------------------------------------- */
// Nazwę kadru czytamy z etykiety dostępnościowej znacznika zaznaczenia —
// podpis pod zdjęciem pojawia się dopiero przy najechaniu.
const nameAt = async (index) =>
  await page.locator('.kadr-grid__pick').nth(index).getAttribute('aria-label');

out['każdy kadr ma znacznik zaznaczenia'] = await page.locator('.kadr-grid__pick').count() > 0;
out['pasek ukryty, dopóki nic nie zaznaczono'] = await page.locator('.kadr-selectbar').count() === 0;

await page.locator('.kadr-grid__pick').nth(2).click();
await page.waitForTimeout(150);
// Shift zaznacza ZAKRES — bez tego wybranie czterdziestu kadrów to
// czterdzieści kliknięć.
await page.locator('.kadr-grid__pick').nth(5).click({ modifiers: ['Shift'] });
await page.waitForTimeout(250);

out['Shift zaznacza zakres'] = await page.locator('.kadr-grid__item--picked').count() === 4;
out['pasek podaje liczbę po polsku'] =
  (await page.locator('.kadr-selectbar__count').innerText()).trim() === '4 zaznaczone';

await page.screenshot({ path: `${root}/dist/preview/panel-zaznaczenie.png` });

const movedName = await nameAt(2);

await page.locator('.kadr-selectbar .kadr-btn--secondary').first().click();
await page.waitForTimeout(900);

out['„Na początek" przenosi zaznaczenie'] = (await nameAt(0)) === movedName;
out['po przeniesieniu zaznaczenie znika'] = await page.locator('.kadr-selectbar').count() === 0;

// Pojedyncze kliknięcie odznacza — ten sam przycisk w obie strony.
await page.locator('.kadr-grid__pick').first().click();
await page.waitForTimeout(150);
await page.locator('.kadr-grid__pick').first().click();
await page.waitForTimeout(200);
out['ponowne kliknięcie odznacza'] = await page.locator('.kadr-selectbar').count() === 0;

/* ---------------------------------------------------------------------
 * 3c-bis. Wybór klientki widziany od strony fotografa
 *
 * To jest ekran, na którym fotograf dowiaduje się, ile wynosi dopłata —
 * czyli miejsce, w którym produkt zarabia. Liczby muszą się zgadzać
 * z rozliczeniem serwera co do grosza, bo fotograf wystawi na ich
 * podstawie fakturę.
 * ------------------------------------------------------------------- */
const selection = page.locator('.kadr-app__main .kadr-panel--accent');

out['panel wyboru widoczny'] = await selection.count() === 1;
out['wybór oznaczony jako zatwierdzony'] =
  (await selection.locator('.kadr-status').innerText()).includes('Wybór wysłany');

const tiles = await selection.locator('.kadr-tile').allInnerTexts();
const tile = (label) => tiles.find((text) => text.startsWith(label)) || '';

out['kafelek wybranych'] = tile('Wybrane').includes('28');
out['kafelek ulubionych'] = tile('Ulubione').includes('46');
out['kafelek ponad pakiet'] = tile('Ponad pakiet').includes('8');
// 8 × 60 zł = 480 zł. Ta liczba to cała wartość ekonomiczna produktu.
out['kwota dopłaty co do grosza'] = tile('Do dopłaty').includes('480');
out['dopłata wyróżniona sygnałem'] =
  await selection.locator('.kadr-tile__value--signal').count() === 1;

await page.screenshot({ path: `${root}/dist/preview/panel-wybor.png` });

// Otwarcie wyboru ponownie: fotograf musi móc cofnąć zatwierdzenie,
// gdy klientka zadzwoni z poprawką. Krok przez potwierdzenie jest celowy —
// klientka zobaczy zmianę natychmiast, więc to nie jest kliknięcie w próżnię.
await selection.locator('.kadr-btn--secondary').click();
await page.waitForTimeout(300);
out['ponowne otwarcie pyta o potwierdzenie'] =
  await page.locator('.kadr-dialog[open]').count() > 0;

// Escape to rezygnacja: stan nie może się zmienić.
await page.keyboard.press('Escape');
await page.waitForTimeout(400);
out['rezygnacja nie zmienia stanu'] =
  (await page.locator('.kadr-app__main .kadr-status').first().innerText())
    .includes('Wybór wysłany');

await selection.locator('.kadr-btn--secondary').click();
await page.waitForTimeout(300);
await page.locator('.kadr-dialog[open] .kadr-btn--primary').click();
await page.waitForTimeout(800);
out['ponowne otwarcie zmienia stan'] =
  (await page.locator('.kadr-app__main .kadr-status').first().innerText())
    .includes('Otwarty ponownie');
out['po otwarciu znika akcent zatwierdzenia'] =
  await page.locator('.kadr-app__main .kadr-panel--accent').count() === 0;

/* ---------------------------------------------------------------------
 * 3d. Wysłanie galerii klientowi
 * ------------------------------------------------------------------- */
await open('panel-galeria.html');

await page.locator('.kadr-view__actions .kadr-btn--primary').click();
await page.waitForTimeout(500);
out['szuflada udostępniania'] = await page.locator('.kadr-drawer[open]').count() > 0;

await page.locator('.kadr-drawer[open] .kadr-btn--primary').click();
await page.waitForTimeout(600);

// Jawny adres istnieje tylko raz — widok musi go pokazać od razu
// i powiedzieć wprost, że drugi raz go nie będzie.
// Zakres zawężamy do szuflady: panel wyboru klientki w widoku galerii też
// bywa akcentowany (`.kadr-panel--accent`), gdy klientka zatwierdziła wybór.
const drawer = page.locator('.kadr-drawer[open]');
const issued = await drawer.locator('.kadr-panel--accent input').inputValue();
out['link pokazany od razu'] = issued.includes('/g/');
out['ostrzeżenie o jednorazowości'] = (await drawer.locator('.kadr-panel--accent').innerText())
  .includes('nie da się go odczytać później');
out['link trafia na listę'] = await drawer.locator('.kadr-panel__row').count() >= 1;

await page.screenshot({ path: `${root}/dist/preview/panel-share.png` });
await page.keyboard.press('Escape');
await page.waitForTimeout(300);

/* ---------------------------------------------------------------------
 * 4. Paleta poleceń zbudowana z nawigacji
 * ------------------------------------------------------------------- */
await open('panel-galerie.html');
await page.keyboard.press('Control+k');
await page.waitForTimeout(300);
out['paleta otwarta'] = await page.locator('.kadr-palette[open]').count() > 0;
// Paleta pokazuje osiem pozycji, zanim ktoś zacznie pisać — długa lista
// bez filtra nie pomaga, a spycha wynik wyszukiwania poza ekran.
out['polecenia z nawigacji'] = await page.locator('.kadr-palette__item').count() === 8;

await page.keyboard.type('klien');
await page.waitForTimeout(250);
out['po wpisaniu "klien"'] = await page.locator('.kadr-palette__item').count();

await page.keyboard.press('Escape');
await page.waitForTimeout(250);
out['Escape zamyka paletę'] = await page.locator('.kadr-palette[open]').count() === 0;

/* ---------------------------------------------------------------------
 * 5. Klienci
 * ------------------------------------------------------------------- */
await open('panel-klienci.html');
out['wiersze listy klientów'] = await page.locator('.kadr-table tbody tr').count();

/* ---------------------------------------------------------------------
 * 5b. Skrzynka wyborów — ekran, od którego zaczyna się dzień fotografa
 * ------------------------------------------------------------------- */
await open('panel-wybory.html');

out['skrzynka ma wiersze'] = await page.locator('.kadr-table tbody tr').count() === 4;
// Zatwierdzone na górze: to one czekają na ruch fotografa.
out['zatwierdzone na górze'] = (await page.locator('.kadr-table tbody tr').first().innerText())
  .includes('Czeka na Ciebie');
out['suma dopłat w nagłówku'] =
  (await page.locator('.kadr-tile').nth(2).innerText()).includes('480');

const inboxRow = page.locator('.kadr-table tbody tr').first();
out['kwota w wierszu wyróżniona'] =
  await inboxRow.locator('.kadr-table__signal').count() === 1;
out['nadmiar ponad pakiet wyróżniony'] =
  (await inboxRow.locator('.kadr-table__warning').innerText()).trim() === '8';

// Wybór mieszczący się w pakiecie pokazuje jawne zero, nie pustkę:
// fotograf ma wiedzieć, że policzone, a nie że brakuje danych.
out['brak dopłaty to jawne zero'] =
  (await page.locator('.kadr-table tbody tr').nth(1).innerText()).includes('0');

// Wybór w toku nie pokazuje kwoty — liczba, która jeszcze się zmieni,
// nie jest informacją.
out['wybór w toku bez kwoty'] =
  ! (await page.locator('.kadr-table tbody tr').nth(2).innerText()).includes('zł');

out['wiersz prowadzi do galerii'] =
  (await inboxRow.locator('.kadr-table__link').getAttribute('href')).includes('/galerie/');

await page.screenshot({ path: `${root}/dist/preview/panel-wybory.png` });

/* ---------------------------------------------------------------------
 * 6. Sekcja bez widoku mówi to wprost
 * ------------------------------------------------------------------- */
await open('panel-produkty.html');
out['sekcja bez widoku ma stan przejściowy'] = await page.locator('.kadr-empty-panel').count() > 0;

/* ---------------------------------------------------------------------
 * 7. Katalog komponentów: dialog, szuflada, formularz
 * ------------------------------------------------------------------- */
await open('components.html');

out['tabela w katalogu'] = await page.locator('.kadr-table tbody tr').count();

const firstBefore = await page.locator('.kadr-table tbody tr').first().innerText();
await page.locator('.kadr-table__sort').first().click();
await page.waitForTimeout(250);
const firstAfter = await page.locator('.kadr-table tbody tr').first().innerText();
out['sortowanie zmienia kolejność'] = firstBefore !== firstAfter;

await page.locator('[data-demo="destructive"]').click();
await page.waitForTimeout(300);
out['dialog otwarty'] = await page.locator('.kadr-dialog--danger[open]').count() > 0;
out['fokus na akcji bezpiecznej'] = await page.evaluate(() =>
  document.activeElement?.classList.contains('kadr-btn--secondary'));
await page.keyboard.press('Escape');
await page.waitForTimeout(250);

await page.locator('[data-demo="toast"]').click();
await page.waitForTimeout(300);
out['powiadomienie widoczne'] = await page.locator('.kadr-toast').count() > 0;

await page.locator('[data-demo="drawer"]').click();
await page.waitForTimeout(400);
out['szuflada otwarta'] = await page.locator('.kadr-drawer[open]').count() > 0;

await page.locator('.kadr-drawer button[type="submit"]').click();
await page.waitForTimeout(300);
out['błędy pod polami'] = await page.locator('.kadr-field__error').count();
out['pole oznaczone aria-invalid'] = await page.locator('.kadr-field__input[aria-invalid="true"]').count();
out['fokus na pierwszym błędnym polu'] = await page.evaluate(() =>
  document.activeElement?.id === 'kadr-field-name');

await page.locator('#kadr-field-name').fill('Ślub Marty i Piotra');
await page.waitForTimeout(250);
out['poprawka kasuje błąd'] = await page.locator('#kadr-field-name-error').count() === 0;

await page.locator('#kadr-field-email').fill('nie-adres');
await page.locator('#kadr-field-email').blur();
await page.waitForTimeout(250);
out['walidacja e-maila przy blur'] = await page.locator('#kadr-field-email-error').count() > 0;

await page.screenshot({ path: `${root}/dist/preview/panel-drawer.png` });
await page.keyboard.press('Escape');
await page.waitForTimeout(300);
out['Escape zamyka szufladę'] = await page.locator('.kadr-drawer[open]').count() === 0;

/* ---------------------------------------------------------------------
 * 8. Rejestracja i logowanie
 * ------------------------------------------------------------------- */
await open('rejestracja.html');

out['formularz rejestracji'] = await page.locator('.kadr-auth__panel .kadr-field').count() === 3;
out['fakty planu z cennika'] = await page.locator('.kadr-auth__facts li').count() > 0;

// Pusta wysyłka zatrzymuje się na walidacji, nie idzie do serwera.
await page.locator('.kadr-auth button[type="submit"]').click();
await page.waitForTimeout(300);
out['rejestracja waliduje przed wysyłką'] = await page.locator('.kadr-field__error').count() === 3;

// Zajęty adres wraca z serwera i ląduje POD polem e-mail, nie w toaście.
await page.locator('#kadr-field-studio').fill('Studio Kadr');
await page.locator('#kadr-field-email').fill('zajety@example.test');
await page.locator('#kadr-field-password').fill('dlugie-haslo-1');
await page.locator('.kadr-auth button[type="submit"]').click();
await page.waitForTimeout(700);
out['błąd serwera pod polem e-mail'] = await page.locator('#kadr-field-email-error').count() > 0;

await page.screenshot({ path: `${root}/dist/preview/auth-register.png` });

await open('logowanie.html');
out['formularz logowania'] = await page.locator('.kadr-auth__panel .kadr-field').count() === 2;

await page.locator('#kadr-field-email').fill('foto@example.test');
await page.locator('#kadr-field-password').fill('zle-haslo');
await page.locator('.kadr-auth button[type="submit"]').click();
await page.waitForTimeout(700);
// Złe hasło nie wskazuje pola — komunikat jest jeden i nie zdradza,
// czy konto istnieje.
out['złe hasło to komunikat formularza'] = await page.locator('.kadr-form__error').count() === 1;

await page.screenshot({ path: `${root}/dist/preview/auth-signin.png` });

/* ---------------------------------------------------------------------
 * 9. Zrzuty ekranu
 * ------------------------------------------------------------------- */
await open('panel.html');
await page.screenshot({ path: `${root}/dist/preview/panel-desktop.png` });

await open('panel-galerie.html');
await page.screenshot({ path: `${root}/dist/preview/panel-galerie.png` });

await page.setViewportSize({ width: 390, height: 844 });
await open('panel.html');
await page.screenshot({ path: `${root}/dist/preview/panel-mobile.png` });

/* Nawigacja na telefonie chowa się w szufladzie. */
out['nawigacja schowana na telefonie'] = await page.evaluate(() => {
  const nav = document.querySelector('.kadr-app__nav');
  return 'false' === nav.dataset.open;
});
await page.locator('.kadr-app__menu').click();
await page.waitForTimeout(350);
out['przycisk otwiera nawigację'] = await page.evaluate(() => {
  const nav = document.querySelector('.kadr-app__nav');
  const button = document.querySelector('.kadr-app__menu');
  return 'true' === nav.dataset.open && 'true' === button.getAttribute('aria-expanded');
});
await page.screenshot({ path: `${root}/dist/preview/panel-mobile-nav.png` });

await browser.close();

for (const [k, v] of Object.entries(out)) {
  const ok = typeof v === 'boolean' ? v : (typeof v === 'number' ? v > 0 : String(v).length > 0);
  console.log(`  ${ok ? '✓' : '✗'} ${k.padEnd(36)} ${v}`);
}

console.log(errors.length
  ? `\n✗ BŁĘDY KONSOLI (${errors.length}):\n` + errors.slice(0, 5).map(e => '    ' + e).join('\n')
  : '\n✓ Zero błędów w konsoli');

const failed = Object.values(out).filter(v =>
  typeof v === 'boolean' ? !v : (typeof v === 'number' ? !(v > 0) : !String(v).length)
).length;

if (failed > 0 || errors.length > 0) {
  process.exit(1);
}
