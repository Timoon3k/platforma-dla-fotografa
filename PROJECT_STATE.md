# PROJECT_STATE — Kadr

**Wersja:** 0.1.0-discovery
**Ostatnia aktualizacja:** 2026-09-12 (Session 1/6)
**Branch:** `claude/premium-photography-saas-u8xl6y`

---

## Gdzie jesteśmy

Session 1/6 zakończona. Repozytorium zawiera **wyłącznie dokumentację, skills i szkielet katalogów**.
Nie istnieje jeszcze żaden kod produkcyjny, żadna zależność ani żadna tabela w bazie.

---

## Co już działa

| Obszar | Stan |
|---|---|
| Strategia produktu, persony, journey, model biznesowy | ✅ zapisane w `docs/` i w logu Session 1 |
| Architektura techniczna (warstwy, routing, multi-tenancy) | ✅ `docs/ARCHITECTURE.md` |
| Model danych (37 tabel, ERD, indeksy) | ✅ `docs/DATABASE.md` — **projekt, nie implementacja** |
| Model bezpieczeństwa | ✅ `docs/SECURITY.md` |
| Budżety wydajności | ✅ `docs/PERFORMANCE.md` |
| Design system i kierunek wizualny | ✅ `docs/DESIGN-SYSTEM.md` — **specyfikacja, nie kod** |
| Model planów i entitlementów | ✅ `docs/BILLING.md` |
| Konwencje REST API | ✅ `docs/API.md` |
| Mapa dokumentów prawnych | ✅ `docs/LEGAL.md` |
| 12 ADR-ów | ✅ `docs/DECISIONS.md` |
| 8 skills | ✅ `.claude/skills/` |

## Co jest częściowo zrobione

Nic. Session 1 nie zostawia niedokończonych elementów — jest w całości dokumentacyjna.

## Czego nie ma (stan oczekiwany na tym etapie)

- `composer.json`, `package.json`, `plugin.php` — świadomie odłożone do Session 2
- jakikolwiek kod PHP / JS / CSS
- migracje i tabele
- bloki Gutenberga
- testy

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

**SESSION 2/6 — Design system + strona marketingowa + bloki Gutenberga.**

Pierwsze zadania Session 2, w tej kolejności:
1. Propozycja `composer.json` i `package.json` z uzasadnieniem **każdej** pozycji (bramka akceptacji).
2. Szkielet wtyczki: `kadr.php` (bootstrap ≤ 100 linii), autoload PSR-4, kontener, sprawdzanie wymagań.
3. Design tokens jako warstwa CSS custom properties (semantyczne, nie dosłowne) + skala typograficzna.
4. Biblioteka komponentów bazowych (przycisk, pole, karta, tabela, dialog, toast, empty state).
5. Bloki Gutenberga (`block.json`, render PHP, Interactivity API tam, gdzie potrzebna interakcja).
6. Strona główna wg kolejności sekcji z `docs/DESIGN-SYSTEM.md`.
7. Cennik, dokumenty prawne, cookie consent.
8. Audyt Core Web Vitals przed zamknięciem sesji.

**Zanim zaczniesz Session 2:** przeczytaj `CLAUDE.md`, ten plik, `docs/DECISIONS.md`,
`docs/ROADMAP.md` i ostatni wpis w `docs/SESSION-LOG.md`.
