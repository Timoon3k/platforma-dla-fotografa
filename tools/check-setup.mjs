/**
 * Weryfikacja ekranu pierwszego uruchomienia w prawdziwej przeglądarce.
 *
 * Ten ekran istnieje po to, żeby administrator wiedział, CO naprawić.
 * Testowanie samych reguł (tests/Setup) nie powie, czy instrukcja naprawy
 * w ogóle dotarła do HTML-a — a to jest jedyne, co się tu liczy.
 */
import { chromium } from 'playwright-core';

const root = new URL('..', import.meta.url).pathname.replace(/\/$/, '');
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
const page = await browser.newPage({ viewport: { width: 1100, height: 900 } });

const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });

const out = {};
const open = async (file) => {
  await page.goto(`file://${root}/dist/preview/${file}`);
  await page.waitForTimeout(150);
};

/* --- Zwykłe odnośniki: pułapka numer jeden ------------------------- */
await open('setup-blokada.html');

const rows = page.locator('.widefat').first().locator('tbody tr');

// Blokady na górze: administrator ma zobaczyć, co go zatrzymuje,
// bez czytania całej listy.
out['blokada jest pierwszym wierszem'] =
  (await rows.first().innerText()).includes('odnośniki');
out['mówi, że platforma zwraca 404'] =
  (await rows.first().innerText()).includes('404');
// Komunikat bez instrukcji naprawy zostawia administratora z problemem,
// którego nie umie rozwiązać.
out['podaje dokładną ścieżkę naprawy'] =
  (await rows.first().innerText()).includes('Ustawienia → Bezpośrednie odnośniki');

out['przycisk tworzy stronę główną'] =
  await page.getByRole('button', { name: 'Utwórz stronę główną' }).count() === 1;
// Formularz zmieniający stan MUSI mieć nonce (docs/SECURITY.md).
out['formularz ma zabezpieczenie nonce'] =
  await page.locator('form input[name="_wpnonce"]').count() === 1;
out['formularz idzie metodą POST'] =
  (await page.locator('form').getAttribute('method')).toLowerCase() === 'post';

// Trasy platformy nie są stronami WordPressa — administrator nie znajdzie
// ich w „Stronach" i bez tej listy nie wie, że istnieją.
const body = await page.locator('body').innerText();
out['wymienia adres panelu'] = body.includes('/app/');
out['wymienia adres logowania'] = body.includes('/logowanie');
out['wymienia adres galerii klientki'] = body.includes('/g/{link}');

await page.screenshot({ path: `${root}/dist/preview/setup-blokada.png`, fullPage: true });

/* --- Baza nieosiągalna -------------------------------------------- */
// Ekran pomocy ma działać WŁAŚNIE wtedy, gdy coś jest zepsute. Biała
// strona zamiast informacji o braku bazy to najgorsza możliwa odpowiedź.
out['brak bazy nie wywraca ekranu'] = body.includes('Brak połączenia z bazą danych');
out['przy braku bazy nie radzi restartu wtyczki'] =
  body.includes('wp-config.php');

/* --- Instalacja kompletna ------------------------------------------ */
await open('setup-gotowe.html');

out['kompletna instalacja mówi to wprost'] =
  (await page.locator('.notice-success').innerText()).includes('działa');
out['nie proponuje tworzenia drugiej strony głównej'] =
  await page.getByRole('button', { name: 'Utwórz stronę główną' }).count() === 0;
// Pierwsza tabela to przegląd; druga to adresy platformy.
out['wszystkie pozycje odhaczone'] =
  await page.locator('.widefat').first().locator('tbody tr').count() === 7;
out['w komplecie nie ma ani jednej blokady'] =
  ! (await page.locator('.widefat').first().innerText()).includes('✕');

await page.screenshot({ path: `${root}/dist/preview/setup-gotowe.png`, fullPage: true });

/* --- Świeża instalacja --------------------------------------------- */
await open('setup-swieza.html');

out['świeża instalacja prowadzi do strony głównej'] =
  await page.getByRole('button', { name: 'Utwórz stronę główną' }).count() === 1;

await browser.close();

for (const [k, v] of Object.entries(out)) {
  console.log(`  ${v ? '✓' : '✗'} ${k.padEnd(44)} ${v}`);
}

console.log(errors.length
  ? `\n✗ BŁĘDY KONSOLI (${errors.length}):\n` + errors.slice(0, 5).map(e => '    ' + e).join('\n')
  : '\n✓ Zero błędów w konsoli');

const failed = Object.values(out).filter(v => !v).length;

if (failed > 0 || errors.length > 0) {
  process.exit(1);
}
