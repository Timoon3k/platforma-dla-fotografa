# PROJECT_STATE — Kadr

**Wersja:** 0.2.0
**Ostatnia aktualizacja:** 2026-09-12 (Session 2/6)
**Branch:** `claude/premium-photography-saas-u8xl6y`

---

## Gdzie jesteśmy

Session 2/6 zamknięta. Istnieje **działająca wtyczka WordPress** — instalowalna, aktywowalna
i używalna zaraz po rozpakowaniu, bez `composer install` i bez `npm run build`.
Zawiera warstwę marketingową: design system, dziewięć bloków Gutenberga, cennik czytający
rejestr planów i własny moduł zgód na cookies.

Nie ma jeszcze żadnej tabeli w bazie ani żadnej funkcji SaaS — to zakres Session 3.

---

## Co działa

| Obszar | Stan |
|---|---|
| Bootstrap wtyczki, sprawdzanie wymagań, autoload PSR-4 bez Composera | ✅ `kadr.php` (80 linii) |
| Aktywacja / deaktywacja / odinstalowanie bez utraty danych | ✅ `Activation`, `uninstall.php` |
| Design tokens (semantyczne) + 3 motywy galerii | ✅ `assets/css/tokens.css` |
| Komponenty bazowe | ✅ `assets/css/components.css` |
| 9 bloków Gutenberga, renderowanie serwerowe | ✅ `blocks/` |
| Warstwa edytora bez kroku budowania | ✅ `assets/js/editor.js` (ADR-013) |
| Wzorce: strona główna, szkic polityki cookies | ✅ `Patterns.php` |
| Rejestr planów i entitlementy | ✅ `src/Domain/Billing/` |
| Cennik czytający rejestr planów | ✅ `blocks/pricing/` |
| Zgody na cookies | ✅ `Consent.php` + `consent.js` (1,9 KB gzip) |
| CPT dokumentów prawnych | ✅ `ContentTypes.php` |
| Testy warstwy Domain | ✅ 20 testów, wszystkie zdane (ADR-014) |
| Walidator spójności bloków | ✅ `tools/check-blocks.php` |
| Pakowanie checkpointu do RAR | ✅ `tools/package.sh` |

**Budżety zasobów:** CSS 6,1 KB gzip (limit 25 KB) · JS 2,1 KB gzip (limit 30 KB).
**Zależności produkcyjne: zero.**

## Czego nie ma

- tabel w bazie, tenantów, galerii, zdjęć, zamówień — Session 3 i 4
- plików fontów (na razie stosy zastępcze) — czeka na kwestię O4
- Regulaminu i Polityki prywatności — szkice do napisania
- formularza rejestracji fotografa — przeniesiony do Session 3, bo wymaga tabeli `tenants`
- audytu Lighthouse — wymaga uruchomionej instalacji WordPressa

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

---

## Znane problemy i otwarte kwestie

| # | Kwestia | Kiedy rozstrzygamy |
|---|---|---|
| O1 | **Nazwa „Kadr” niezweryfikowana** w EUIPO/UPRP i u rejestratora domen. Zmiana jest tania teraz, kosztowna po Session 3 | przed Session 3 |
| O2 | Wybór konkretnego operatora płatności (PayNow vs Przelewy24 vs Autopay) — potrzebne porównanie prowizji i warunków dla jednoosobowych DG | Session 4 |
| O3 | Dostawca object storage (region EU) i realny koszt transferu | Session 3 |
| O4 | Licencje fontów: Fraunces (OFL) i General Sans (Fontshare) — potwierdzić warunki komercyjne i self-hosting | Session 2 |
| O5 | Dokumenty prawne wymagają weryfikacji przez prawnika — draft ≠ zgodność | przed premierą |
| O6 | Integracja z fakturowaniem PL (Fakturownia/wFirma) — poza MVP, ale fotograf o to zapyta | post-MVP |

---

## Następny logiczny krok

**SESSION 3/6 — rdzeń SaaS.**

Pierwsze zadania Session 3, w tej kolejności:
1. Uruchomienie instalacji WordPressa i audyt Lighthouse strony marketingowej
   (jedyna niedokończona bramka wyjścia Session 2).
2. System migracji z wersją schematu + tabele tenancy i klientów.
3. `TenantContext` i repozytoria z wymuszoną konstrukcyjnie izolacją + testy izolacji w CI.
4. Rejestracja fotografa i onboarding checklist (przeniesione z Session 2).
5. `StorageProviderInterface` + LocalStorage + pipeline obrazów w kolejce.
6. Galerie, upload chunked, Selection Room, Client Journey.

**Decyzja do podjęcia na starcie Session 3:** czy dashboard `/app` dostaje bundler
(ADR-013 celowo nie rozstrzyga tego dla warstwy aplikacyjnej).

**Zanim zaczniesz:** przeczytaj `CLAUDE.md`, ten plik, `docs/DECISIONS.md`,
`docs/ROADMAP.md` i ostatni wpis w `docs/SESSION-LOG.md`.
