# PROJECT_STATE — Kadr

**Wersja:** 0.3.0
**Ostatnia aktualizacja:** 2026-09-12 (Session 3/15)
**Branch:** `claude/premium-photography-saas-u8xl6y`

---

## Gdzie jesteśmy

Session 3/15 zamknięta. **Plan rozszerzony z 6 do 15 sesji** — pięć z nich (6–10)
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
| Narzędzia: testy, spójność bloków, kontrast, PSR-4, podgląd, RAR, ZIP | ✅ `tools/` |

**62 testy · 9/9 bloków · 18/18 par kontrastu · 48 plików PSR-4 · zero zależności produkcyjnych.**

## Czego nie ma

- wysyłania zdjęć i pipeline'u obrazów — sesja 4
- interfejsu aplikacji, galerii klienta, Selection Room — sesje 5–10
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

---

## Znane problemy i otwarte kwestie

| # | Kwestia | Kiedy |
|---|---|---|
| O1 | **Nazwa „Kadr” niezweryfikowana** w EUIPO/UPRP i u rejestratora domen. Zmiana jest jeszcze tania — po sesji 5 dotyka namespace'u, prefiksu tabel i migracji | przed sesją 5 |
| O2 | Wybór operatora płatności (PayNow / Przelewy24 / Autopay) | sesja 12 |
| O3 | Dostawca object storage w EU i realny koszt transferu | sesja 4 |
| O5 | Dokumenty prawne wymagają weryfikacji przez prawnika | przed premierą |
| O6 | Integracja z fakturowaniem PL — poza MVP, ale fotograf zapyta | post-MVP |
| O7 | **Bundler dla `/app`** — ADR-013 celowo tego nie rozstrzygnął. Kandydat: Preact + Signals, budowanie tylko dla `/app` | sesja 6 |
| O8 | Pliki fontów (Bricolage Grotesque, Geist) — licencje OFL, zostaje osadzenie `woff2` | sesja 6 |
| O9 | Audyt w prawdziwej instalacji WordPressa — to środowisko nie ma dostępu do wordpress.org (403) ani serwera MySQL. Zastępczo działa harness `tools/preview.php` | gdy będzie dostępne środowisko |

*(O4 — licencje fontów — zamknięta: wszystkie trzy kroje są na OFL.)*

## Następny logiczny krok

**SESJA 4/15 — storage, pipeline obrazów, kolejka zadań.**

1. `StorageProviderInterface` + `LocalStorage` + szkielet S3, ścieżki i cykl życia plików.
2. Pipeline obrazów: metadane → warianty AVIF/WebP → miniatura → znak wodny,
   usuwanie EXIF i GPS z podglądów publicznych.
3. `QueueInterface` + Action Scheduler + limit współbieżności per tenant.
4. Podpisane URL-e, tokeny pobrania, kontrolowany endpoint.
5. Testy dostępu do plików: bezpośredni URL, wygasły token, cudzy token.

**Bramka wyjścia:** oryginał nie jest osiągalny żadnym publicznym adresem,
a 800 zdjęć przetwarza się w tle bez blokowania żądania.

**Zanim zaczniesz:** przeczytaj `CLAUDE.md`, ten plik, `docs/DECISIONS.md`,
`docs/ROADMAP.md` i ostatni wpis w `docs/SESSION-LOG.md`.
