# PROJECT_STATE — Kadr

**Wersja:** 0.6.0
**Ostatnia aktualizacja:** 2026-09-13 (Sesja 6/15)
**Branch:** `claude/premium-photography-saas-u8xl6y`

---

## Gdzie jesteśmy

Sesja 6/15 zamknięta. Pięć sesji (6–10) jest poświęconych frontendowi aplikacji;
ta pierwsza zbudowała fundament, na którym stanie każdy kolejny ekran.

Wtyczka jest instalowalna i działa od razu po wgraniu: zakłada własne tabele,
serwuje stronę marketingową i ma kompletną, przetestowaną warstwę izolacji danych.
**Panel fotografa ma powłokę i komplet komponentów** — nawigację, tabelę, dialogi,
szufladę, formularze, paletę poleceń i powiadomienia. Nie ma jeszcze widoków
z prawdziwymi danymi: trasy REST i pierwsze ekrany to sesja 7.

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
| Weryfikacja panelu w prawdziwej przeglądarce | ✅ `tools/check-panel.mjs`, 18 sprawdzeń |
| Narzędzia: testy, spójność bloków, kontrast, PSR-4, podgląd, RAR, ZIP | ✅ `tools/` |

**160 testów PHP · 18/18 sprawdzeń w przeglądarce · 9/9 bloków · 18/18 par kontrastu ·
83 pliki PSR-4 · 13 plików JS bez błędów składni.**

Budżety: panel 10,9 KB gzip kodu + 9,9 KB runtime'u (limit 120 KB), arkusz panelu
5,4 KB gzip. Landing bez zmian: 3,8 KB JS, zero Preacta.

## Czego nie ma

- adaptera S3 — świadomie odłożony (ADR-017); MVP działa na dysku lokalnym
- konkretnych endpointów REST — baza i klient gotowe, brakuje tras (sesja 7)
- rejestracji fotografa i onboardingu — przeniesione do sesji 7, razem z endpointem
- widoków panelu z prawdziwymi danymi, galerii klienta, Selection Room — sesje 7–10
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
| O9 | Audyt w prawdziwej instalacji WordPressa — to środowisko nie ma dostępu do wordpress.org (403) ani serwera MySQL. Zastępczo działa harness `tools/preview.php` | gdy będzie dostępne środowisko |

*(O4 — licencje fontów — zamknięta: wszystkie trzy kroje są na OFL.
O7 — bundler dla `/app` — zamknięta przez ADR-018: bundlera nie ma.)*

## Następny logiczny krok

**SESJA 7/15 — galerie w panelu fotografa.**

1. **Rejestracja fotografa i onboarding checklist** (przeniesione z sesji 5 i 6) —
   pierwszy realny użytkownik komponentu formularza, razem z endpointem.
2. Pierwsze trasy REST: galerie, klienci, podsumowanie widoku „Dzisiaj".
   Kontrakt odpowiedzi i paginacja kursorowa są już ustalone po obu stronach.
3. Lista galerii: filtry, sortowanie po stronie serwera, wyszukiwarka, akcje masowe.
4. Tworzenie i edycja galerii w szufladzie, ustawienia dostępu, termin ważności.
5. Wysyłanie zdjęć w interfejsie: drag & drop, postęp, wznawianie, duplikaty.
   Warstwa serwerowa działa od sesji 5 — brakuje widoku.
6. Siatka zdjęć z wirtualizacją: 1500 kadrów bez zacinania.
7. Widok „Dzisiaj" na jednym zapytaniu zagregowanym.

**Bramka wyjścia:** wysłanie galerii ślubnej z 800 zdjęciami nie blokuje
interfejsu ani serwera.

**Zanim zaczniesz:** przeczytaj `CLAUDE.md`, ten plik, `docs/DECISIONS.md`,
`docs/ROADMAP.md` i ostatni wpis w `docs/SESSION-LOG.md`.
