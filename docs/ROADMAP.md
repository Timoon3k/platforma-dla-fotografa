# ROADMAP — Kadr

---

## Zasada zakresu

Funkcja wchodzi do v1.0 tylko wtedy, gdy spełnia **co najmniej jeden** warunek:
1. obsługuje etap **wybór · dopłata · dostawa · odbitki** z customer journey (to jest cała
   wartość ekonomiczna produktu),
2. jest wymagana prawnie,
3. bez niej produktu nie da się sprzedać ani uruchomić.

Wszystko inne jest post-MVP. Bez wyjątków — największym ryzykiem tego projektu
jest rozrost zakresu (ryzyko R13).

---

## SESSION 1/6 — Product discovery + architektura + kierunek wizualny ✅

```
[x] wizja produktu, persony, customer journey
[x] model biznesowy, plany, free tier
[x] 12 pomysłów na wyróżnik + wybór TOP 3
[x] warianty architektoniczne + decyzja
[x] model danych i ERD
[x] architektura storage
[x] architektura płatności (rozdzielenie dwóch domen)
[x] 3 kierunki wizualne + decyzja
[x] architektura informacji
[x] zakres MVP i post-MVP
[x] rejestr ryzyk
[x] dokumentacja projektu (13 plików)
[x] 8 skills
[x] szkielet katalogów
```

**Wyróżniki wybrane do implementacji:** ① Client Journey · ② Selection Room · ③ Odsłona.

---

## SESSION 2/6 — Design system + strona marketingowa + Gutenberg

```
[ ] composer.json i package.json — uzasadnienie KAŻDEJ pozycji (bramka akceptacji)
[ ] kadr.php — bootstrap ≤ 100 linii, sprawdzanie wymagań, autoload PSR-4, kontener
[ ] design tokens jako warstwa CSS custom properties (semantyczne)
[ ] skala typograficzna, self-hosting fontów, fallbacki z size-adjust
[ ] komponenty bazowe: button, input, card, table, dialog, toast, empty state, badge
[ ] 12 bloków Gutenberga (block.json + render PHP + Interactivity API)
[ ] strona główna wg kolejności sekcji z DESIGN-SYSTEM.md §8
[ ] cennik z przełącznikiem miesięcznie/rocznie (czyta rejestr planów, nie hardcode)
[ ] 4 podstrony funkcji
[ ] dokumenty prawne jako CPT z szablonem
[ ] cookie consent (własny, lekki — kategorie: niezbędne, analityka, marketing, preferencje)
[ ] rejestracja fotografa (formularz + walidacja + throttling)
[ ] audyt Lighthouse przed zamknięciem sesji — budżety z PERFORMANCE.md
```

**Bramka wyjścia:** landing spełnia budżety CWV na mobile, administrator edytuje każdą sekcję
w Gutenbergu bez możliwości zepsucia układu.

---

## SESSION 3/6 — Rdzeń SaaS

```
[ ] migracje + wersja schematu + instalator
[ ] tabele: tenancy, clients, galleries, assets, selections, access, audit
[ ] TenantContext + repozytoria z wymuszoną izolacją
[ ] role, capabilities, zespół
[ ] REST API v1 — galerie, zdjęcia, wybór, klienci
[ ] StorageProviderInterface + LocalStorage + S3Storage
[ ] pipeline obrazów: warianty, AVIF/WebP, znak wodny, usuwanie EXIF
[ ] kolejka (QueueInterface + Action Scheduler) + limit współbieżności per tenant
[ ] upload chunked z wznawianiem, postępem, deduplikacją
[ ] dashboard fotografa: Dzisiaj, Klienci, Galerie
[ ] onboarding checklist (nie formularz na 30 pól)
[ ] galeria klienta: siatka, lightbox, klawiatura, swipe
[ ] Selection Room ⭐ — ulubione, wybór, odrzucenie, komentarze, licznik pakietu
[ ] Client Journey ⭐ — oś statusów widoczna dla obu stron
[ ] portal klienta
[ ] uwierzytelnianie klienta (magic link + opcjonalne hasło)
[ ] testy izolacji tenantów jako bramka CI
[ ] test galerii z 800 zdjęciami na 4G
```

**Bramka wyjścia:** pełna ścieżka „fotograf tworzy galerię → wysyła → klient wybiera → fotograf
widzi wybór” działa end-to-end na telefonie.

---

## SESSION 4/6 — Commerce + subskrypcje + zamówienia

```
[ ] tabele: products, variants, orders, payments, payment_events, subscriptions, usage
[ ] elastyczny model opcji i wariantów (format, papier, wykończenie) — bez hardcode'u
[ ] cena dodatkowego zdjęcia, bundle 5/10, „kup wszystkie”
[ ] Print Room w galerii — z podglądem kadru w formacie (2:3 vs 4:3 obcina głowy)
[ ] koszyk i checkout — jedna kolumna na mobile, BLIK pierwszy
[ ] PaymentGatewayInterface + FakeGateway + pierwszy adapter produkcyjny
[ ] webhooki: podpis, tolerancja czasowa, idempotencja, maszyna stanów, retry
[ ] rejestr planów i entitlementów (zero if ($plan === …))
[ ] free tier: 5 projektów, read-only po wyczerpaniu, brak utraty danych
[ ] Stripe Billing: abonament, dodatki, faktury, pauza, anulowanie
[ ] billing UX w dashboardzie
[ ] feature flags
[ ] testy webhooków: powtórzenie, zły podpis, stary timestamp
```

**Bramka wyjścia:** klient wybiera 28 zdjęć przy pakiecie 20, widzi kwotę dopłaty,
płaci BLIK-iem, fotograf widzi opłacone zamówienie.

---

## SESSION 5/6 — Booking + CRM + automatyzacje + dostawa + RODO

```
[ ] usługi, dostępność, bufory, blackout dates
[ ] strona rezerwacji /b/{studio}
[ ] rezerwacja + zadatek (przez moduł Commerce)
[ ] kalendarz fotografa (miesiąc/tydzień), wykrywanie kolizji
[ ] CRM: karta klienta, historia, notatki, zgody
[ ] powiadomienia e-mail (6 typów z briefu) + szablony edytowalne per tenant
[ ] automatyzacje: model zdarzenie → warunek → akcja
[ ] dostawa: pliki finalne, tokeny pobrania, limity, ZIP w tle
[ ] Odsłona ⭐ — premiera gotowych zdjęć
[ ] Privacy center: eksport, usunięcie, retencja, historia zgód
[ ] dokumenty prawne — pełne drafty (LEGAL.md)
[ ] wyszukiwarka w dashboardzie (debounce + indeksy)
```

**Bramka wyjścia:** klient rezerwuje sesję online z zadatkiem; pełny cykl od rezerwacji
do pobrania gotowych zdjęć działa bez ręcznej interwencji.

---

## SESSION 6/6 — Hardening + wydajność + QA + release

> **Session 6 nie jest miejscem na dopisywanie funkcji.**

```
[ ] pełny przegląd bezpieczeństwa wg checklisty z SECURITY.md §12
[ ] testy izolacji tenantów dla każdego endpointu
[ ] testy dostępu do plików
[ ] testy uprawnień dla każdej capability
[ ] audyt Core Web Vitals wszystkich powierzchni
[ ] audyt dostępności WCAG 2.2 AA (w trzech motywach galerii)
[ ] testy E2E pięciu krytycznych ścieżek (poniżej)
[ ] QA mobilne na realnych urządzeniach
[ ] edge case'y i stany błędów
[ ] test na czystej instalacji WordPressa
[ ] test aktywacji / deaktywacji / odinstalowania
[ ] test migracji i aktualizacji z wersji wcześniejszej
[ ] scenariusz backup / restore
[ ] test obciążeniowy
[ ] dokumentacja: README, instalacja, przewodnik admina, przewodnik fotografa,
    dokumentacja deweloperska, API, baza danych, prywatność, changelog
[ ] instalowalny ZIP — DOPIERO NA POLECENIE
```

### Krytyczne ścieżki E2E
```
J1  fotograf rejestruje się → tworzy galerię → wgrywa zdjęcia → wysyła klientowi
J2  klient otwiera galerię → wybiera zdjęcia → zatwierdza wybór
J3  klient wybiera ponad limit → powstaje zamówienie → płaci
J4  fotograf dodaje gotowe zdjęcia → klient pobiera
J5  klient rezerwuje sesję i wpłaca zadatek
```

---

## Post-MVP

| Faza | Zakres |
|---|---|
| **1.1** | Consent & Usage Vault · własna domena · pełny white label · drugi adapter płatności |
| **1.2** | automatyzacje warunkowe · SMS · Studio Board (tablica produkcji) · zaawansowane role zespołu |
| **1.3** | Google Calendar · publiczne API + webhooki · integracja z fakturowaniem PL · laboratoria druku |
| **2.0** | Revenue Assistant (po zebraniu danych) · własne motywy galerii · wersja EN · dark mode dashboardu |

**Wymaga osobnej decyzji:** marketplace / split payments — STOP, osobny dokument
architektoniczno-prawny (ADR-006, `CLAUDE.md` §2).

---

## Definicja ukończenia v1.0

Produkt nie jest gotowy, dopóki nie przejdą **wszystkie**:

```
[ ] QA funkcjonalne            [ ] QA mobilne
[ ] QA dostępności             [ ] przegląd bezpieczeństwa
[ ] testy izolacji tenantów    [ ] testy płatności
[ ] testy dostępu do plików    [ ] audyt wydajności
[ ] audyt Core Web Vitals      [ ] test czystej instalacji
[ ] test aktywacji/deaktywacji [ ] test migracji
[ ] test aktualizacji          [ ] scenariusz backup/restore
[ ] E2E ścieżki fotografa      [ ] E2E ścieżki klienta
```
