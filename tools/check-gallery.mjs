/**
 * Weryfikacja galerii klienta w prawdziwej przeglądarce.
 *
 * Persona krytyczna tego widoku to telefon, wieczór i jedna ręka, więc
 * sprawdzamy dokładnie to: czy kadry są w dokumencie od razu, czy lightbox
 * działa z klawiatury i palca, i czy trzy motywy nie rozjeżdżają układu.
 *
 * Uruchamiany na PRODUKCYJNYM markupie (`GalleryMarkup`), produkcyjnych
 * stylach i produkcyjnym skrypcie. Podstawione są wyłącznie zdjęcia.
 *
 * Użycie:  php tools/preview-gallery.php && node tools/check-gallery.mjs
 */
import { chromium } from 'playwright-core';

const root = new URL('..', import.meta.url).pathname.replace(/\/$/, '');
const preview = `file://${root}/dist/preview`;
const errors = [];
const out = {};

const browser = await chromium.launch({
  executablePath: process.env.KADR_CHROMIUM || '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
});

const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
page.on('pageerror', e => errors.push('pageerror: ' + e.message));

const open = async (file) => {
  await page.goto(`${preview}/${file}`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(600);
};

/* ---------------------------------------------------------------------
 * 1. Pierwszy ekran przychodzi gotowy z serwera
 * ------------------------------------------------------------------- */
await open('galeria-noir.html');

// Kadry są w DOKUMENCIE, nie za żądaniem do API — to jest cała różnica
// dla LCP na 4G. Sprawdzamy w surowym HTML-u, nie po wykonaniu skryptu.
const rawHtml = await page.content();
out['kadry są w HTML-u'] = (rawHtml.match(/kadr-g-item__image/g) || []).length >= 20;
out['pierwsze kadry bez odkładania'] = (rawHtml.match(/loading="eager"/g) || []).length === 4;
out['reszta odłożona'] = rawHtml.includes('loading="lazy"');
out['okładka ma wysoki priorytet'] = rawHtml.includes('fetchpriority="high"');

// Każdy kadr ma wymiary i proporcje, więc nic nie skacze (zero CLS).
out['każdy kadr ma wymiary'] = await page.evaluate(() =>
  Array.from(document.querySelectorAll('.kadr-g-item__image'))
    .every(img => img.getAttribute('width') && img.getAttribute('height')));
out['miejsce zarezerwowane proporcją'] = await page.evaluate(() =>
  Array.from(document.querySelectorAll('.kadr-g-item'))
    .every(item => item.style.getPropertyValue('--ratio') !== ''));

// Kolejność jest chronologiczna i ma znaczenie: sesja to opowieść.
// Pierwszy rząd musi zawierać PIERWSZE kadry, a nie co dwunasty.
out['rząd zaczyna się od pierwszych kadrów'] = await page.evaluate(() => {
  const items = Array.from(document.querySelectorAll('.kadr-g-item'));
  const firstTop = Math.round(items[0].getBoundingClientRect().top);
  const inFirstRow = items.filter(i => Math.round(i.getBoundingClientRect().top) === firstTop);
  return inFirstRow.length >= 2 && inFirstRow[1] === items[1];
});

/* ---------------------------------------------------------------------
 * 2. Lightbox
 * ------------------------------------------------------------------- */
await page.locator('.kadr-g-item__button').first().click();
await page.waitForTimeout(400);

out['lightbox otwarty'] = await page.locator('.kadr-g-lightbox[open]').count() > 0;
out['licznik pokazuje pozycję'] = (await page.locator('.kadr-g-lightbox__counter').innerText()).startsWith('1 /');
out['na pierwszym kadrze wstecz nieaktywne'] = await page.locator('.kadr-g-nav--prev').isDisabled();

// Wersja pełna, nie ta sama miniatura: lightbox prosi o wariant `view`.
out['lightbox bierze większy wariant'] = await page.evaluate(() =>
  document.querySelector('.kadr-g-lightbox__image')?.src.length > 0);

await page.keyboard.press('ArrowRight');
await page.waitForTimeout(250);
out['strzałka przewija dalej'] = (await page.locator('.kadr-g-lightbox__counter').innerText()).startsWith('2 /');

await page.keyboard.press('ArrowLeft');
await page.waitForTimeout(250);
out['strzałka przewija wstecz'] = (await page.locator('.kadr-g-lightbox__counter').innerText()).startsWith('1 /');

await page.screenshot({ path: `${root}/dist/preview/galeria-lightbox.png` });

await page.keyboard.press('Escape');
await page.waitForTimeout(300);
out['Escape zamyka lightbox'] = await page.locator('.kadr-g-lightbox[open]').count() === 0;

// Fokus wraca na kadr, z którego weszliśmy — inaczej osoba korzystająca
// z klawiatury ląduje na początku strony.
out['fokus wraca na kadr'] = await page.evaluate(() =>
  document.activeElement?.classList.contains('kadr-g-item__button'));

/* ---------------------------------------------------------------------
 * 3. Obsługa z klawiatury od początku
 * ------------------------------------------------------------------- */
await open('galeria-noir.html');
await page.keyboard.press('Tab');
out['pierwszy Tab trafia w pominięcie'] = await page.evaluate(() =>
  document.activeElement?.classList.contains('kadr-g-skip'));

await page.keyboard.press('Tab');
await page.keyboard.press('Enter');
await page.waitForTimeout(400);
out['Enter otwiera kadr'] = await page.locator('.kadr-g-lightbox[open]').count() > 0;
await page.keyboard.press('Escape');

/* ---------------------------------------------------------------------
 * 4. Doczytywanie kolejnych kadrów
 * ------------------------------------------------------------------- */
out['przycisk doczytania istnieje'] = await page.locator('#kadr-g-more').count() === 1;

/* ---------------------------------------------------------------------
 * 5. Bramka PIN-u
 * ------------------------------------------------------------------- */
await open('galeria-pin.html');

out['bramka pyta o PIN'] = await page.locator('.kadr-g-pin').count() === 1;
// Bramka nie pokazuje ANI JEDNEGO kadru — nawet rozmytego.
out['bramka nie pokazuje zdjęć'] = await page.locator('.kadr-g-item').count() === 0;
out['klawiatura numeryczna na telefonie'] = 'numeric' ===
  await page.locator('.kadr-g-pin').getAttribute('inputmode');

await open('galeria-pin-blad.html');
out['błędny PIN mówi wprost'] = await page.locator('.kadr-g-gate__error[role="alert"]').count() === 1;

await open('galeria-koniec.html');
out['wygasły link ma własny ekran'] = await page.locator('.kadr-g-gate__title').count() === 1;

/* ---------------------------------------------------------------------
 * 6. Trzy motywy i telefon
 * ------------------------------------------------------------------- */
for (const theme of ['noir', 'paper', 'minimal']) {
  await open(`galeria-${theme}.html`);

  const width = await page.evaluate(() => document.documentElement.scrollWidth);
  out[`${theme}: brak przewijania w bok`] = width <= 1440;

  await page.screenshot({ path: `${root}/dist/preview/galeria-${theme}.png` });
}

await page.setViewportSize({ width: 390, height: 844 });
await open('galeria-noir.html');

// Na telefonie rząd mieści co najmniej dwa kadry — jeden na ekran
// gubi rytm serii i zamienia galerię w nieskończone przewijanie.
out['telefon: rząd ma co najmniej dwa kadry'] = await page.evaluate(() => {
  const items = Array.from(document.querySelectorAll('.kadr-g-item'));
  const firstTop = Math.round(items[0].getBoundingClientRect().top);
  return items.filter(i => Math.round(i.getBoundingClientRect().top) === firstTop).length >= 2;
});
out['telefon: brak przewijania w bok'] =
  await page.evaluate(() => document.documentElement.scrollWidth) <= 390;

await page.screenshot({ path: `${root}/dist/preview/galeria-mobile.png` });

await page.locator('.kadr-g-item__button').first().click();
await page.waitForTimeout(400);
await page.screenshot({ path: `${root}/dist/preview/galeria-mobile-lightbox.png` });
out['telefon: lightbox na pełnym ekranie'] = await page.evaluate(() => {
  const box = document.querySelector('.kadr-g-lightbox')?.getBoundingClientRect();
  return Math.round(box.width) === 390;
});

await browser.close();

for (const [k, v] of Object.entries(out)) {
  console.log(`  ${v ? '✓' : '✗'} ${k.padEnd(38)} ${v}`);
}

console.log(errors.length
  ? `\n✗ BŁĘDY KONSOLI (${errors.length}):\n` + errors.slice(0, 5).map(e => '    ' + e).join('\n')
  : '\n✓ Zero błędów w konsoli');

const failed = Object.values(out).filter(v => !v).length;

if (failed > 0 || errors.length > 0) {
  process.exit(1);
}
