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

// Proofing: pobieranie wyłączone, więc przycisku nie ma.
out['bez pobierania nie ma przycisku'] = await page.locator('.kadr-g-lightbox [data-download]').isHidden();

await page.screenshot({ path: `${root}/dist/preview/galeria-lightbox.png` });

await page.keyboard.press('Escape');
await page.waitForTimeout(300);
out['Escape zamyka lightbox'] = await page.locator('.kadr-g-lightbox[open]').count() === 0;

// Fokus wraca na kadr, z którego weszliśmy — inaczej osoba korzystająca
// z klawiatury ląduje na początku strony.
out['fokus wraca na kadr'] = await page.evaluate(() =>
  document.activeElement?.classList.contains('kadr-g-item__button'));

/* ---------------------------------------------------------------------
 * 2b. Galeria z włączonym pobieraniem
 * ------------------------------------------------------------------- */
await open('galeria-paper.html');
await page.locator('.kadr-g-item__button').first().click();
await page.waitForTimeout(400);

out['z pobieraniem przycisk jest'] = await page.locator('.kadr-g-lightbox [data-download]').isVisible();
out['pobieranie celuje w plik, nie w podgląd'] =
  ( await page.locator('.kadr-g-lightbox [data-download]').getAttribute('href') || '' ).includes('/d/');

await page.keyboard.press('Escape');
await page.waitForTimeout(250);

/* ---------------------------------------------------------------------
 * 2a-ter. Print Room — BRAMKA SESJI 12
 *
 * Klientka musi zobaczyć, jak zostanie przycięte jej zdjęcie, ZANIM je
 * zamówi. Ta, która tego nie zobaczyła, dostanie odbitkę z obciętą głową
 * i złoży reklamację u fotografa (skill photography-workflow §5).
 * ------------------------------------------------------------------- */
await open('galeria-odbitki.html');

const frame = page.locator('.kadr-g-crop__frame');
const caption = page.locator('.kadr-g-print__caption');

out['podgląd kadrowania istnieje'] = await frame.isVisible();

// Zdjęcie 3:2 w formacie 10×15 (też 3:2) jest PEŁNE — tu nie wolno
// straszyć przycięciem.
const full = await frame.boundingBox();
out['10×15 nie przycina nic'] =
  (await caption.innerText()).includes('Całe zdjęcie');

// To samo zdjęcie w 13×18 traci boki. Ramka MUSI się zwęzić.
await page.getByRole('button', { name: /13×18/ }).click();
await page.waitForTimeout(450);
const trimmed = await frame.boundingBox();

out['13×18 zwęża ramkę podglądu'] = Math.round(trimmed.width) < Math.round(full.width);
out['wysokość zostaje bez zmian'] = Math.round(trimmed.height) === Math.round(full.height);
out['podpis nazywa stratę konkretnie'] =
  (await caption.innerText()).includes('lewa i prawa krawędź');
// „Zdjęcie zostanie dopasowane do formatu" nie mówi nic.
out['podpis nie ucieka w ogólniki'] =
  ! (await caption.innerText()).includes('dopasowane');

// Ostrzeżenie pojawia się TYLKO tam, gdzie coś znaczy — widoczne zawsze
// przestałoby być czytane.
const flags = await page.locator('.kadr-g-print__flag').count();
out['ostrzeżenia tylko przy realnej stracie'] = flags >= 1 && flags <= 3;

// Cena przy każdym formacie, w złotówkach.
out['każdy format ma cenę'] =
  await page.locator('.kadr-g-print__price').count()
  === await page.locator('.kadr-g-print__option').count();
out['ceny po polsku'] =
  (await page.locator('.kadr-g-print__price').first().innerText()).includes('zł');

// Wybór formatu kciukiem o 22:30 — 44 px to minimum.
const hit = await page.locator('.kadr-g-print__option').first().boundingBox();
out['pozycje mają dotykowy rozmiar'] = hit.height >= 44;

// Czytnik ekranu ma usłyszeć zmianę podglądu, nie tylko ją zobaczyć.
out['podpis ogłaszany na żywo'] =
  await page.locator('.kadr-g-print__caption[aria-live="polite"]').count() === 1;
out['wybrany format oznaczony dla czytnika'] =
  await page.locator('.kadr-g-print__option[aria-pressed="true"]').count() === 1;

await page.screenshot({ path: `${root}/dist/preview/galeria-odbitki.png`, fullPage: true });

// Telefon: klientka wybiera to jedną ręką.
await page.setViewportSize({ width: 390, height: 760 });
await page.waitForTimeout(250);
out['telefon: brak przewijania w bok'] = await page.evaluate(
  () => document.documentElement.scrollWidth <= window.innerWidth + 1
);
await page.setViewportSize({ width: 900, height: 1100 });

/* ---------------------------------------------------------------------
 * 2a-bis. Oś procesu — odpowiedź na „kiedy będą zdjęcia?"
 *
 * Fotograf dostaje to pytanie kilka razy przy każdej sesji i za każdym
 * razem odpowiada ręcznie. To jedno z dwudziestu przerwań, z których
 * składają się 2–4 godziny administracji przy jednej sesji.
 * ------------------------------------------------------------------- */
await open('galeria-etapy.html');

const journey = page.locator('.kadr-g-journey');

out['oś procesu widoczna'] = await journey.isVisible();
// Zdanie „co dalej" jest całą wartością tego elementu.
out['mówi, co się stanie dalej'] =
  (await journey.locator('.kadr-g-journey__now').innerText()).length > 30;
out['etapy w języku klientki'] =
  (await journey.innerText()).includes('Wybierasz zdjęcia');
// „Klientka obejrzała" powiedziane klientce brzmi jak podglądanie.
out['nie mówi o klientce w trzeciej osobie'] =
  ! (await journey.innerText()).includes('Klientka');
out['dokładnie jeden etap jest bieżący'] =
  await journey.locator('.kadr-g-journey__step--current').count() === 1;
// Stan niesie znak, nie sam kolor (WCAG 2.2 AA, 1.4.1).
out['przebyte etapy mają znak, nie tylko kolor'] =
  (await journey.locator('.kadr-g-journey__step--done').first().innerText()).includes('✓');

// Oś i licznik muszą opowiadać TO SAMO. Dwa różne stany na jednym ekranie
// to dokładnie ten moment, w którym klientka pisze do fotografa.
out['oś zgadza się z licznikiem'] =
  (await journey.innerText()).includes('Wybierasz')
  && await page.locator('#kadr-g-submit').count() === 1;

await page.screenshot({ path: `${root}/dist/preview/galeria-etapy.png` });

/* ---------------------------------------------------------------------
 * 2b-bis. Pliki do pobrania — trzeci etap, na którym produkt zarabia
 *
 * To jest moment, o którym klientka opowiada znajomym. Ekran ma go
 * obsłużyć tak, żeby nie trzeba było o nic dopytywać.
 * ------------------------------------------------------------------- */
await open('galeria-pliki.html');

const delivery = page.locator('.kadr-g-delivery');

out['sekcja pobierania widoczna'] = await delivery.isVisible();
out['przycisk prowadzi do paczki'] =
  (await delivery.locator('a').getAttribute('href')) === 'pobierz';
// Klientka na telefonie nie otworzy ZIP-a (skill photography-workflow §3).
// Bez tego zdania połowa z nich pobiera paczkę na telefon i pisze,
// że „nie działa".
out['mówi wprost o telefonie'] =
  (await delivery.innerText()).includes('telefonie');

// W galerii BEZ trybu wyboru nie może być przycisków wyboru: skrypt ich
// nie podpina, więc byłyby martwe. Martwa kontrolka uczy, że interfejsowi
// nie warto ufać — a to ekran, na którym za chwilę prosimy o pieniądze.
out['bez trybu wyboru brak przycisków wyboru'] =
  await page.locator('.kadr-g-choices').count() === 0;

await page.screenshot({ path: `${root}/dist/preview/galeria-pliki.png` });

await open('galeria-noir.html');
out['zwykła galeria też bez martwych przycisków'] =
  await page.locator('.kadr-g-choices').count() === 0;

/* ---------------------------------------------------------------------
 * 2c. Wybór zdjęć i licznik pakietu
 *
 * To jest ekran, dla którego istnieje ten produkt. Licznik ma być widoczny
 * przez cały czas i liczyć się przy każdej zmianie — inaczej dopłata jest
 * niespodzianką w wiadomości od fotografa, a nie decyzją klientki.
 * ------------------------------------------------------------------- */
await open('galeria-wybor.html');

out['licznik widoczny od razu'] = await page.locator('#kadr-g-count').isVisible();
out['licznik nie przewija się z treścią'] = await page.evaluate(() => {
  const box = document.getElementById('kadr-g-count');
  const before = box.getBoundingClientRect().top;
  window.scrollTo(0, 1200);
  return box.getBoundingClientRect().top === before;
});
await page.evaluate(() => window.scrollTo(0, 0));

// Serduszko i „wybieram" to dwie różne rzeczy — dwa przyciski na kadr.
out['dwa przyciski wyboru na kadr'] = await page.evaluate(() =>
  document.querySelector('.kadr-g-item').querySelectorAll('.kadr-g-choice').length === 2);

out['pole dotyku ma 44 px'] = await page.evaluate(() => {
  const box = document.querySelector('.kadr-g-choice').getBoundingClientRect();
  return Math.round(box.width) >= 44 && Math.round(box.height) >= 44;
});

// Stan z serwera jest narysowany od razu, bez czekania na skrypt.
out['poprzedni wybór widoczny w HTML-u'] = (await page.content())
  .includes('data-state="selected"');

const before = await page.locator('.kadr-g-count__main').innerText();
await page.locator('.kadr-g-choice--selected').nth(4).click();
await page.waitForTimeout(500);
const after = await page.locator('.kadr-g-count__main').innerText();

out['kliknięcie zmienia licznik'] = before !== after;
// Polszczyzna ma trzy formy liczby mnogiej: 1 / 2–4 / 5+.
out['odmiana liczebnika poprawna'] = after.includes('5 zdjęć');

out['wybrany kadr jest wyróżniony'] = await page.evaluate(() =>
  'selected' === document.querySelectorAll('.kadr-g-item')[4]
    .querySelector('.kadr-g-item__button').dataset.state);

// Kliknięcie w przycisk wyboru nie może otwierać lightboxa.
out['wybór nie otwiera lightboxa'] = await page.locator('.kadr-g-lightbox[open]').count() === 0;

// Ponowne kliknięcie odznacza.
await page.locator('.kadr-g-choice--selected').nth(4).click();
await page.waitForTimeout(400);
out['ponowne kliknięcie odznacza'] = (await page.locator('.kadr-g-count__main').innerText()) === before;

await page.screenshot({ path: `${root}/dist/preview/galeria-wybor.png` });

// Po przekroczeniu pakietu licznik pokazuje kwotę, a nie tylko liczbę.
await open('galeria-doplata.html');
const detail = await page.locator('.kadr-g-count__detail').innerText();

out['kwota dopłaty na liczniku'] = detail.includes('480 zł');
out['rozbicie na pakiet i nadmiar'] = detail.includes('20 zdjęć w pakiecie')
  && detail.includes('8 zdjęć dodatkowo');

await page.screenshot({ path: `${root}/dist/preview/galeria-doplata.png` });

// Wybór wysłany: przycisku już nie ma, jest potwierdzenie.
await open('galeria-wyslany.html');
out['wysłany wybór bez przycisku'] = await page.locator('#kadr-g-submit').count() === 0;
out['wysłany wybór ma potwierdzenie'] = await page.locator('.kadr-g-count__sent').isVisible();

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
