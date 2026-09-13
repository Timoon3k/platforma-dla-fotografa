/**
 * Weryfikacja panelu w prawdziwej przeglądarce.
 *
 * Sprawdza to, czego testy PHP nie dosięgną: czy komponenty się renderują,
 * czy reaktywność działa i czy obsługa klawiatury jest poprawna.
 *
 * Ten test wykrył, że runtime importował sygnały z `signals-core.js` zamiast
 * z integracji `signals.js`. Wszystko liczyło się poprawnie, ale widok się
 * nie odświeżał — testy jednostkowe nie miały jak tego zobaczyć.
 *
 * Wymaga `npm install playwright-core` (narzędzie deweloperskie, nie trafia
 * do wydania). Ścieżkę do przeglądarki nadpisuje zmienna KADR_CHROMIUM.
 *
 * Użycie:  php tools/preview-app.php && node tools/check-panel.mjs
 */
import { chromium } from 'playwright-core';

const root = new URL('..', import.meta.url).pathname.replace(/\/$/, '');
const file = `file://${root}/dist/preview/panel.html`;
const errors = [];

const executablePath =
  process.env.KADR_CHROMIUM ||
  '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';

const browser = await chromium.launch({ executablePath });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
page.on('pageerror', e => errors.push('pageerror: ' + e.message));

await page.goto(file, { waitUntil: 'networkidle' });
await page.waitForTimeout(1200);

const out = {};

// 1. Tabela wyrenderowana przez Preact
out['wiersze tabeli'] = await page.locator('.kadr-table tbody tr').count();
out['nagłówki sortujące'] = await page.locator('.kadr-table__sort').count();

// 2. Sortowanie działa
const firstBefore = await page.locator('.kadr-table tbody tr').first().innerText();
await page.locator('.kadr-table__sort').first().click();
await page.waitForTimeout(200);
const firstAfter = await page.locator('.kadr-table tbody tr').first().innerText();
out['sortowanie zmienia kolejność'] = firstBefore !== firstAfter;

// 3. Paleta poleceń na Ctrl+K
await page.keyboard.press('Control+k');
await page.waitForTimeout(300);
out['paleta otwarta'] = await page.locator('.kadr-palette[open]').count() > 0;
out['pozycje palety'] = await page.locator('.kadr-palette__item').count();

// 4. Filtrowanie w palecie
await page.keyboard.type('galer');
await page.waitForTimeout(250);
out['po wpisaniu "galer"'] = await page.locator('.kadr-palette__item').count();

// 5. Escape zamyka
await page.keyboard.press('Escape');
await page.waitForTimeout(250);
out['Escape zamyka paletę'] = await page.locator('.kadr-palette[open]').count() === 0;

// 6. Dialog + pułapka fokusu
await page.locator('[data-demo="destructive"]').click();
await page.waitForTimeout(300);
out['dialog otwarty'] = await page.locator('.kadr-dialog--danger[open]').count() > 0;
out['fokus na akcji bezpiecznej'] = await page.evaluate(() =>
  document.activeElement?.classList.contains('kadr-btn--secondary'));
await page.keyboard.press('Escape');
await page.waitForTimeout(250);

// 7. Toast
await page.locator('[data-demo="toast"]').click();
await page.waitForTimeout(300);
out['powiadomienie widoczne'] = await page.locator('.kadr-toast').count() > 0;

// 8. Pusty stan
await page.locator('[data-demo="empty"]').click();
await page.waitForTimeout(200);
out['pusty stan'] = await page.locator('.kadr-empty-panel').count() > 0;
await page.locator('[data-demo="data"]').click();
await page.waitForTimeout(200);

// 9. Szuflada z formularzem: pułapka fokusu i walidacja inline
await page.locator('[data-demo="drawer"]').click();
await page.waitForTimeout(400);
out['szuflada otwarta'] = await page.locator('.kadr-drawer[open]').count() > 0;

// Wysyłka pustego formularza ma pokazać błędy pod polami, a nie wysłać nic.
await page.locator('.kadr-drawer button[type="submit"]').click();
await page.waitForTimeout(250);
out['błędy pod polami'] = await page.locator('.kadr-field__error').count();
out['pole oznaczone aria-invalid'] = await page.locator('.kadr-field__input[aria-invalid="true"]').count();
out['fokus na pierwszym błędnym polu'] = await page.evaluate(() =>
  document.activeElement?.id === 'kadr-field-name');

// Poprawka ma kasować błąd natychmiast, bez czekania na kolejną wysyłkę.
await page.locator('#kadr-field-name').fill('Ślub Marty i Piotra');
await page.waitForTimeout(250);
out['poprawka kasuje błąd'] = await page.locator('#kadr-field-name-error').count() === 0;

// Błędny adres wyłapany przy opuszczeniu pola.
await page.locator('#kadr-field-email').fill('nie-adres');
await page.locator('#kadr-field-email').blur();
await page.waitForTimeout(250);
out['walidacja e-maila przy blur'] = await page.locator('#kadr-field-email-error').count() > 0;

await page.screenshot({ path: `${root}/dist/preview/panel-drawer.png`, fullPage: false });

await page.keyboard.press('Escape');
await page.waitForTimeout(300);
out['Escape zamyka szufladę'] = await page.locator('.kadr-drawer[open]').count() === 0;

// 10. Zrzuty
await page.screenshot({ path: `${root}/dist/preview/panel-desktop.png`, fullPage: false });
await page.setViewportSize({ width: 390, height: 844 });
await page.waitForTimeout(400);
await page.screenshot({ path: `${root}/dist/preview/panel-mobile.png`, fullPage: false });

await browser.close();

for (const [k, v] of Object.entries(out)) {
  const ok = typeof v === 'boolean' ? (v ? '✓' : '✗') : (v > 0 ? '✓' : '✗');
  console.log(`  ${ok} ${k.padEnd(30)} ${v}`);
}
const failed = Object.values(out).filter(v => typeof v === 'boolean' ? !v : !(v > 0)).length;
console.log(errors.length ? `\n✗ BŁĘDY KONSOLI (${errors.length}):\n` + errors.slice(0,5).map(e=>'    '+e).join('\n') : '\n✓ Zero błędów w konsoli');


if (failed > 0 || errors.length > 0) {
  process.exit(1);
}
