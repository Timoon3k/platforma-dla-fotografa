# PROJECT_STATE — Kadr

**Wersja:** 0.7.0
**Ostatnia aktualizacja:** 2026-09-13 (Sesja 7/15)
**Branch:** `claude/premium-photography-saas-u8xl6y`

---

## Gdzie jesteśmy

Sesja 7/15 zamknięta. **Cała ścieżka fotografa jest po raz pierwszy kompletna
w kodzie**: rejestracja studia → logowanie → panel → utworzenie galerii →
wysłanie zdjęć → siatka kadrów. Trasy `/rejestracja`, `/logowanie` i `/app`
istnieją, panel renderuje się **poza motywem**, a wszystkie dane jadą przez
REST API v1.

**Uwaga — czego nie wiemy:** to środowisko nie ma WordPressa ani MySQL-a,
więc ta ścieżka nie została przejechana w prawdziwej instalacji (kwestia O9).
Warstwa serwerowa ma testy na prawdziwym SQL-u, a panel i formularze —
w prawdziwej przeglądarce. Styku z WordPressem nikt jeszcze nie sprawdził.

Czego brakuje do sprzedaży: galerii klienta (sesja 8), Selection Roomu (sesja 9)
i płatności (sesje 11–13).

---

## Co działa

| Obszar | Stan |
|---|---|
| Bootstrap, wymagania, autoload PSR-4 bez Composera | ✅ `kadr.php`, 80 linii |
| Migracje z wersją schematu, uruchamiane przy aktywacji i aktualizacji | ✅ `MigrationRunner` |
| Szesnaście tabel rdzenia | ✅ `Schema\Tables` |
| Deklaratywny schemat z dwiema gramatykami (MySQL + SQLite) | ✅ testy na prawdziwym SQL |
| **Izolacja tenantów wymuszona konstrukcyjnie** | ✅ `TenantRepository` + 13 testów |
| Repozytoria: klienci, galerie, zdjęcia, wybory | ✅ `Database\Repositories` |
| Warstwa tenancy: kontekst, role, uprawnienia | ✅ `Domain\Tenancy` |
| Arytmetyka dopłaty za zdjęcia ponad pakiet | ✅ `Domain\Selection\PackageTally` |
| Rejestr planów i entitlementy | ✅ `Domain\Billing` |
| Strona marketingowa: 9 bloków, cennik, zgody | ✅ `blocks/` |
| Kierunek Obsidian + warstwa ruchu | ✅ ADR-015 |
| Magazyn plików: interfejs + LocalStorage z zapisem atomowym | ✅ `Infrastructure\Storage` |
| Ścieżki obiektów: ULID-y, rozdział prywatne/publiczne, walidacja | ✅ `Domain\Storage\StoragePath` |
| Pipeline obrazów: warianty AVIF/WebP, brak powiększania, usuwanie EXIF/GPS | ✅ `Infrastructure\Image` |
| Tokeny dostępu: hash w bazie, wygaśnięcie, unieważnienie, limit użyć | ✅ `Domain\Security` |
| Kolejka zadań: dzierżawa, ponawianie, limit per tenant | ✅ `Infrastructure\Queue` (ADR-016) |
| **Wysyłanie zdjęć end-to-end**: fragmenty → scalenie → kolejka → warianty | ✅ `Application\Gallery` |
| Uwierzytelnianie klienta: magic link jednorazowy, sesje unieważnialne | ✅ `Application\Auth` |
| Throttling: logowanie, magic link, PIN, pobrania, API | ✅ `Domain\Security` |
| Role i uprawnienia; fotograf nie widzi WP Admina | ✅ `Infrastructure\WordPress\Capabilities` |
| Routing /app, /k, /g, /b, /d poza WP Adminem | ✅ `Rewrites` |
| Worker kolejki + runner z handlerami | ✅ `Worker`, `JobRunner` |
| Baza REST: kształt odpowiedzi, błędy, paginacja kursorowa | ✅ `Presentation\Rest\Controller` |
| **Powłoka panelu**: nawigacja, pasek górny, obszar treści, układ 375 px | ✅ `assets/css/app.css` |
| Runtime panelu: Preact + Signals + htm jako moduły ES, bez bundlera | ✅ ADR-018, 9,9 KB gzip |
| Klient REST z paginacją kursorową i typowanym błędem | ✅ `assets/js/app/api.js` |
| Tabela: sortowanie po stronie serwera, szkielet, pusty stan | ✅ `app/table.js` |
| Dialog i potwierdzenie operacji nieodwracalnej (natywny `<dialog>`) | ✅ `app/dialog.js` |
| Szuflada boczna i popover (Popover API z zapasem) | ✅ `app/drawer.js` |
| Formularze: walidacja inline, błędy z serwera pod polami, kopia robocza | ✅ `app/form.js` |
| Paleta poleceń ⌘K i powiadomienia z akcją cofnięcia | ✅ `app/palette.js`, `app/toast.js` |
| Katalog komponentów jako działający podgląd panelu | ✅ `tools/preview-app.php` |
| **Rejestracja fotografa**: konto + studio w jednym kroku, wycofanie przy błędzie | ✅ `Application\Studio\RegisterStudio` (ADR-019) |
| Własne logowanie: limit prób, jeden komunikat, sprawdzenie uprawnienia | ✅ `Rest\Routes\SessionController` |
| **Panel renderowany przez wtyczkę** — `/app` poza motywem, powłoka z serwera | ✅ `Presentation\App\Shell` |
| Trasy REST: `/today`, `/galleries`, `/clients`, `/assets`, `/uploads` | ✅ `Presentation\Rest\Routes` |
| Paginacja kursorowa po ULID-zie, w warstwie izolacji | ✅ `TenantRepository::findPageBy` (ADR-021) |
| Widok „Dzisiaj” na jednym zapytaniu zagregowanym | ✅ `views/today.js` |
| Lista galerii: filtr, wyszukiwarka, stronicowanie — wszystko po stronie serwera | ✅ `views/galleries.js` |
| Tworzenie i edycja galerii w szufladzie; licznik dopłaty liczony na żywo | ✅ `views/gallery-form.js` |
| **Wysyłanie zdjęć z panelu**: drag & drop, postęp, wznawianie, duplikaty | ✅ `upload.js` + `UploadsController` |
| SHA-256 liczony przyrostowo w przeglądarce, stała pamięć | ✅ `sha256.js` (ADR-020) |
| Siatka zdjęć z wirtualizacją i doczytywaniem stron | ✅ `views/gallery.js` |
| Miniatury przez kontrolowany endpoint, nigdy z katalogu | ✅ `AssetsController::thumb` |
| Weryfikacja panelu w prawdziwej przeglądarce | ✅ `tools/check-panel.mjs`, 52 sprawdzenia |
| Narzędzia: testy, spójność bloków, kontrast, PSR-4, podgląd, RAR, ZIP | ✅ `tools/` |

**198 testów PHP · 52/52 sprawdzeń w przeglądarce · 9/9 bloków · 18/18 par kontrastu ·
101 plików PSR-4 · 21 plików JS bez błędów składni.**

## Czego nie ma

- adaptera S3 — świadomie odłożony (ADR-017); MVP działa na dysku lokalnym
- galerii klienta i Selection Roomu — sesje 8–10
- kolejności zdjęć, okładki i podglądu „oczami klienta" — przeniesione do sesji 8
- własnego ekranu resetu hasła — na razie przez `wp-login.php` (kwestia O11)
- zamówień, koszyka i płatności — sesje 11–13
- commerce, płatności, abonamentów — sesje 11–13
- rezerwacji, CRM, dostawy — sesje 14–15
- plików fontów (na razie stosy zastępcze) — licencje OFL, zostaje osadzenie
- audytu w prawdziwym WordPressie — środowisko nie ma dostępu do wordpress.org ani MySQL-a

---

## Podjęte decyzje (skrót — pełne uzasadnienia w `docs/DECISIONS.md`)

| ADR | Decyzja |
|---|---|
| 001 | Nazwa robocza **Kadr**, slug `kadr`, namespace `Kadr\` |
| 002 | Architektura: wariant B — WP jako platforma, wtyczka jako aplikacja |
| 003 | Klient końcowy **poza** `wp_users`, własny identity store |
| 004 | Kolejka: Action Scheduler za własnym `QueueInterface` |
| 005 | Dane transakcyjne w custom tables, nigdy CPT |
| 006 | Płatności klientów: własne klucze fotografa, pierwszy adapter PayNow/Przelewy24 (BLIK) |
| 007 | Billing platformy: Stripe Billing |
| 008 | Cennik 69 / 149 / 299 zł + free tier na 5 projektów |
| 009 | Design: Atelier (marketing) + Studio OS (dashboard) + motywy galerii |
| 010 | Brak globalnego dark mode w v1.0 |
| 011 | Storage: `StorageProviderInterface`, Local + S3 (EU), `async-aws/s3` |
| 012 | i18n od pierwszej linii, text domain `kadr`, MVP po polsku |
| 013 | Brak kroku budowania w części publicznej — wtyczka działa po rozpakowaniu |
| 014 | Testy warstwy Domain bez frameworka (mikro-runner) |
| 015 | Kierunek Obsidian + warstwa ruchu — **zastępuje ADR-009 i ADR-010** |
| 016 | Własna kolejka zadań — **zastępuje ADR-004** (Action Scheduler) |
| 017 | S3 odłożone do momentu, w którym będzie potrzebne |
| 018 | Preact + Signals + htm jako dołączone moduły ES, bez bundlera — **domyka O7** |
| 019 | Własne ekrany rejestracji i logowania zamiast `wp-login.php` |
| 020 | Własna, przyrostowa implementacja SHA-256 w przeglądarce |
| 021 | Paginacja kursorowa po `public_id` (ULID), nie po `OFFSET` ani po `id` |

---

## Znane problemy i otwarte kwestie

| # | Kwestia | Kiedy |
|---|---|---|
| O1 | **Nazwa „Kadr” niezweryfikowana** w EUIPO/UPRP i u rejestratora domen. Zmiana jest jeszcze tania — po sesji 5 dotyka namespace'u, prefiksu tabel i migracji | przed sesją 5 |
| O2 | Wybór operatora płatności (PayNow / Przelewy24 / Autopay) | sesja 12 |
| O3 | Dostawca object storage w EU, koszt transferu i adapter S3 (ADR-017) | gdy pojawi się realny wolumen |
| O5 | Dokumenty prawne wymagają weryfikacji przez prawnika | przed premierą |
| O6 | Integracja z fakturowaniem PL — poza MVP, ale fotograf zapyta | post-MVP |
| O8 | Pliki fontów (Bricolage Grotesque, Geist) — licencje OFL, zostaje osadzenie `woff2` | sesja 7 |
| O10 | Trzy motywy galerii klienta nie mają jeszcze palet, więc narzędzie kontrastu mierzy tylko Obsidian | sesja 8 |
| O11 | Reset hasła fotografa nie ma własnego ekranu — prowadzi przez `wp-login.php` | sesja 8 |
| O12 | Zachowanie kolejki przy 800 zadaniach naraz nieprzetestowane — brak MySQL-a i WordPressa w tym środowisku | gdy będzie środowisko |
| O9 | Audyt w prawdziwej instalacji WordPressa — to środowisko nie ma dostępu do wordpress.org (403) ani serwera MySQL. Zastępczo działa harness `tools/preview.php` | gdy będzie dostępne środowisko |

*(O4 — licencje fontów — zamknięta: wszystkie trzy kroje są na OFL.
O7 — bundler dla `/app` — zamknięta przez ADR-018: bundlera nie ma.)*

## Następny logiczny krok

**SESJA 8/15 — galeria klienta.**

> Persona krytyczna: telefon, 22:30, jedną ręką, czasem słaby zasięg.
> To jest ekran, od którego zależy, czy klientka dopłaci za zdjęcia
> ponad pakiet — czyli cała ekonomia tego produktu.

1. Kolejność zdjęć, wybór wielokrotny i okładka (przeniesione z sesji 7 —
   to ustawienia tego, co zobaczy klient).
2. Siatka mozaikowa z leniwym doładowywaniem i LQIP.
3. Lightbox: klawiatura, swipe, gesty, zoom, pełny ekran.
4. Trzy motywy: Noir, Paper, Minimal — wraz z paletami dla narzędzia kontrastu.
5. Branding fotografa, okładka, intro, ochrona PIN-em i hasłem.
6. Podgląd galerii oczami klienta, dostępny z panelu.
7. Budżet: ≤ 60 KB JS gzip.

**Bramka wyjścia:** LCP poniżej 2,5 s na 4G przy galerii z 500 zdjęciami;
cała galeria obsługiwana z klawiatury.

**Zanim zaczniesz:** przeczytaj `CLAUDE.md`, ten plik, `docs/DECISIONS.md`,
`docs/ROADMAP.md` i ostatni wpis w `docs/SESSION-LOG.md`.
