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
