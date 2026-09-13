# PROJECT_STATE — Kadr

**Wersja:** 0.10.0
**Ostatnia aktualizacja:** 2026-09-13 (Sesja 10/15)
**Branch:** `claude/premium-photography-saas-u8xl6y`

---

## Gdzie jesteśmy

Sesja 10/15 zamknięta. **Produkt domyka pierwszy i trzeci z czterech etapów,
które są całą jego wartością ekonomiczną** (CLAUDE.md §1): klientka wybiera
zdjęcia i widzi kwotę dopłaty, zanim cokolwiek zatwierdzi — a potem dostaje
pliki, bez Dysku Google i bez linku, który wygasa przed pobraniem.

Bramka sesji 9: 28 zdjęć przy pakiecie 20 i cenie 60 zł daje **480 zł**,
policzone przez serwer i pokazane klientce, zanim kliknie „wysyłam".

Bramka sesji 10: paczka wesela **pakuje się w tle porcjami i przeżywa
przerwanie**, a pobieranie wznawia się po zerwanym połączeniu.

Czego brakuje do sprzedaży: **zapłaty za dopłatę** (sesje 12–13) i odbitek
(sesja 11). Kwota jest policzona i widoczna, pliki gotowe — zapłacić
jeszcze nie ma jak.

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
| **Wybór zdjęć przez klientkę**: ulubione ≠ wybrane, licznik pakietu na żywo | ✅ `Application\Selection\SelectionRoom` (ADR-025) |
| Kwota dopłaty widoczna, zanim klientka cokolwiek zatwierdzi | ✅ licznik przyklejony do dołu ekranu |
| Liczby do rozliczenia liczone przez serwer, zamrażane przy zatwierdzeniu | ✅ ADR-026 |
| Zatwierdzenie wyboru i ponowne otwarcie przez fotografa | ✅ `SelectionRoom::submit/reopen` |
| Panel wyboru w widoku galerii fotografa | ✅ `views/selection.js` |
| **Skrzynka „Wybory”**: kto wybrał, kto wybiera, ile czeka dopłat | ✅ `Application\Selection\SelectionInbox` |
| Zaznaczanie wielu kadrów (Shift — zakres) i zbiorcze usuwanie | ✅ `views/arrange.js` |
| Układanie kolejności: przeciąganie oraz „Na początek” / „Na koniec” | ✅ `ArrangeGallery` (ADR-027) |
| Poprawne polskie formy liczby mnogiej — trzy, nie dwie | ✅ `Support\Plural`, `runtime.js` (ADR-028) |
| **Paczka ZIP w tle**: porcjami, z wznawianiem po przerwaniu | ✅ `Application\Delivery\PackGalleryArchive` (ADR-029) |
| Pakowanie bez kompresji, plik z dysku zamiast z pamięci | ✅ `Infrastructure\Delivery\ZipPacker` |
| Pobranie przez `/d/{token}` z obsługą `Range` (wznawianie) | ✅ `Presentation\Client\DownloadPage` |
| Tokeny pobrania: hash w bazie, doba życia, unieważnianie hurtem | ✅ ADR-031 |
| `finals` to oryginał wydany tokenem, nie kolejny wariant | ✅ ADR-030 |
| Panel dostawy: postęp „340 z 1200”, link, powiadomienie klientki | ✅ `views/delivery.js` |
| Galeria klientki: sekcja pobierania mówiąca wprost o telefonie | ✅ `GalleryMarkup::delivery` |
| Warstwa mailowa: interfejs w Domain, adapter `wp_mail` | ✅ `Domain\Notification`, `Infrastructure\Mail` |
| Wiadomość „Twoje zdjęcia są gotowe" — link do galerii, nie do paczki | ✅ ADR-032 |
| **Dziennik zdarzeń**: kto wydał link do plików i kto ich użył | ✅ `AuditLogRepository`, IP tylko jako hash |
| Wyszukiwanie tabel po nazwie zamiast po pozycji w tablicy | ✅ `Tables::byName()` |
| Weryfikacja panelu w prawdziwej przeglądarce | ✅ `tools/check-panel.mjs`, 92 sprawdzenia |
| Weryfikacja galerii klienta w prawdziwej przeglądarce | ✅ `tools/check-gallery.mjs`, 51 sprawdzeń |
| **Runner testów wykrywa własne urwanie** — `exit` w ładowanym pliku | ✅ `tools/run-tests.php` |
| Narzędzia: testy, spójność bloków, kontrast, PSR-4, podgląd, RAR, ZIP | ✅ `tools/` |

**295 testów PHP · 143 sprawdzenia w przeglądarce (92 panel + 51 galeria) · 9/9 bloków ·
51 par kontrastu (18 panel + 33 w trzech motywach galerii) · 132 pliki PSR-4 ·
27 plików JS bez błędów składni.**

Budżety: galeria klienta **5,4 KB JS gzip** przy limicie 60 KB, arkusz galerii
5,5 KB gzip; panel 34,5 KB + 9,7 KB bibliotek, `app.css` 10,6 KB gzip.
Landing bez zmian.

## Czego nie ma

- adaptera S3 — świadomie odłożony (ADR-017); MVP działa na dysku lokalnym
- odrzucania kadrów — `rejected` jest w modelu, nie ma przycisku; wejdzie
  z wyborem w kilku rundach i komentarzami do zdjęć
- powiadomienia mailem o zatwierdzeniu wyboru — sesja 10
- **zapłaty za dopłatę** — kwota jest policzona i widoczna, zapłacić nie ma jak
- sprzątania wygasłych paczek — `ArchiveRepository::expired()` gotowe,
  brakuje zadania cyklicznego (sesja 11)
- powiadomienia fotografa o zatwierdzonym wyborze — warstwa mailowa stoi,
  brakuje wyzwalacza (sesja 11)
- portalu klienta `/k` — trasa istnieje, renderera nie ma (sesja 11)
- **przetestowanej ścieżki dla magazynu zdalnego** — pakowanie przez strumień
  i plik tymczasowy jest napisane, ale nie ma na czym go sprawdzić
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
| 025 | Ulubione i wybrane to dwa osobne stany — serduszko jest darmowe |
| 026 | Liczby do rozliczenia liczy serwer; zamrażamy je przy zatwierdzeniu |
| 027 | Żądanie zmiany kolejności opisuje zamiar, nie gotową listę |
| 028 | Polskie formy liczby mnogiej poza `_n()` — trzy formy, nie dwie |
| 029 | Paczka powstaje w tle porcjami i da się ją przerwać w połowie |
| 030 | `finals` to oryginał wydany tokenem, a nie kolejny wariant w magazynie |
| 031 | Token pobrania bez limitu użyć, za to żyjący dobę i unieważnialny |
| 032 | Wiadomość do klientki prowadzi do galerii, nie do wygasającej paczki |

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

**SESJA 11/15 — Client Journey i portal klienta.**

> Przeniesiona z sesji 10, która poszła na dostawę plików — bo dostawa jest
> jednym z czterech etapów wymienionych w CLAUDE.md §1, a Client Journey nie.

1. Oś procesu: jedenaście etapów, widok klienta i widok fotografa.
2. Automatyczne przejścia statusów wywoływane zdarzeniami domenowymi.
3. Portal klienta `/k`: sesje, galerie, wybory, pliki, terminy, zgody.
4. Tablica produkcji dla fotografa: co jest w obróbce i u kogo.
5. Trzy rzeczy dopisane z sesji 10: sprzątanie wygasłych paczek,
   powiadomienie fotografa o zatwierdzonym wyborze, wysyłanie logo studia
   (kwestia O14).

**Bramka wyjścia:** klient w każdej chwili wie, na jakim etapie jest jego
sesja, bez pytania fotografa.

**Zanim zaczniesz:** przeczytaj `CLAUDE.md`, ten plik, `docs/DECISIONS.md`,
`docs/ROADMAP.md` i ostatni wpis w `docs/SESSION-LOG.md`.
