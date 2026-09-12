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
