# SESSION LOG — Kadr

---

# SESSION 1/6 — Product discovery, architektura, kierunek wizualny

**Data:** 2026-09-12 · **Branch:** `claude/premium-photography-saas-u8xl6y`
**Stan wejściowy:** puste repozytorium, zero commitów.
**Rezultat:** 24 pliki dokumentacji, skills i szkieletu. Zero kodu produkcyjnego,
zero zależności, zero tabel.

---

## A. Wizja produktu

> System operacyjny relacji z klientem dla fotografa — prowadzi całą współpracę od pierwszego
> zapytania do ponownej rezerwacji i po drodze sam sprzedaje to, czego fotograf normalnie
> nie zdąży sprzedać.

Produkt sprzedaje dwie rzeczy, w tej kolejności:

**1. Odzyskany czas.** Obsługa jednej sesji w typowym polskim studiu to dziś: ustalanie terminu
w DM na Instagramie → zadatek BLIK-iem → sesja → podglądy przez WeTransfer (link wygasa po 7 dniach,
klientka pisze po 10) → wybór zdjęć w formie wiadomości *„te z drugiego rzędu, to gdzie Zosia się
śmieje i to w niebieskiej sukience, ale bez tego trzeciego"* → ręczne odszukiwanie plików →
obróbka → Dysk Google → „a mogę dokupić jeszcze dwa?" → kolejny przelew → kolejny link.
**2–4 godziny administracji na sesję, rozłożone na 20 przerwań.**

**2. Przychód, który dziś wyparowuje.** Dopłata za nadmiarowe zdjęcia jest niewygodna dla obu
stron, więc fotograf zwykle „dorzuca gratis". Odbitki nie sprzedają się wcale, bo wymagają
osobnej rozmowy. Klientka, która za rok chce tę samą sesję, musi sobie o tym przypomnieć sama.

**Non-goals:** edytor zdjęć · kreator stron portfolio · system księgowy · marketplace ·
pośrednik finansowy · konkurent dla CRM klasy enterprise.

---

## B. Persony

### P1 — Ola, 31, fotografka rodzinna i newborn, Poznań *(persona podstawowa)*
8–14 sesji/mies., 1 200–2 500 zł za sesję, jednoosobowa DG. Instagram DM, Dysk Google, WeTransfer.
**Bóle:** wybór zdjęć w wiadomościach · „kiedy będą zdjęcia?" · rozmowa o dopłacie · wygasające linki.
**Przekonuje ją:** galeria wyglądająca jak jej Instagram, pierwsza galeria gotowa w 15 minut.
**Odstraszy ją:** karta przed zobaczeniem wartości, angielskie UI, cokolwiek wyglądającego jak WordPress.
**Ryzyko churnu:** martwy sezon styczeń–luty → konieczna pauza konta.

### P2 — Marek, 38, fotograf ślubny premium, Warszawa
22–28 wesel/rok, 9 000–18 000 zł, 800–1 500 zdjęć na galerię, 30–80 GB materiału.
Marka jest wszystkim — galeria nie może wyglądać jak cudzy produkt. Dostawa zdjęć to jego
główny kanał poleceń. **Najlepszy i jednocześnie najdroższy klient** — jego cena musi
uwzględniać koszt storage'u.

### P3 — Studio Lumen, 3–5 osób, Kraków *(plan Pro)*
~40 sesji/mies. Potrzebuje: seatów, granularnych uprawnień, tablicy produkcji, wspólnego kalendarza.
Asystentka ma odpowiadać klientom, ale nie widzieć rozliczeń. Retuszerka zewnętrzna dostaje dziś
pełny dostęp do wszystkiego, bo inaczej się nie da. **Najwyższy LTV, najniższy churn.**

### P4 — Kasia, 34, klientka *(persona krytyczna)*
Telefon, 21:30–23:00, jedną ręką, dziecko śpi obok, czasem słaby zasięg.
Oczekiwania ukształtowane przez Allegro i Netflixa, nie przez software fotograficzny.
**Nie zrobi:** konta z hasłem · instalacji aplikacji · otwierania ZIP-a na telefonie · czytania instrukcji.
**Zrobi:** kliknie link · poda PIN · przesunie palcem · kliknie serduszko · zapłaci BLIK-iem w 20 s.
**Dlaczego krytyczna:** to ona mówi innym mamom „skąd masz taką ładną galerię?".
**To jest nasz kanał akwizycji.**

### P5 — Administrator platformy
MRR, konwersja free → paid, churn, **koszt storage per tenant zestawiony z przychodem per tenant**,
kolejka, nieudane webhooki, nadużycia. Cel: infrastruktura ≤ 20% MRR.
Wsparcie techniczne wymaga wglądu w dane → impersonacja z pełnym audit logiem i zgodą.

---

## C. Customer journey

| # | Etap | Klient widzi | System robi sam | Dziś pęka |
|---|---|---|---|---|
| 1 | Zapytanie | formularz / strona rezerwacji | kontakt w CRM, źródło | leady giną w DM |
| 2 | Rezerwacja | wybór terminu, zadatek | blokada terminu, zamówienie, potwierdzenie | ręczne pilnowanie zadatków |
| 3 | Przed sesją | plan sesji: co zabrać, gdzie parking | automat T-7 i T-1 | telefony i no-show |
| 4 | Sesja | — | status *Sesja zrealizowana* | — |
| 5 | Galeria proofingowa | link + PIN, galeria mobilna | pipeline miniatur, powiadomienie, termin ważności | WeTransfer wygasa |
| 6 | **Wybór** ⭐ | serduszka, wybór, licznik pakietu | liczy nadmiar i kwotę dopłaty | **największy ból** |
| 7 | **Dopłata** ⭐ | „20 w pakiecie, 8 dodatkowych = X zł" → BLIK | zamówienie, płatność | **tu wyparowuje przychód** |
| 8 | Obróbka | „W obróbce — gotowe do 20.03" | SLA, przypomnienia | „kiedy będą zdjęcia?" × 5 |
| 9 | **Dostawa** ⭐ | Odsłona, potem pobieranie | tokeny, limity, wygaśnięcie | ZIP na telefonie |
| 10 | **Odbitki** ⭐ | Print Room w galerii | rekomendacje, progi wysyłki | **nie sprzedaje się wcale** |
| 11 | Powrót | „Rok temu robiliśmy sesję…" | automat rocznicowy | zależne od pamięci klienta |

**Etapy 6, 7, 9 i 10 to cała wartość ekonomiczna produktu.** Reszta to higiena.

---

## D. Model biznesowy

**Free:** 5 pełnych projektów, bezterminowo, 5 GB, bez karty, **ze sprzedażą dodatkowych zdjęć**.
Po wyczerpaniu: read-only, galerie klientów działają dalej, dane min. 12 miesięcy.

**Starter 69 zł · Studio 149 zł · Pro 299 zł** (rocznie −17%). Dodatki: storage, seat, domena,
SMS, motywy premium, reaktywacja galerii. Pełna tabela: `docs/BILLING.md`.

**Dlaczego free tier na projektach, nie na czasie.** Cykl „sesja → galeria → wybór → dopłata →
dostawa" trwa 2–6 tygodni. Trial 14-dniowy strukturalnie uniemożliwia zobaczenie wartości.

**Ekonomia.** Storage to jedyny istotnie zmienny koszt. Przy planie Pro (1 TB) koszt surowy
to ~3–10% ceny planu — **pod warunkiem** twardego egzekwowania limitów i archiwizacji oryginałów
po wygaśnięciu galerii. Bez tych dwóch mechanizmów marża na fotografach ślubnych znika w 18 miesięcy.
Próg samodzielności: ~200 płacących × śr. 140 zł ≈ 28 000 zł MRR.

---

## E. Wyróżniki — 12 pomysłów, ocena, wybór

| # | Pomysł | Wartość | Koszt | Przychód | Wyróżnik | Retencja | Σ |
|---|---|---|---|---|---|---|---|
| 1 | **Client Journey** | 5 | 2 | 3 | 5 | 5 | **21** |
| 2 | **Selection Room** | 5 | 3 | 5 | 4 | 4 | **20** |
| 3 | **Odsłona** | 4 | 2 | 3 | 5 | 5 | **19** |
| 4 | Consent & Usage Vault | 4 | 2 | 2 | 5 | 4 | 17 |
| 5 | Print Room z podglądem kadru | 4 | 4 | 5 | 3 | 3 | 15 |
| 6 | Return Client | 3 | 2 | 4 | 3 | 5 | 15 |
| 7 | Smart Expiry & Archive | 3 | 2 | 3 | 3 | 4 | 15 |
| 12 | Brand Skin | 4 | 3 | 4 | 3 | 4 | 15 |
| 8 | Studio Board | 4 | 3 | 2 | 3 | 4 | 13 |
| 10 | Session Blueprint | 3 | 2 | 1 | 3 | 3 | 12 |
| 9 | Revenue Assistant | 4 | 5 | 5 | 4 | 3 | 11 |
| 11 | Wybór odporny na słabą sieć | 3 | 4 | 1 | 3 | 2 | 8 |

### TOP 3: ① Client Journey · ② Selection Room · ③ Odsłona

**Uzasadnienie ponad punktację.** Te trzy są **jedną opowieścią**, nie trzema funkcjami: klient wie,
gdzie jest (①), wybiera i płaci bez tarcia (②), a odbiór zdjęć jest wydarzeniem (③).
Są nieskopiowalne punktowo — narzędzie „tylko do galerii" nie zbuduje osi procesu, bo nie zna
początku ani końca. ② robi fotografowi pieniądze bezpośrednio. ② i ③ widzi klient końcowy,
czyli przyszły lead innego fotografa — to jedyny mechanizm wzrostu, który nic nie kosztuje.

**#4 (Consent & Usage Vault)** — pierwsza przewaga po MVP. Tania, specyficznie europejska,
konkurencja zza oceanu strukturalnie jej nie ma.

**#9 (Revenue Assistant)** — świadomie odłożony. Wymaga danych historycznych, których w dniu
premiery nie mamy. Zgadujący asystent niszczy zaufanie do całego produktu.
Wracamy przy ~500 zamkniętych wyborach w bazie.

---

## F. Architektura — rozważone warianty

**A — klasyczny WordPress (CPT + postmeta).** Odrzucony: ~216 mln wierszy w `wp_postmeta`,
zerowa izolacja tenantów, UI uwiązane do WP Admin, niemożliwe budżety CWV.

**B — WP jako platforma, wtyczka jako aplikacja.** ✅ **Wybrany** (ADR-002).

**C — headless.** Odrzucony: łamie założenie wtyczki WP, dwa stacki, ~2× koszt utrzymania,
fotograf nie wgra tego na swój hosting.

### Trzy decyzje fundamentalne
- **F-1 Klient poza `wp_users`** (ADR-003). Argument rozstrzygający: `wp_users.user_email` jest
  unikalny globalnie, a ta sama osoba może być klientką dwóch fotografów. **Multi-tenancy wyklucza
  wariant z `wp_users`.** Mitygacja: nie piszemy własnej kryptografii — `wp_hash_password()`,
  sesje w bazie (unieważnialne), domyślnie magic link.
- **F-2 Kolejka: Action Scheduler za `QueueInterface`** (ADR-004).
- **F-3 Środowisko:** PHP 8.2+ · WP 6.5+ · MySQL 8.0 · Imagick · VPS.

---

## G. Model danych

37 tabel w siedmiu domenach. Pełna specyfikacja, ERD i indeksy: `docs/DATABASE.md`.

**Argument za custom tables:** 1000 fotografów × 60 galerii × 300 zdjęć = **18 mln wierszy**
w `gallery_assets`; w modelu CPT to ~216 mln wierszy w tabeli EAV i 6 JOIN-ów na zapytanie.

**Indeks rozstrzygający dla poprawności:** `payment_events (provider, external_event_id) UNIQUE` —
jedyny mechanizm gwarantujący, że powtórzony webhook nie zrealizuje zamówienia dwa razy.
Wymuszenie na poziomie bazy, bo kod da się ominąć wyścigiem.

---

## H. Storage

`StorageProviderInterface` + LocalStorage + S3 (region **EU**). Sześć wariantów na zdjęcie
(thumb/grid/view × AVIF/WebP), nie dwadzieścia. Cykl życia: aktywna → zarchiwizowana
(oryginały do cold storage, reaktywacja płatna) → do usunięcia, nigdy po cichu.
Deduplikacja po SHA-256 w obrębie tenanta. Szczegóły: ADR-011, `docs/PERFORMANCE.md`.

Archiwizacja jest jednocześnie mechanizmem oszczędności i mikro-upsellem — rzadki przypadek,
w którym obniżenie kosztu i podniesienie przychodu to ten sam mechanizm.

---

## I. Płatności

**Dwie rozłączne domeny, bez wspólnego kodu i wspólnych tabel:**
```
fotograf → platforma    abonament, dodatki          moduł Billing\    (Stripe Billing)
klient   → fotograf     zadatek, dopłaty, odbitki   moduł Commerce\   (operator fotografa)
```
Platforma **nigdy nie jest stroną** transakcji klient → fotograf i nie przyjmuje tych środków (ADR-006).
Pierwszy adapter musi mieć **BLIK** — bez tego konwersja w kroku 7 journeya się załamuje.

Marketplace / split payments = **STOP** i osobny dokument architektoniczno-prawny.

---

## J. Kierunki wizualne — trzy propozycje

| | Teza | Paleta | Typografia | Dla kogo |
|---|---|---|---|---|
| **Darkroom** | zdjęcia świecą w ciemności | węgiel `#0B0B0C` + bursztyn ciemni | Instrument Serif + Switzer | ślub, fine-art |
| **Atelier** | drogi album fotograficzny; spokój jest luksusem | kość słoniowa `#F4F1EA` + terakota | Fraunces + General Sans | rodzinna, newborn, ślubna — **95% rynku PL** |
| **Studio OS** | panujesz nad chaosem: liczby, siatka, dane | `#FAFAFA`/`#0E0E10` + zieleń sygnałowa | Bricolage + Geist | studia, komercyjna |

**Decyzja (ADR-009):** Atelier na zewnątrz, Studio OS w środku, a Darkroom i Minimal
stają się **motywami galerii** — czyli entitlementem planu. Żaden kierunek się nie marnuje.

**Dark mode (ADR-010):** nie w v1.0. Globalny dark mode podwaja powierzchnię QA i testów
kontrastu, a ciemny motyw ma realną wartość dokładnie w jednym miejscu — w galerii — i tam go dajemy.

---

## K. Architektura informacji

Publiczna: `/`, `/funkcje/*`, `/cennik`, `/bezpieczenstwo`, `/dla-studiow`, `/dokumenty/*`.
Fotograf: `/app` → Dzisiaj · Klienci · Galerie · Zamówienia · Produkty · Kalendarz · Wiadomości ·
Automatyzacje · Analityka · Ustawienia · Rozliczenia.
Klient: `/k` → Sesje · Galerie · Wybory · Zamówienia · Pliki · Terminy · Dane i zgody.
Gość: `/g/{slug}-{token}` · Rezerwacje: `/b/{studio}` · Admin: WP Admin › Platforma.

**Zmiana wobec briefu §62:** problem idzie przed rozwiązaniem, a dowód społeczny nisko —
bo go jeszcze nie mamy i nie będziemy go udawać. Pełna kolejność: `docs/DESIGN-SYSTEM.md` §8.

---

## L/M. Zakres MVP i post-MVP

Pełne listy: `docs/ROADMAP.md`.
**Kryterium wejścia do v1.0:** funkcja obsługuje etap 6, 7, 9 lub 10 journeya, albo jest wymagana
prawnie, albo bez niej produktu nie da się sprzedać. Nic więcej.

---

## N. Rejestr ryzyk — pięć najważniejszych

| # | Ryzyko | Mitygacja |
|---|---|---|
| R1 | koszt storage zjada marżę | twarde limity · liczniki przyrostowe · archiwizacja · koszt per tenant w panelu |
| R2 | wyciek między tenantami | `TenantContext` w repozytorium · testy izolacji jako bramka CI |
| R3 | wyciek zdjęć przez bezpośredni URL | zero publicznych ścieżek · podpisane URL-e · tokeny z hashem · ULID |
| R6 | fotograf nie kończy onboardingu | checklista zamiast formularza · demo-galeria · wsparcie przy 3. dniu bez galerii |
| R13 | rozrost zakresu | ten dokument · jawna lista „nie wchodzi" · bramki wyjścia z sesji |

Pełny rejestr 15 pozycji z prawdopodobieństwem i wpływem: sekcja N propozycji Session 1
oraz `docs/SECURITY.md` (ryzyka techniczne).

---

## O. Nazwa produktu

Rozważono 10 kierunków: **Kadr**, Stykówka, Odsłona, Passepartu, Klisza, Aperta, Atelia,
Wywołane, Proofroom, Lumea.

**Wybrano: Kadr** (ADR-001). Cztery litery, twarde spółgłoski, dobrze znosi duży stopień pisma,
wprost fotograficzne bez bycia opisowym, działa fonetycznie poza Polską.
Alternatywa global-first: **Aperta**.

⚠️ **Nie zweryfikowano** domeny ani znaków towarowych. Warunek zawieszający — kwestia O1
w `PROJECT_STATE.md`. Zmiana nazwy jest tania do Session 3, potem kosztowna.

---

## Decyzje podjęte w tej sesji

12 ADR-ów: `docs/DECISIONS.md`. Właściciel produktu delegował zatwierdzenie checklisty
(„zostawiam checklistę tobie, najwyżej później wprowadzimy poprawki"), więc decyzje z Bloku 4
propozycji zostały podjęte zgodnie z rekomendacjami i zapisane jako ADR-y —
każdą da się cofnąć jedną poprawką.

## Pliki utworzone

```
CLAUDE.md · PROJECT_STATE.md · README.md · .gitignore · .editorconfig
docs/ARCHITECTURE.md · DECISIONS.md · ROADMAP.md · DATABASE.md · SECURITY.md
docs/PERFORMANCE.md · DESIGN-SYSTEM.md · API.md · BILLING.md · LEGAL.md · SESSION-LOG.md
docs/adr/TEMPLATE.md
.claude/skills/{product-strategy,premium-ui-design,wordpress-saas,gutenberg,
               photography-workflow,performance,security-privacy,qa-accessibility}/SKILL.md
src/{Domain,Application,Infrastructure,Presentation}/ + src/README.md
```

**Nie utworzono** (świadomie, odłożone do Session 2): `composer.json`, `package.json`,
`kadr.php`, jakikolwiek kod PHP/JS/CSS, migracje, tabele, testy.

## Testy

Brak — sesja w całości dokumentacyjna, nie powstał żaden kod wykonywalny.

## Wpływ

| | |
|---|---|
| Baza danych | brak — schemat zaprojektowany, nie zaimplementowany |
| Wydajność | brak — zero kodu w runtimie; budżety zapisane jako zobowiązanie |
| Bezpieczeństwo | brak zmian w powierzchni ataku; powstał model zagrożeń wiążący kolejne sesje |

## Następny krok

**Session 2/6 — design system, strona marketingowa, bloki Gutenberga.**
Pierwsze zadanie: propozycja `composer.json` i `package.json` z uzasadnieniem każdej pozycji.
Szczegóły i kolejność: `PROJECT_STATE.md` → „Następny logiczny krok".

---

# SESSION 2/6 — Design system, strona marketingowa, Gutenberg

**Data:** 2026-09-12 · **Wersja:** 0.1.0-discovery → **0.2.0**
**Stan wejściowy:** sama dokumentacja, zero kodu.
**Rezultat:** działająca wtyczka WordPress — instalowalna i używalna zaraz po rozpakowaniu,
bez `composer install` i bez `npm run build`.

---

## Dwie decyzje, które odbiegły od planu Session 1

### ADR-013 — brak kroku budowania w części publicznej
Plan zakładał Vite. Środowisko ma npm i node 22, więc `@wordpress/scripts` był realną opcją.
Wybrałem jednak brak bundlera, bo checkpointy sesji są dostarczane jako archiwum, a artefakt
wymagający `npm install` nie jest wtyczką, tylko jej kodem źródłowym.

Warunek wykonalności: WordPress 6.5+ dostarcza mapę importów dla `@wordpress/interactivity`,
więc `import { store } from '@wordpress/interactivity'` działa bez bundlera. Nie tracimy
nowoczesnego API, tracimy krok kompilacji.

Warstwa edytora powstaje z jednej deklaratywnej specyfikacji (`assets/js/editor.js`, 454 linie
na dziewięć bloków) zamiast dziewięciu plików JSX. Wyszło **mniej** kodu, nie więcej.

**Decyzja celowo nie obejmuje dashboardu `/app`** — interfejs z tabelami, filtrami i stanem to
inna klasa problemu i wraca jako osobne pytanie na starcie Session 3.

### ADR-014 — testy warstwy Domain bez frameworka
`composer install` nie działa w tym środowisku: proxy blokuje uwierzytelnianie do github.com
(sprawdzone, nie założone). Zamiast pisać kod bez możliwości uruchomienia testów, warstwa Domain
dostała mikro-runner: ~150 linii, tylko używane asercje, działa wszędzie gdzie jest PHP.

Zwrot pojawił się natychmiast — patrz niżej.

---

## Błąd wykryty i naprawiony w trakcie

Pierwszy przebieg testów pokazał, że plan **Pro raportuje limit 0 galerii** zamiast „bez limitu”.

Przyczyna: `Plan::limit()` używało `$this->entitlements[ $key ] ?? 0`, a operator `??` reaguje
na `null` — czyli dokładnie na wartość, którą w rejestrze planów zapisujemy jako „bez limitu”.
Najdroższy plan blokowałby tworzenie galerii.

Naprawa: rozróżnienie „klucz nieobecny” (bezpieczna odmowa, limit 0) od „klucz ustawiony na null”
(brak limitu), przez `array_key_exists()`. Zabezpieczone testem regresyjnym
`PlanTest::testNullMeansUnlimitedNotZero`.

To jest argument za ADR-014 mocniejszy niż cokolwiek, co mógłbym napisać w uzasadnieniu.

---

## Co powstało

**Bootstrap i infrastruktura WordPressa**
`kadr.php` (80 linii, limit 100) · `Requirements` (twarde wymagania + ostrzeżenia miękkie
o braku Imagicka) · `Activation` · `Plugin` · `Paths` · `Assets` · `Blocks` · `Patterns` ·
`ContentTypes` · `Consent` · `uninstall.php` (domyślnie nie usuwa danych).

**Warstwa domenowa rozliczeń** — pierwszy kod, który nie zna WordPressa:
`Money` (grosze jako int, nigdy float) · `Limit` · `Plan` · `PlanRegistry` (jedyne źródło cen
i entitlementów) · `Entitlements` (jedyny dopuszczalny sposób sprawdzania uprawnień).

**Design system**
`tokens.css` — tokeny semantyczne + trzy motywy galerii (Paper, Noir, Minimal) jako nadpisanie
samych zmiennych · `base.css` · `components.css` · `marketing.css`.

**Dziewięć bloków Gutenberga**, wszystkie renderowane po stronie serwera:
`hero` · `problem` · `journey` · `feature` · `proof` · `pricing` · `faq` · `testimonials` · `cta`.

Dwa warte wyróżnienia:
- **`pricing`** czyta `PlanRegistry`. Administrator nie może tu nadpisać ceny ani limitu —
  cena nie istnieje w dwóch miejscach, więc cennik nie może rozejść się z produktem.
  Przełącznik miesięcznie/rocznie na Interactivity API; bez JS strona pokazuje ceny miesięczne,
  co jest poprawnym stanem domyślnym, a nie awarią.
- **`proof`** liczy dopłatę przez `Money` z warstwy domenowej, a nie w szablonie —
  strona marketingowa nie może pokazać arytmetyki innej niż produkt.

**Zgody na cookies** — własny moduł, 1,9 KB gzip. Skrypty wymagające zgody leżą jako
`<script type="text/plain">` i nie mają fizycznej możliwości wykonania się przed zgodą.
Odmowa ma tę samą wagę wizualną co zgoda. Esc traktowany jako odmowa. Zgoda wersjonowana.

**Narzędzia**
`tools/package.sh` (checkpoint RAR) · `tools/run-tests.php` + `TestCase.php` ·
`tools/check-blocks.php`.

---

## Testy i weryfikacja

| Sprawdzenie | Wynik |
|---|---|
| `php tools/run-tests.php` | **20/20 zdanych** |
| `php tools/check-blocks.php` | **9/9 bloków spójnych** |
| Test negatywny walidatora (usunięty atrybut `note`) | wykrył oba użycia, kod wyjścia 1 |
| `php -l` na wszystkich plikach PHP | bez błędów |
| `node --check` na wszystkich plikach JS | bez błędów |
| Poprawność JSON we wszystkich `block.json` | 9/9 |

**Czego NIE zweryfikowano:** faktycznego renderowania w WordPressie. W tym środowisku nie ma
uruchomionej instalacji WP ani bazy danych. Walidator bloków pokrywa najczęstszą przyczynę
cichych awarii (rozjazd atrybutów między `block.json`, `render.php` i `editor.js`),
ale nie zastępuje uruchomienia. Pełny audyt to pierwsze zadanie Session 3.

## Wydajność

| Zasób | Zmierzone (gzip) | Budżet |
|---|---|---|
| CSS landingu | **6,1 KB** | 25 KB |
| JS landingu | **2,1 KB** | 30 KB |
| Zależności produkcyjne | **0** | — |

Style bloków są rejestrowane, ale ładowane wyłącznie gdy blok jest na stronie —
nie ma globalnego pakietu na każdej podstronie.

## Bezpieczeństwo

- Escaping w każdym szablonie; `Render::rich()` przepuszcza treść administratora przez
  `wp_kses` z zawężoną listą tagów.
- Poprawiono podwójne zabezpieczanie: `get_block_wrapper_attributes()` i
  `wp_interactivity_data_wp_context()` zwracają dane zabezpieczone przez rdzeń, a przepuszczanie
  ich przez `wp_kses_data()` zniekształca cudzysłowy w atrybutach. Wypisywane bezpośrednio,
  z komentarzem i wyciszeniem reguły PHPCS wraz z uzasadnieniem.
- Odczyt ciasteczka zgód przez `sanitize_text_field( wp_unslash() )`, z walidacją wersji
  i odfiltrowaniem nieznanych kategorii.
- `uninstall.php` nie usuwa niczego bez jawnej flagi ustawionej świadomie przez administratora.

## Czego brakuje do zamknięcia Session 2

| Pozycja | Powód |
|---|---|
| Pliki fontów Fraunces i General Sans | czeka na potwierdzenie licencji (kwestia O4). Do tego czasu działają stosy zastępcze |
| Szkice Regulaminu i Polityki prywatności | objętość; polityka cookies jest gotowa, bo moduł zgód już działa i musi mieć do czego linkować |
| Cztery podstrony funkcji jako wzorce | bloki istnieją, brakuje gotowych układów |
| Audyt Lighthouse | wymaga działającej instalacji WordPressa |
| Formularz rejestracji fotografa | **przeniesiony do Session 3** — nie da się sensownie zarejestrować fotografa, zanim istnieje tabela `tenants`. Roadmapa umieszczała to w Session 2 przez przeoczenie kolejności zależności |

## Następny krok

**Session 3/6 — rdzeń SaaS.** Szczegóły w `PROJECT_STATE.md`.

---

# SESSION 3/15 — Warstwa danych, izolacja tenantów, kierunek Obsidian

**Data:** 2026-09-12 · **Wersja:** 0.2.0 → **0.3.0**
**Zmiana planu:** roadmapa rozszerzona z 6 do 15 sesji (decyzja właściciela produktu).
Pięć sesji (6–10) poświęconych wyłącznie frontendowi aplikacji.

---

## Zmiana kierunku wizualnego w trakcie sesji

Właściciel produktu odrzucił Atelier: *„styl musi mieć animacje i wygląd najlepszej
platformy premium, a nie papieru i terakoty”*. Przedstawiłem trzy kierunki (Obsidian,
Kinetic, Spectrum) i trzy poziomy intensywności ruchu. Wybór: **Obsidian + wyrazisty ruch
bez biblioteki**. Zapisane jako ADR-015, jawnie zastępujący ADR-009 i ADR-010.

**Koszt zmiany okazał się niski i to nie przypadek.** `base.css` i `components.css` miały
łącznie jedną zakodowaną wartość koloru — reszta była tokenowa. Zmiana sprowadziła się do
podmiany wartości tokenów i dołożenia warstwy ruchu. Markup dziewięciu bloków, warstwa
domenowa, cennik, zgody i testy pozostały nietknięte. Decyzja z sesji 1 o semantycznych
tokenach zwróciła się dokładnie w tym momencie.

Ruch: scroll reveals, animowany licznik dopłaty, podświetlenie podążające za kursorem —
wszystko na `IntersectionObserver`, Web Animations i zmiennych CSS. **2,0 KB gzip**
zamiast ~70 KB, które kosztowałby GSAP.

---

## Trzy błędy wykryte przez narzędzia, zanim cokolwiek wyszło

### 1. Biel na przycisku głównym: 3,72:1

Napisałem `tools/check-contrast.php`, czytający paletę wprost z `tokens.css`.
Wykrył, że tekst przycisku głównego — **najważniejszego elementu strony** — nie zdaje
WCAG AA. Przy okazji: etykiety 4,35:1 i obrys pola formularza 1,52:1.

Naprawa nie polegała na dobraniu koloru „na oko”, tylko na policzeniu: `--kadr-accent`
(`#4D7CFF`) zostaje do tekstu i obrysów, gdzie ma 5,38:1, a wypełnienia dostały
`--kadr-accent-solid` (`#3463E6`), na którym biel daje 5,16:1. Doszedł osobny
`--kadr-line-control` dla obrysów kontrolek, bo WCAG 1.4.11 wymaga tam 3:1,
a obrysu dekoracyjnego nie wymaga. **18/18 par zdaje AA.**

### 2. Klucze unikalne bez `tenant_id` — trzy tabele

Pierwszy przebieg testów izolacji wywalił się na naruszeniu klucza unikalnego:
`UNIQUE(selection_id, asset_id)` bez `tenant_id` sprawia, że **wpis jednego fotografa
blokuje zapis drugiemu**. To jednocześnie awaria i wyciek informacji o istnieniu cudzych
danych. Audyt pokazał trzy takie klucze: `selection_items`, `selections`, `asset_variants`.

Zamiast poprawić trzy miejsca i liczyć na pamięć, powstał `tests/Domain/SchemaTest.php`,
który pilnuje reguły dla całego schematu. Natychmiast wykrył czwarty problem:
indeks `audit_log (entity_type, entity_id)` bez tenanta, bezużyteczny w zapytaniach
wielotenantowych.

### 3. Fałszywy alarm we własnym skrypcie pakującym

Weryfikacja pakietu ZIP zgłaszała brak `uninstall.php`, mimo że plik był w archiwum.
Przyczyna: `set -o pipefail` w połączeniu z `grep -q`, który kończy się po pierwszym
trafieniu — `unzip` dostaje SIGPIPE i potok zwraca niezero. Przy okazji poprawiłem
wzorzec `[ -e x ] && cp`, który pod `set -e` po cichu ubija skrypt w połowie kopiowania.

---

## Co powstało

**Warstwa domenowa** (nie zna WordPressa, w całości testowalna):
`Ulid` · `Clock` / `SystemClock` / `FrozenClock` · `Result` · `TenantId` · `TenantContext` ·
`Capability` · `Role` · `SelectionState` · `PackageTally`.

**Schemat deklaratywny.** `Table` opisuje tabelę raz, a dwie gramatyki generują z niej DDL:
MySQL dla produkcji i SQLite dla testów. Dzięki temu testy izolacji wykonują **prawdziwe
zapytania SQL na dokładnie tych samych kolumnach co produkcja** — atrapa `$wpdb`
dowiodłaby tylko tego, że atrapa działa.

**`TenantRepository`** — warstwa, na której stoi izolacja danych:
- klasa nie wystawia metody przyjmującej surowy SQL; nie ma `query()`, `findAny()` ani flagi `$ignoreTenant`,
- wszystkie metody dostępowe są `final`,
- `tenant_id` jest doklejany do każdego WHERE i nadpisywany przy każdym zapisie,
- nazwy kolumn są sprawdzane względem deklaracji tabeli — klucz spoza schematu kończy się
  wyjątkiem, a nie zapytaniem (identyfikatorów nie da się parametryzować).

**Migracje** z wersją schematu, uruchamiane wyłącznie przy aktywacji i aktualizacji wtyczki.

**Pięć repozytoriów:** klienci, galerie, zdjęcia, wybory, pozycje wyboru.

---

## Testy

| Zestaw | Liczba | Wynik |
|---|---|---|
| Izolacja tenantów (prawdziwy SQL) | 13 | ✅ |
| Arytmetyka dopłaty | 11 | ✅ |
| Reguły schematu | 6 | ✅ |
| Tenancy i uprawnienia | 7 | ✅ |
| ULID | 5 | ✅ |
| Plany i entitlementy | 20 | ✅ |
| **Razem** | **62** | **✅** |

Pozostałe bramki: 9/9 bloków spójnych · 18/18 par kontrastu · 48 plików zgodnych z PSR-4.

### Co testuje zestaw izolacji

Tenant B, znając identyfikator zasobu tenanta A, próbuje: odczytać klienta po ULID-zie
i po adresie e-mail, wylistować dane, zmodyfikować galerię, usunąć ją, opublikować,
policzyć cudze zdjęcia, znaleźć duplikat po hashu, podmienić cudzy wybór. Każda próba
kończy się niczym. Osobno sprawdzane: próba nadpisania `tenant_id` przez dane wejściowe,
próba wstrzyknięcia przez nazwę kolumny, oraz to, że ta sama osoba może być klientką
dwóch fotografów (argument rozstrzygający z ADR-003).

## Czego NIE zweryfikowano

**Działania w prawdziwym WordPressie.** To środowisko nie ma dostępu do wordpress.org
(proxy zwraca 403) ani serwera MySQL. Zastępczo działa `tools/preview.php`, renderujący
bloki poza WordPressem, oraz testy na SQLite. To nie zastępuje instalacji — rejestracja
bloków, edytor, Interactivity API i zapytania na MySQL pozostają niesprawdzone.

## Wpływ

| | |
|---|---|
| Baza | **14 nowych tabel.** Powstają przy aktywacji, nigdy przy zwykłym żądaniu |
| Wydajność | CSS 9,2 KB, JS 3,8 KB gzip (limity 25 i 30 KB) |
| Bezpieczeństwo | izolacja tenantów wymuszona konstrukcyjnie, 13 testów; kolumny z listy dozwolonych; miękkie usuwanie z koszem |

## Następny krok

**Sesja 4/15 — storage, pipeline obrazów, kolejka.** Szczegóły w `PROJECT_STATE.md`.

---

# SESJA 4/15 — Magazyn plików, pipeline obrazów, kolejka zadań

**Data:** 2026-09-12 · **Wersja:** 0.3.0 → **0.4.0**
**Testy:** 62 → **129**

---

## Dwie decyzje wymuszone przez środowisko — i to, co z nich wyszło

### ADR-016 — własna kolejka zamiast Action Scheduler

ADR-004 wybrał Action Scheduler, argumentując, że „pisanie kolejki od zera to koszt bez
zwrotu”. Przy wdrożeniu okazało się, że w tym środowisku nie da się pobrać żadnego pakietu:
`composer install` nie uwierzytelnia się do github.com. Zamiast zablokować prace, oceniłem
wariant ponownie — i okazało się, że pierwotne uzasadnienie już nie obowiązuje.

Mieliśmy z sesji 3 deklaratywny schemat, migracje, kontrakt bazy i testy na prawdziwym
silniku SQL. Koszt wyniósł ~250 linii i dzień, a nie dwa tygodnie, jak szacował ADR-004.
Do tego doszła rzecz, której Action Scheduler nie ma, a która jest u nas wymaganiem
produktowym: **limit współbieżności per tenant**. Jeden fotograf wysyłający wesele nie może
zagłodzić kolejki pozostałych — w wariancie z zewnętrzną biblioteką trzeba by to obchodzić.

### ADR-017 — S3 odłożone

`async-aws/s3` z ADR-011 jest równie nieosiągalny. Świadomie **nie piszę** adaptera S3 teraz.
Kod, którego nie da się uruchomić przeciwko prawdziwej usłudze, wyglądałby na gotowy
i byłby niesprawdzony w jedynym miejscu, które ma znaczenie. Brak kodu jest widoczny,
fałszywa gotowość nie.

Interfejs jest zaprojektowany pod obie implementacje, więc dołożenie adaptera nie zmieni
ani jednej linii kodu aplikacyjnego.

---

## Błąd wykryty przez testy

`StoragePath::fromString()` wykonywał `trim( $path, '/' )` **przed** walidacją, przez co
ścieżka bezwzględna `/etc/passwd` była po cichu zamieniana na względną `etc/passwd`
zamiast odrzucona. Nie była to podatność na wyjście poza magazyn — plik i tak wylądowałby
wewnątrz katalogu bazowego — ale ciche naprawianie złych danych wejściowych ukrywa błędy.
Poprawione: walidujemy wejście, zanim cokolwiek z niego obetniemy. Przy okazji doszło
odrzucanie podwójnych ukośników i ścieżek windowsowych.

---

## Co powstało

**Magazyn plików.** `StorageProvider` + `LocalStorage` z zapisem atomowym (plik tymczasowy
→ `rename()`), więc przerwane wysyłanie nie zostawia obiektu wyglądającego na kompletny.
Katalog leży poza `uploads` i dostaje plik blokujący serwowanie przez Apache.

**Ścieżki.** `StoragePath` składa się z ULID-ów, więc nie da się jej zgadnąć ani wyliczyć
z sąsiedniej. Cztery przestrzenie prywatne (`originals`, `previews`, `thumbs`, `finals`)
i jedna publiczna (`brand`) — rozdział jest w typie, nie w komentarzu.

**Pipeline obrazów.** `GdProcessor` (przetestowany na prawdziwych plikach — to środowisko
ma GD z AVIF i WebP) oraz `ImagickProcessor` jako ścieżka produkcyjna, wybierane fabryką.
Warianty nie powiększają małych zdjęć, korygują obrót z EXIF i **nie niosą metadanych** —
zdjęcie z sesji newborn zawiera lokalizację domu klienta.

**Tokeny.** `SecureToken` — 256 bitów entropii, w bazie wyłącznie hash, porównanie w czasie
stałym, base64url bezpieczny w URL-u. `AccessGrant` rozstrzyga trzy niezależne powody
odmowy (wygaśnięcie, unieważnienie, wyczerpanie limitu) w jednym miejscu, żeby żaden
kontroler nie sprawdził tylko dwóch z nich.

**Kolejka.** Zajęcie odporne na wyścig dwóch workerów, ponawianie z rosnącym opóźnieniem
(1→2→4→8→16 min, górna granica godzina), limit prób, zwolnienie zadań po awarii procesu
roboczego, priorytety, limit współbieżności per tenant, anulowanie zadań oczekujących —
ale nie tych w trakcie, bo skasowanie wiersza zostawiłoby sierotę.

Odczyt kolejki jest celowo ponadtenantowy, co łamie regułę indeksów z sesji 3. Wyjątek jest
zadeklarowany jawnie metodą `crossTenantReads()` z uzasadnieniem, a nowy test pilnuje,
że uzasadnienie istnieje i że wiersze nadal należą do tenantów.

---

## Testy

| Zestaw | Nowe | Razem |
|---|---|---|
| Ścieżki i uprawnienia plików | 14 | |
| Tokeny i dostęp czasowy | 12 | |
| Magazyn lokalny (prawdziwy system plików) | 11 | |
| Kolejka zadań (prawdziwy SQL) | 14 | |
| Pipeline obrazów (prawdziwe pliki JPEG/WebP) | 14 | |
| Wcześniejsze zestawy | — | 64 |
| **Razem** | **+67** | **129** |

Pozostałe bramki: 9/9 bloków · 18/18 par kontrastu · 67 plików PSR-4.

**Czego nie zweryfikowano automatycznie:** `ImagickProcessor` — to środowisko nie ma
rozszerzenia Imagick. Testy pokrywają GD oraz wybór implementacji przez fabrykę,
w tym poprawne zejście na fallback. Weryfikacja ścieżki Imagicka jest pozycją
w checkliście wydania.

## Wpływ

| | |
|---|---|
| Baza | 2 nowe tabele: `kadr_jobs`, `kadr_download_tokens` (migracja 0002) |
| Wydajność | pipeline w kolejce, nie w żądaniu · brak powiększania wariantów · suma derywatów mniejsza od oryginału (zmierzone testem) |
| Bezpieczeństwo | ścieżki nie do zgadnięcia · oryginały nieosiągalne publicznie · EXIF i GPS usuwane z podglądów · tokeny jako hash, z wygaśnięciem i unieważnieniem |

## Następny krok

**Sesja 5/15 — konta, uwierzytelnianie, REST API v1.** Wszystkie elementy wysyłania zdjęć
są gotowe; brakuje warstwy HTTP, która je zepnie.

---

# SESJA 5/15 — Uwierzytelnianie klienta, wysyłanie zdjęć end-to-end

**Data:** 2026-09-12 · **Wersja:** 0.4.0 → **0.5.0**
**Testy:** 129 → **160**

---

## Najważniejsze: ścieżka wysyłania zdjęcia działa od początku do końca

```
fragmenty (5 MB) → scalenie → weryfikacja hasha → zapis oryginału
    → zadanie w kolejce → worker → metadane → warianty AVIF/WebP → gotowe
```

Test `testEndToEndFromChunksToReadyVariants` przechodzi tę drogę na prawdziwym
SQLite, prawdziwym systemie plików i prawdziwym GD. **Ani jednej atrapy.**

Rzeczy, które ta ścieżka robi dobrze i które łatwo zrobić źle:
- **limit planu i duplikat sprawdzane PRZED transferem** — klient nie wysyła
  200 MB po to, żeby dowiedzieć się, że format jest nieobsługiwany albo brakuje miejsca,
- **hash weryfikowany po scaleniu** — uszkodzony transfer nie zostaje zapisany
  jako poprawne zdjęcie,
- **fragment można wysłać ponownie** — zerwane łącze nie wymaga zaczynania od nowa,
- **fragmenty sprzątane po scaleniu** — porzucone wysyłki nie zapełniają dysku,
- **przetwarzanie idempotentne** — ponowione zadanie nadpisuje warianty, nie duplikuje.

---

## Błąd znaleziony we własnym kodzie w trakcie pisania

`ChunkedUpload::complete()` budował ścieżkę pliku z ULID-a wygenerowanego lokalnie,
a `AssetRepository::create()` generował **własny** identyfikator przy zapisie. Wiersz
w bazie i plik na dysku wskazywałyby na różne ULID-y, przez co plik stałby się
nieosiągalny — a wyglądałoby to na działające, bo zapis się udaje i status się zmienia.

Naprawa: repozytorium przyjmuje identyfikator z zewnątrz, z komentarzem wyjaśniającym,
dlaczego akurat tu jest to potrzebne.

Dwa dalsze błędy wyłapały testy: brakująca metoda `AssetRepository::update()` oraz
błędny scenariusz w moim własnym teście limitu planu (plik 6 GB odbijał się najpierw
o maksymalny rozmiar pojedynczego pliku — i tak ma być, bo tańsze sprawdzenie idzie
pierwsze). Doszedł test pilnujący tej kolejności.

---

## Uwierzytelnianie klienta (ADR-003)

Magic link zamiast hasła, bo klientka z persony P4 nie założy konta o 22:30 na telefonie —
a zmuszanie jej do tego kosztuje fotografa wybór zdjęć, czyli pieniądze.

Reguły wymuszone i przetestowane:
- link jest **jednorazowy** i żyje 15 minut — kliknięcie w ten sam link z historii
  przeglądarki nie zaloguje ponownie,
- **brak konta nie jest rozróżnialny od sukcesu** — inaczej formularz stałby się
  wyszukiwarką klientów fotografa,
- wszystkie powody odmowy dają **ten sam komunikat**,
- token magic linku **nie działa jako token sesji** i odwrotnie,
- sesja jednego fotografa **nie działa u drugiego**,
- wylogowanie unieważnia natychmiast; jest też wylogowanie ze wszystkich urządzeń.

## Throttling

Progi z `docs/SECURITY.md` w jednym miejscu, żeby nie rozjechały się z dokumentacją.
Przekroczenie zamyka bramkę na czas blokady, a nie tylko do końca okna — inaczej
atakujący czekałby sekundę i próbował dalej.

⚠️ Wariant produkcyjny stoi na obiektowym cache WordPressa. **Bez trwałego cache
(Redis, Memcached) limity nie działają między żądaniami.** Instalacja bez niego dostaje
ostrzeżenie na ekranie stanu, zamiast cicho udawać ochronę.

## Warstwa WordPressa

Role `kadr_owner` i `kadr_member` z uprawnieniami z warstwy domenowej · fotograf wchodzący
na `/wp-admin` trafia do swojej aplikacji · pasek WordPressa ukryty · trasy `/app`, `/k`,
`/g/{token}`, `/b/{studio}`, `/d/{token}` · worker kolejki na cronie · kontener składający
zależności.

Baza kontrolerów REST: jednolity kształt `{ data, meta }`, jedno mapowanie kodu błędu
na status HTTP, paginacja kursorowa, `permission_callback` jako metoda pomocnicza —
`__return_true` nie ma jak się tu pojawić.

---

## Testy

| Zestaw | Nowe |
|---|---|
| Uwierzytelnianie klienta | 13 |
| Wysyłanie zdjęć (pełna ścieżka) | 14 |
| Runner zadań | 4 |
| **Razem** | **+31 → 160** |

Pozostałe bramki: 9/9 bloków · 18/18 par kontrastu · 83 pliki PSR-4.

## Czego NIE zrobiono w tej sesji

| Pozycja | Powód |
|---|---|
| Konkretne endpointy REST | baza gotowa, brakuje rejestracji tras — idą razem z widokami, które konsumują |
| Rejestracja fotografa i onboarding | **przeniesione do sesji 6.** Formularz napisany przed systemem komponentów trzeba by pisać dwa razy |
| Endpoint pobrania | elementy gotowe (`SecureToken`, `AccessGrant`, trasa `/d`), brakuje kontrolera |

Bramka sesji („fotograf rejestruje się i widzi panel") jest spełniona **częściowo**:
uwierzytelnianie klienta i cała ścieżka wysyłania działają, panel fotografa powstaje
w sesji 6.

## Następny krok

**Sesja 6/15 — powłoka aplikacji.** Na starcie decyzja o bundlerze dla `/app` (kwestia O7).

---

# SESJA 6/15 — Powłoka aplikacji i design system panelu

**Data:** 2026-09-13 · **Wersja:** 0.5.0 → **0.6.0**
**Testy:** 160 PHP (bez zmian) + **18 sprawdzeń w prawdziwej przeglądarce** (nowe)

---

## Decyzja, na którą czekały cztery sesje: czym budować panel (ADR-018)

ADR-013 celowo nie rozstrzygnął, czym budować `/app`. Dziś było wiadomo, co ten
interfejs ma unieść: tabele, dialogi, przeciąganie zdjęć, wysyłanie z postępem,
paletę poleceń, a w sesji 8 siatkę z półtora tysiąca kadrów.

**Wybrane: Preact + @preact/signals + htm jako gotowe moduły ES, dołączone do wtyczki.**
Bez bundlera i — co się okazało w trakcie — **bez mapy importów**: skrypt
`tools/vendor-preact.sh` przepisuje odwołania między pakietami na ścieżki względne,
więc przeglądarka rozwiązuje je sama.

Koszt: **9,9 KB gzip** przy budżecie 120 KB na panel. Korzyść z ADR-013 zostaje
nienaruszona — wtyczka dalej działa zaraz po rozpakowaniu, bez npm i bez Composera.

**Konsekwencja, którą trzeba nazwać wprost:** deklaracja „zero zależności
produkcyjnych" przestała obowiązywać dla JavaScriptu. Dla PHP obowiązuje dalej.
`CLAUDE.md` §8 został poprawiony jawnie, a nie obchodzony po cichu; licencje i wersje
są w `assets/vendor/LICENSES.md`.

**Landing nie dostał ani bajta z tych bibliotek.** Arkusz i skrypt panelu ładują się
wyłącznie na trasie `/app` — strona marketingowa ma dalej 3,8 KB JS.

---

## Dwa błędy, które znalazła dopiero przeglądarka

Testy jednostkowe nie miały jak ich zobaczyć. Oba wyszły w `tools/check-panel.mjs`.

**1. Import z rdzenia sygnałów zamiast z integracji.** `runtime.js` importował
`signal` z `signals-core.js`. Wszystko liczyło się poprawnie — i nic się nie
przerysowywało, bo rdzeń nie wie nic o Preakcie. Tabela sortowała dane w pamięci
i pokazywała stare. Poprawka to jedna ścieżka (`signals.js`) i komentarz w kodzie,
żeby nikt tego nie „uprościł" z powrotem.

**2. Nawigacja odjeżdżała razem ze stroną.** Widoczne dopiero na zrzucie ekranu:
przy przewijaniu listy galerii boczna nawigacja wyjeżdżała w górę. Powłoka dostała
`height: 100dvh` i `overflow: hidden`, a przewijanie przeniosło się do obszaru treści
(`scrollbar-gutter: stable`, żeby układ nie skakał przy pojawieniu się paska).

Wniosek na kolejne sesje: **panel trzeba oglądać w przeglądarce, nie tylko testować.**
`node tools/check-panel.mjs` dołącza do bramki zamknięcia sesji.

---

## Co powstało

| Moduł | Rzecz, którą robi dobrze |
|---|---|
| `api.js` | błąd API zachowuje kod i status, więc widok nie zgaduje z treści komunikatu; paginacja kursorowa, nie `OFFSET` |
| `table.js` | **sortowanie liczy serwer** — przy tysiącu galerii ściąganie wszystkiego, żeby posortować lokalnie, nie ma sensu; szkielet ma strukturę docelowej tabeli, więc układ nie skacze |
| `dialog.js` | natywny `<dialog>` + `showModal()`: pułapka fokusu, Escape i tło modalne z przeglądarki; Escape i kliknięcie w tło to **zawsze** rezygnacja, nigdy potwierdzenie |
| `drawer.js` | szuflada do edycji bez utraty kontekstu listy; popover przez Popover API, więc menu w tabeli nie przycina się o `overflow` rodzica |
| `form.js` | walidacja przy opuszczeniu pola, poprawka kasująca błąd natychmiast, **błąd z serwera trafia pod pole, którego dotyczy** (`details.params`), fokus na pierwszym błędnym polu |
| `palette.js` | ⌘K / Ctrl+K, wyszukiwanie i nawigacja z klawiatury |
| `toast.js` | powiadomienie z akcją cofnięcia — komunikat bez wyjścia z sytuacji to tylko hałas |

Puste stany są częścią onboardingu, nie komunikatem o błędzie: mówią, co to jest,
po co i jaki jest następny krok.

---

## Katalog komponentów, który naprawdę działa

`php tools/preview-app.php` generuje **jeden samodzielny plik HTML**, w którym panel
działa: paleta, dialogi, szuflada z formularzem, sortowanie tabeli, powiadomienia.
To nie makieta — to te same moduły, które trafiają do wtyczki. Moduły są osadzone
w dokumencie i zamieniane na adresy blob, więc całość działa z `file://`, bez serwera.

Dane w podglądzie są jawnie oznaczone jako przykładowe (CLAUDE.md §9). W kodzie
wtyczki nie ma ani jednego wymyślonego klienta, zamówienia ani statystyki.

---

## Czego świadomie NIE zrobiono

- **Widoków na prawdziwych danych.** Trasy REST powstają w sesji 7, razem z pierwszym
  ekranem, który ich używa. Endpoint napisany „na zapas", bez widoku, zwykle trzeba
  potem przepisać.
- **Rejestracji fotografa** — przeniesiona do sesji 7 z tego samego powodu: formularz
  ma powstać razem z endpointem, który go obsłuży.
- **Trzech motywów w panelu.** Motywy Noir / Paper / Minimal dotyczą galerii klienta
  (sesja 8). Panel ma jeden motyw i w nim kontrast jest zmierzony — bramka sesji
  mówiła o trzech, więc zapisujemy to jako zastrzeżenie, a nie przemilczamy.

---

## Bramka zamknięcia

```
160 testów PHP ✓   9/9 bloków ✓   18/18 par kontrastu ✓   83 pliki PSR-4 ✓
13 plików JS bez błędów składni ✓   18/18 sprawdzeń w przeglądarce ✓
zero błędów w konsoli ✓
```

Budżety: panel **10,9 KB gzip** kodu + **9,9 KB** runtime'u przy limicie 120 KB.
Arkusz panelu 5,4 KB gzip. Landing bez zmian.

---

## Następny krok

**Sesja 7/15 — galerie w panelu fotografa.** Rejestracja i onboarding, pierwsze trasy
REST, lista galerii, tworzenie i edycja w szufladzie, wysyłanie zdjęć w interfejsie
i siatka z wirtualizacją. Bramka: galeria ślubna z 800 zdjęciami nie blokuje
ani interfejsu, ani serwera.

---

# SESJA 7/15 — Panel na prawdziwych danych: rejestracja, galerie, wysyłanie zdjęć

**Data:** 2026-09-13 · **Wersja:** 0.6.0 → **0.7.0**
**Testy:** 160 → **198 PHP** · 18 → **52 sprawdzenia w przeglądarce**

---

## Punkt wyjścia: panelu nie dało się zobaczyć w WordPressie

Trasa `/app` istniała, sprawdzała uprawnienia i ładowała moduły — po czym
wywoływała `do_action( 'kadr_render_route' )`, pod które **nikt nie był podpięty**.
Powłoka, którą widać było na zrzutach z sesji 6, mieszkała w narzędziu podglądu,
nie we wtyczce. Wejście na `/app` dawało stronę 404 motywu.

To był pierwszy element tej sesji i punkt, od którego wszystko inne miało sens.

---

## Panel jest własnym dokumentem, nie podstroną motywu

`Presentation\App\Shell` renderuje własny `<head>`, własny układ i nawigację
**po stronie serwera** — rama aplikacji jest widoczna, zanim załaduje się
choćby jeden moduł.

Zasoby motywu i innych wtyczek są na `/app` odpinane (`kadr_app_keep_asset`
pozwala to zawęzić). To nie jest czystość dla czystości: arkusz obcego motywu
wstrzyknięty w panel potrafi przesunąć układ albo dołożyć własny pasek
nawigacji, a fotograf nie ma jak tego naprawić.

`Shell::body()` jest osobną metodą, która nie dotyka bazy — dzięki temu
podgląd renderuje **produkcyjny markup**, a nie jego kopię. Nie ma dwóch
wersji powłoki, które rozjadą się przy pierwszej zmianie.

---

## Fotograf ma wreszcie jak wejść (ADR-019)

Rejestracja zakłada **konto i studio w jednym kroku**. Rozdzielenie ich to
dodatkowy ekran, na którym część ludzi odpada, a konto bez studia nie ma
czym zarządzać.

Kolejność jest odwracana przy błędzie: konto powstaje pierwsze (studio
potrzebuje jego identyfikatora), więc gdy zapis studia padnie, **konto musi
zniknąć**. Inaczej człowiek ma login bez studia i nie może się zarejestrować
ponownie, bo adres jest zajęty — sytuacja bez wyjścia bez administratora.
Pilnuje tego osobny test.

Logowanie jest własne, nie `wp-login.php`. Hasło dalej liczy WordPress
(ADR-003 stoi), ale trzy rzeczy są nasze: limit prób na konto i adres IP,
**jeden komunikat** dla złego hasła i nieistniejącego konta, oraz sprawdzenie
uprawnienia `kadr_access_app`. Adres powrotu ograniczony do `/app` — formularz
logowania przyjmujący dowolny adres zwrotny jest nośnikiem phishingu.

---

## Reguła produktu, która nie jest walidacją formularza

Galeria z limitem pakietu, ale **bez ceny zdjęcia ponad pakiet**, jest
odrzucana. To nie jest kaprys interfejsu: taka konfiguracja oznacza, że klient
wybierze więcej zdjęć i nikt za nie nie zapłaci — czyli dokładnie tę stratę,
którą ten produkt ma likwidować (CLAUDE.md §1).

Formularz pokazuje przy okazji, ile warte jest dziesięć zdjęć ponad pakiet,
i przelicza to w trakcie pisania ceny. To jest ruch, który **tłumaczy produkt**,
a nie zdobi ekran (§7).

Limit planu siedzi w `ManageGalleries`, nie w kontrolerze. Gdyby siedział
w kontrolerze, każda kolejna ścieżka tworzenia galerii (import, duplikowanie,
szablon) musiałaby go powtórzyć — i któraś by zapomniała.

---

## Wysyłanie zdjęć i skrót liczony przyrostowo (ADR-020)

Serwerowa strona wysyłania działała od sesji 5. Doszły trasy i interfejs:
przeciąganie, postęp pojedynczy i całościowy, anulowanie, ponawianie,
rozpoznanie duplikatu i **komunikaty, z którymi da się coś zrobić** — brak
miejsca, zły format i zerwane łącze wymagają trzech różnych reakcji.

Skrót SHA-256 nie idzie przez `crypto.subtle.digest`, bo tamta funkcja
przyjmuje cały plik naraz. RAW z wesela ma sto megabajtów, a fotograf wysyła
ich osiemset. Własna implementacja przyrostowa liczy skrót **przy okazji
czytania fragmentów, które i tak lecą na serwer**: jedno przejście, stała pamięć.

Weryfikacja jest tu obowiązkowa i jest: atrapa serwera w podglądzie liczy skrót
niezależnie (`crypto.subtle.digest`) i odrzuca niezgodny, więc test
w przeglądarce sprawdza naszą implementację na prawdziwym pliku. Do tego
wektory testowe z RFC 6234 i porównanie z OpenSSL dla dziewięciu rozmiarów
i pięciu wzorców podziału na fragmenty.

**Błąd znaleziony po drodze:** `digest()` nie był idempotentny — drugie
wywołanie zwracało śmieci. Zły skrót oznacza odrzucenie poprawnie przesłanego
pliku i wygląda jak uszkodzony transfer, czyli awaria nie do zdiagnozowania
z zewnątrz. Wynik jest teraz zapamiętywany, a dopisywanie danych po policzeniu
skrótu rzuca wyjątkiem.

---

## Siatka, która nie klęka przy ośmiuset kadrach

Rysowane są tylko wiersze w oknie plus dwa zapasu. Wysokość rezerwowana jest
dla **całej** liczby zdjęć, nie dla wczytanych — inaczej pasek przewijania
rósłby skokowo przy każdej doczytanej stronie i wyrywał widok spod kursora.
Kolejne strony dochodzą, gdy przewijanie zbliża się do końca wczytanych.

Zdjęcie czekające na warianty z kolejki ma zarezerwowane miejsce o właściwych
proporcjach, więc siatka nie skacze, gdy miniatura dojdzie (zero CLS).

Miniatury idą przez **kontrolowany endpoint**, który najpierw sprawdza
uprawnienie i tenanta. Prywatne zdjęcie nigdy nie leży pod przewidywalnym
adresem, a katalog plików jest poza `wp-content` (§5).

---

## Paginacja kursorowa po ULID-zie (ADR-021)

`OFFSET 50000` skanuje pięćdziesiąt tysięcy wierszy, żeby oddać dwadzieścia —
i gubi wiersze, gdy ktoś doda galerię w trakcie przeglądania. Kursorem jest
`public_id`: ULID koduje czas utworzenia, więc kolejność jest ta sama co po
`created_at`, kolumna jest unikalna, a kursor **jest już publicznym
identyfikatorem**, więc nie zdradza sekwencyjnego `id`.

Metoda leży w `TenantRepository`, czyli w warstwie izolacji — nie da się jej
wywołać bez tenanta. Test podaje kursor cudzego tenanta i sprawdza, że nie
wychodzą cudze wiersze.

---

## Trzy błędy wizualne tej samej rodziny

Wszystkie trzy znalazło **patrzenie na wyrenderowaną stronę**, nie testy:

1. `.kadr h1` z base.css (specyficzność 0,1,1) wygrywało z `.kadr-view__title`
   (0,1,0) — dashboard dostał nagłówek wielkości sekcji hero.
2. `.kadr-table td { text-align: left }` wygrywało z `.kadr-table__numeric` —
   liczby zostawały po lewej.
3. `.kadr img { height: auto }` wygrywało z regułą obrazka w siatce —
   miniatury nie wypełniały kafelków.

Wniosek zapisany w arkuszu przy trzeciej poprawce: **jeśli reguła „nie działa",
zacznij od porównania jej z regułą elementową w base.css.** Panel ma teraz
własną skalę typografii — landing jest projektowany pod pierwsze wrażenie,
panel pod setki godzin pracy.

---

## Błąd, który zatrzymałby pierwszą rejestrację

Wpis do dziennika audytu przy tworzeniu studia nie ustawiał `updated_at`,
a kolumna jest `NOT NULL`. Rejestracja padałaby u **pierwszego użytkownika**,
a wycofanie zabierałoby jego konto — czyli objaw wyglądałby jak „nie da się
założyć konta", bez śladu przyczyny. Znalazł to test, zanim zobaczyła
przeglądarka.

---

## Czego świadomie NIE zrobiono

- **Kolejności zdjęć, okładki i podglądu „oczami klienta"** — przeniesione do
  sesji 8. To są ustawienia tego, co zobaczy klient, a widok klienta jeszcze
  nie istnieje; robienie ich teraz oznaczałoby pisanie na ślepo.
- **Własnego ekranu resetu hasła** — na razie prowadzi przez `wp-login.php`
  (kwestia O11). Działa, ale łamie regułę „fotograf nie widzi WordPressa"
  i jest do domknięcia.
- **Testu obciążeniowego kolejki** — nie ma tu MySQL-a ani WordPressa
  (kwestie O9 i O12). Bramka sesji mówiła o ośmiuset zdjęciach; sprawdzone
  jest to, co sprawdzalne bez serwera: wirtualizacja siatki i stała pamięć
  przy liczeniu skrótu.

---

## Bramka zamknięcia

```
198 testów PHP ✓   9/9 bloków ✓   18/18 par kontrastu ✓   101 plików PSR-4 ✓
21 plików JS bez błędów składni ✓   52/52 sprawdzenia w przeglądarce ✓
zero błędów w konsoli ✓
```

---

## Następny krok

**Sesja 8/15 — galeria klienta.** Persona krytyczna: telefon, 22:30, jedną ręką,
czasem słaby zasięg. To ekran, od którego zależy, czy klientka dopłaci za zdjęcia
ponad pakiet — czyli cała ekonomia tego produktu. Bramka: LCP poniżej 2,5 s na 4G
przy galerii z 500 zdjęciami i pełna obsługa z klawiatury.

---

# SESJA 8/15 — Galeria klienta

**Data:** 2026-09-13 · **Wersja:** 0.7.0 → **0.8.0**
**Testy:** 198 → **212 PHP** · 52 → **89 sprawdzeń w przeglądarce** (56 panel + 33 galeria)
**Kontrast:** 18 → **51 par** (18 panel + 33 w trzech motywach galerii)

---

## Persona, która rozstrzygnęła każdą decyzję w tej sesji

**Telefon, 22:30, jedną ręką, czasem słaby zasięg.** Klientka nie zakłada konta,
nie instaluje aplikacji, nie czyta instrukcji. Klika link z SMS-a i chce
zobaczyć zdjęcia.

Z tego wynikła pierwsza i najważniejsza decyzja (ADR-022): **galeria nie używa
Preacta.** Odruch mówił co innego — to ten sam produkt i ten sam zespół
komponentów. Ale przy tamtym podejściu pierwsze zdjęcie pojawia się dopiero po
pobraniu dokumentu, pobraniu modułów, wykonaniu ich, żądaniu do API i dopiero
wtedy pobraniu pliku. Pięć kroków, z czego cztery przed pierwszym pikselem
fotografii.

Serwer renderuje więc pierwszy ekran razem z pierwszymi kadrami, a skrypt
dokłada lightbox i doczytywanie. **Galeria działa bez tego skryptu.** Waży on
3,2 KB gzip przy budżecie 60 KB.

---

## Układ, który trzeba było wyrzucić po zobaczeniu w przeglądarce (ADR-023)

Pierwszy podszedł CSS `columns` — standardowy masonry. Wygląda dobrze na
zrzucie i jest zły z powodu, którego nie widać, dopóki nie spojrzy się
na kolejność: **treść wypełnia kolumnę do końca, zanim przejdzie do następnej.**

Przy galerii ślubnej znaczy to, że klientka przewija w dół całą lewą połowę
serii — kadry 1 do 400 — a potem wraca na górę po prawą. Sesja jest
chronologiczna. To jest opowieść, nie zbiór.

Zastąpione rzędami o stałej wysokości, w których każdy kadr ma szerokość
wynikającą z własnych proporcji, a rząd dociąga się do pełnej szerokości.
Kolejność zachowana, kadr nieprzycięty, zero JavaScriptu — proporcje wpisuje
serwer, bo zna wymiary każdego zdjęcia.

Test w przeglądarce pilnuje teraz, że pierwszy rząd zawiera kadry **1 i 2**,
a nie co dwunasty.

---

## Trzy motywy i audyt, który od razu znalazł dwa błędy

`tools/check-contrast.php` czytał dotąd wyłącznie paletę Obsidian. Dopisanie
trzech palet galerii (kwestia O10) dało natychmiastowy zwrot:

1. **Paper:** obrys przycisku 2,87:1 i obrys pola PIN-u 2,92:1 przy wymaganych 3:1.
2. **Paper, poważniejszy:** tytuł na okładce **poniżej 2:1** — ciemny atrament
   motywu na ciemnym przyciemnieniu.

Drugi błąd wymusił decyzję, która jest prawdziwym rozstrzygnięciem projektowym:
**okładka ze zdjęciem ma własne kolory tekstu, niezależne od motywu.** Nie da
się z góry wiedzieć, jakie zdjęcie wybierze fotograf. Jedyne, co działa dla
każdego kadru, to jasny tekst na przyciemnieniu — i takie jest teraz wymuszone
we wszystkich trzech motywach. Audyt sprawdza najgorszy przypadek: przyciemnienie
położone na bieli.

---

## Jedno wyjście poza tenanta, świadome i ograniczone (ADR-024)

Klientka otwiera link, nie będąc nikim zalogowanym: nie ma sesji, konta ani
tenanta. Tymczasem każde repozytorium wymaga `TenantContext`.

`GalleryLookup` zamienia hash tokenu na identyfikator tenanta i galerii —
**i nic więcej.** Nie czyta ani jednego zdjęcia, klienta ani ustawienia.
Od tego momentu wszystko idzie przez zwykłe repozytoria, przez tę samą warstwę
izolacji, co panel fotografa. Klasa leży w `Platform\`, więc widać w imporcie,
że kod wychodzi poza tenanta.

Reguły, których pilnują testy:
- **każdy powód odmowy daje identyczny komunikat** — zły token, wygaśnięcie,
  unieważnienie, wycofanie publikacji. Rozróżnianie ich mówiłoby zgadującemu,
  że trafił w istniejący link,
- **wycofanie galerii z publikacji zamyka wszystkie wydane linki naraz** —
  fotograf ma jeden przełącznik, nie dwa,
- **zdjęcie musi należeć do TEJ galerii**, nie tylko do tego tenanta; inaczej
  jeden link otwierałby cały dorobek fotografa,
- **PIN ma limit prób liczony po linku, nie po adresie IP** — klientka
  i zgadujący mogą siedzieć za tym samym adresem, a bronimy linku. Cztery
  cyfry bez limitu są do zgadnięcia w kilkanaście minut.

---

## Miniatura, która jest w dokumencie, a nie za żądaniem

LQIP trafił jako **kolumna w tabeli zdjęć**, nie jako plik w magazynie. Cały
sens miniatury zastępczej polega na tym, że idzie razem z dokumentem — plik
w magazynie to kolejne żądanie na 4G, czyli dokładnie to, czego unikamy.

Generuje ją pipeline przy okazji wariantów, ograniczona do 2 KB. Nieudana
miniatura **nie przekreśla zdjęcia**: galeria bez LQIP-u wygląda gorzej przez
chwilę, galeria bez zdjęcia nie wygląda wcale. Pilnuje tego osobny test.

---

## Błąd wyłapany przez test, nie przez oko

Ukryty przycisk pobierania był widoczny. `display: inline-flex` z klasy
wygrywa z regułą przeglądarki dla `[hidden]` — ta sama rodzina pułapek,
co trzy błędy specyficzności z sesji 7. Poprawka i komentarz w arkuszu.

Przy okazji wyleciała inna rzecz: adresy pełnego wariantu i pobrania były
**wyliczane w przeglądarce z adresu miniatury** wyrażeniem regularnym.
Działa, dopóki ktoś nie zmieni ścieżki — a wtedy psuje się po cichu. Teraz
serwer wypisuje je w atrybutach.

---

## Czego świadomie NIE zrobiono

- **View Transitions między siatką a lightboxem.** Przejście działa dobrze
  dopiero wtedy, gdy lightbox pokazuje TEN SAM plik, co kadr w siatce.
  U nas pokazuje większy wariant, więc przejście i tak kończy się podmianą
  obrazka. Wróci z Selection Roomem, gdzie ruch niesie znaczenie.
- **ZIP w tle** — przeniesiony do sesji 10 (dostawa), gdzie i tak jest
  potrzebny dla pełnych plików.
- **Zmiana kolejności przeciąganiem i wybór wielokrotny** — do sesji 9.
  Oba są operacjami na zaznaczeniu, a zaznaczenie jest tematem Selection Roomu.
- **Wysyłanie logo studia** — slot w galerii jest, pole jest puste (kwestia O14).

---

## Bramka zamknięcia

```
212 testów PHP ✓   9/9 bloków ✓   51 par kontrastu ✓   109 plików PSR-4 ✓
23 pliki JS bez błędów składni ✓   56/56 sprawdzeń panelu ✓
33/33 sprawdzenia galerii ✓   zero błędów w konsoli ✓
```

Budżet galerii: **3,2 KB JS gzip** przy limicie 60 KB, arkusz 3,3 KB gzip.

**LCP pozostaje niezmierzone** (kwestia O13). Zrobione jest to, co o nim
decyduje: kadry są w dokumencie, mają `fetchpriority` i wymiary, a odkładanie
zaczyna się od piątego zdjęcia.

---

## Następny krok

**Sesja 9/15 — Selection Room.** Wyróżnik ②, etap, na którym fotograf faktycznie
zarabia, i jedyny, którego brak sprawia, że produktu nie da się jeszcze sprzedać.
Licznik pakietu liczony na żywo ma tłumaczyć klientce dopłatę, zanim ktokolwiek
o niej napisze. Bramka: klientka wybiera 28 zdjęć przy pakiecie 20 i widzi kwotę,
zanim cokolwiek zatwierdzi.
