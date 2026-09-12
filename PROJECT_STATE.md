# PROJECT_STATE — Kadr

**Wersja:** 0.5.0
**Ostatnia aktualizacja:** 2026-09-12 (Sesja 5/15)
**Branch:** `claude/premium-photography-saas-u8xl6y`

---

## Gdzie jesteśmy

Sesja 5/15 zamknięta. **Plan rozszerzony z 6 do 15 sesji** — pięć z nich (6–10)
jest poświęconych wyłącznie frontendowi aplikacji.

Wtyczka jest instalowalna i działa od razu po wgraniu: zakłada własne tabele,
serwuje stronę marketingową i ma kompletną, przetestowaną warstwę izolacji danych.
Nie ma jeszcze interfejsu aplikacji — to zakres sesji 5–10.

---

## Co działa

| Obszar | Stan |
|---|---|
| Bootstrap, wymagania, autoload PSR-4 bez Composera | ✅ `kadr.php`, 80 linii |
| Migracje z wersją schematu, uruchamiane przy aktywacji i aktualizacji | ✅ `MigrationRunner` |
| Czternaście tabel rdzenia | ✅ `Schema\Tables` |
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
| Narzędzia: testy, spójność bloków, kontrast, PSR-4, podgląd, RAR, ZIP | ✅ `tools/` |

**160 testów · 9/9 bloków · 18/18 par kontrastu · 83 pliki PSR-4 · zero zależności produkcyjnych.**

## Czego nie ma

- adaptera S3 — świadomie odłożony (ADR-017); MVP działa na dysku lokalnym
- konkretnych endpointów REST — baza gotowa, brakuje tras
- rejestracji fotografa i onboardingu — przeniesione do sesji 6
- interfejsu aplikacji, galerii klienta, Selection Room — sesje 6–10
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

---

## Znane problemy i otwarte kwestie

| # | Kwestia | Kiedy |
|---|---|---|
| O1 | **Nazwa „Kadr” niezweryfikowana** w EUIPO/UPRP i u rejestratora domen. Zmiana jest jeszcze tania — po sesji 5 dotyka namespace'u, prefiksu tabel i migracji | przed sesją 5 |
| O2 | Wybór operatora płatności (PayNow / Przelewy24 / Autopay) | sesja 12 |
| O3 | Dostawca object storage w EU, koszt transferu i adapter S3 (ADR-017) | gdy pojawi się realny wolumen |
| O5 | Dokumenty prawne wymagają weryfikacji przez prawnika | przed premierą |
| O6 | Integracja z fakturowaniem PL — poza MVP, ale fotograf zapyta | post-MVP |
| O7 | **Bundler dla `/app`** — ADR-013 celowo tego nie rozstrzygnął. Kandydat: Preact + Signals, budowanie tylko dla `/app` | sesja 6 |
| O8 | Pliki fontów (Bricolage Grotesque, Geist) — licencje OFL, zostaje osadzenie `woff2` | sesja 6 |
| O9 | Audyt w prawdziwej instalacji WordPressa — to środowisko nie ma dostępu do wordpress.org (403) ani serwera MySQL. Zastępczo działa harness `tools/preview.php` | gdy będzie dostępne środowisko |

*(O4 — licencje fontów — zamknięta: wszystkie trzy kroje są na OFL.)*

## Następny logiczny krok

**SESJA 6/15 — powłoka aplikacji: design system dashboardu.**

1. **Decyzja do podjęcia na starcie:** bundler dla `/app` (kwestia O7).
   ADR-013 celowo tego nie rozstrzygnął. Kandydat: Preact + Signals,
   budowanie tylko dla `/app`, landing zostaje bez budowania.
2. Powłoka: nawigacja, pasek górny, obszar treści, stany ładowania.
3. Komponenty danych: tabela sortowalna i filtrowalna, paginacja kursorowa,
   wyszukiwarka z debounce, puste stany, szkielety.
4. Komponenty akcji: dialog z pułapką fokusu, szuflada, toast,
   potwierdzenie operacji nieodwracalnej.
5. Paleta poleceń (Cmd+K).
6. **Rejestracja fotografa i onboarding checklist** (przeniesione z sesji 5 —
   formularz przed systemem komponentów trzeba by pisać dwa razy).
7. Konkretne endpointy REST pod te widoki.
8. Katalog komponentów jako strona podglądu dla dalszych sesji.

**Bramka wyjścia:** każdy komponent ma komplet stanów, działa z klawiatury
i zdaje kontrast w trzech motywach.

**Zanim zaczniesz:** przeczytaj `CLAUDE.md`, ten plik, `docs/DECISIONS.md`,
`docs/ROADMAP.md` i ostatni wpis w `docs/SESSION-LOG.md`.
