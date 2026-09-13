# PROJECT_STATE — Kadr

**Wersja:** 0.8.0
**Ostatnia aktualizacja:** 2026-09-13 (Sesja 8/15)
**Branch:** `claude/premium-photography-saas-u8xl6y`

---

## Gdzie jesteśmy

Sesja 8/15 zamknięta. **Obie strony produktu są kompletne w kodzie**:
fotograf zakłada studio, tworzy galerię, wysyła zdjęcia i generuje link —
a klientka otwiera ten link na telefonie i ogląda zdjęcia w jednym z trzech
motywów, z lightboxem, PIN-em i pobieraniem.

Czego brakuje do sprzedaży: **wyboru zdjęć i dopłaty** (sesja 9 — to jest
moment, w którym produkt zarabia), dostawy (sesja 10) i płatności (11–13).

**Uwaga — czego nie wiemy:** to środowisko nie ma WordPressa ani MySQL-a,
więc żadna z tych ścieżek nie została przejechana w prawdziwej instalacji
(kwestia O9). Warstwa serwerowa ma testy na prawdziwym SQL-u, a panel,
formularze i galeria — w prawdziwej przeglądarce. Styku z WordPressem
nikt jeszcze nie sprawdził. **LCP galerii jest niezmierzone** — do pomiaru
trzeba prawdziwych plików i dławienia sieci.

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
| **Galeria klienta `/g/{token}`** — pierwszy ekran z serwera, kadry w dokumencie | ✅ `Presentation\Client` (ADR-022) |
| Układ: rzędy o stałej wysokości, kolejność chronologiczna, kadr nieprzycięty | ✅ ADR-023 |
| Trzy motywy: Noir, Paper, Minimal — kontrast audytowany w każdym | ✅ `assets/css/gallery.css` |
| Lightbox: klawiatura, swipe, pełny ekran, powrót fokusu | ✅ 3,2 KB gzip |
| Miniatura LQIP wpisana w dokument, generowana w pipelinie | ✅ migracja 0003 |
| Linki dla klientki: token 256 bitów, hash w bazie, PIN, unieważnianie | ✅ `ShareGallery` |
| Jedno wyjście poza tenanta, po globalnie unikalnym hashu tokenu | ✅ `GalleryLookup` (ADR-024) |
| Okładka galerii i podgląd oczami klientki z panelu | ✅ `views/share.js` |
| Pobieranie pojedynczego zdjęcia, gdy fotograf je włączył | ✅ `/g/{token}/d/{zdjęcie}` |
| Weryfikacja panelu w prawdziwej przeglądarce | ✅ `tools/check-panel.mjs`, 56 sprawdzeń |
| Weryfikacja galerii klienta w prawdziwej przeglądarce | ✅ `tools/check-gallery.mjs`, 33 sprawdzenia |
| Narzędzia: testy, spójność bloków, kontrast, PSR-4, podgląd, RAR, ZIP | ✅ `tools/` |

**212 testów PHP · 89 sprawdzeń w przeglądarce (56 panel + 33 galeria) · 9/9 bloków ·
51 par kontrastu (18 panel + 33 w trzech motywach galerii) · 109 plików PSR-4 ·
23 pliki JS bez błędów składni.**

Budżety: galeria klienta **3,2 KB JS gzip** przy limicie 60 KB, arkusz galerii
3,3 KB gzip. Panel i landing bez zmian.

## Czego nie ma

- adaptera S3 — świadomie odłożony (ADR-017); MVP działa na dysku lokalnym
- **wyboru zdjęć i dopłaty** — sesja 9, moment, w którym produkt zarabia
- zmiany kolejności przeciąganiem i wyboru wielokrotnego — sesja 9
- ZIP-a w tle i dostawy plików — sesja 10
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
| 022 | Galeria klienta renderowana przez serwer, bez frameworka — 3,2 KB JS |
| 023 | Układ galerii: rzędy o stałej wysokości, nie kolumny (kolejność ma znaczenie) |
| 024 | Jedno wyjście poza tenanta dla publicznego linku (`GalleryLookup`) |

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
| O11 | Reset hasła fotografa nie ma własnego ekranu — prowadzi przez `wp-login.php` | sesja 8 |
| O12 | Zachowanie kolejki przy 800 zadaniach naraz nieprzetestowane — brak MySQL-a i WordPressa w tym środowisku | gdy będzie środowisko |
| O13 | LCP galerii klienta niezmierzone — wymaga prawdziwych plików i dławienia sieci | gdy będzie środowisko |
| O14 | Logo studia ma slot w galerii, ale nie ma jeszcze wysyłania — pole jest puste | sesja 10 |
| O9 | Audyt w prawdziwej instalacji WordPressa — to środowisko nie ma dostępu do wordpress.org (403) ani serwera MySQL. Zastępczo działa harness `tools/preview.php` | gdy będzie dostępne środowisko |

*(O4 — licencje fontów — zamknięta: wszystkie trzy kroje są na OFL.
O7 — bundler dla `/app` — zamknięta przez ADR-018: bundlera nie ma.
O10 — palety motywów galerii — zamknięta: trzy motywy mają palety,
a `tools/check-contrast.php` audytuje każdą z nich.)*

## Następny logiczny krok

**SESJA 9/15 — Selection Room.**

> Wyróżnik ②. To jest etap, na którym fotograf faktycznie zarabia —
> i jedyny, którego brak sprawia, że produktu nie da się jeszcze sprzedać.

1. Stany zdjęcia: ulubione, wybrane, odrzucone.
2. **Licznik pakietu liczony na żywo, widoczny przez cały czas** — to on
   tłumaczy klientce, dlaczego ma dopłacić, zanim ktokolwiek o tym napisze.
3. Wyliczenie nadmiaru i kwoty dopłaty (`PackageTally` czeka od sesji 2).
4. Zatwierdzenie wyboru i możliwość ponownego otwarcia przez fotografa —
   klientka zawsze się rozmyśli.
5. Zmiana kolejności przeciąganiem i wybór wielokrotny w panelu
   (przeniesione z sesji 8 — to operacje na zaznaczeniu).

**Bramka wyjścia:** klientka wybiera 28 zdjęć przy pakiecie 20 i widzi kwotę
dopłaty, zanim cokolwiek zatwierdzi.

**Zanim zaczniesz:** przeczytaj `CLAUDE.md`, ten plik, `docs/DECISIONS.md`,
`docs/ROADMAP.md` i ostatni wpis w `docs/SESSION-LOG.md`.
