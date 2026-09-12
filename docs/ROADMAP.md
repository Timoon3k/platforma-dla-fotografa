# ROADMAP — Kadr

> **Plan rozszerzony z 6 do 15 sesji** (decyzja właściciela produktu, Session 3).
> Powód: sześć sesji wymuszało kompromisy tam, gdzie produkt ma być najmocniejszy —
> w interfejsie aplikacji. Pięć sesji (6–10) jest teraz poświęconych wyłącznie frontendowi.
>
> Wpisy w `docs/SESSION-LOG.md` sprzed tej zmiany używają numeracji „N/6” — numery sesji
> 1, 2 i 3 są te same, zmienia się tylko to, co następuje po nich.

---

## Zasada zakresu

Funkcja wchodzi do v1.0 tylko wtedy, gdy spełnia **co najmniej jeden** warunek:
1. obsługuje etap **wybór · dopłata · dostawa · odbitki** z customer journey,
2. jest wymagana prawnie,
3. bez niej produktu nie da się sprzedać ani uruchomić.

Dłuższy plan **nie oznacza szerszego zakresu** — oznacza głębsze wykonanie tego samego zakresu.
Największym ryzykiem projektu pozostaje rozrost zakresu (R13), a piętnaście sesji zwiększa
to ryzyko, nie zmniejsza.

---

## Mapa faz

```
FAZA I    FUNDAMENT              sesje 1–4    ██████████░░░░░░░░░░░░░░░░░░░░
FAZA II   APLIKACJA I FRONTEND   sesje 5–10   ░░░░░░░░░░████████████████░░░░
FAZA III  PIENIĄDZE              sesje 11–13  ░░░░░░░░░░░░░░░░░░░░░░░░░░████
FAZA IV   OPERACJE I WYDANIE     sesje 14–15  ░░░░░░░░░░░░░░░░░░░░░░░░░░░░██
```

---

# FAZA I — FUNDAMENT

## Sesja 1 — Strategia, architektura, kierunek ✅

Wizja, persony, customer journey, model biznesowy, dwanaście pomysłów na wyróżnik i wybór
TOP 3, warianty architektoniczne, model danych, storage, płatności, trzy kierunki wizualne,
zakres MVP, rejestr ryzyk. Dwanaście ADR-ów, osiem skills, trzynaście dokumentów.

## Sesja 2 — Design system i strona marketingowa ✅

Bootstrap wtyczki, warstwa domenowa rozliczeń, tokeny, dziewięć bloków Gutenberga,
cennik czytający rejestr planów, moduł zgód na cookies, wzorce bloków.
Zero zależności produkcyjnych.

## Sesja 3 — Warstwa danych i izolacja tenantów 🔵 *(w toku)*

```
[x] deklaratywny schemat tabel z dwiema gramatykami (MySQL produkcyjnie, SQLite w testach)
[x] czternaście tabel rdzenia: tenancy, klienci, galerie, zdjęcia, wybory, audyt
[x] prymitywy domenowe: ULID, Clock, Result, Money
[x] warstwa tenancy: TenantContext, Capability, Role
[x] kontrakt Database + adaptery wpdb i PDO
[x] TenantRepository — zapytanie bez tenanta niemożliwe do napisania
[x] kierunek wizualny Obsidian + warstwa ruchu (ADR-015)
[ ] repozytoria: klienci, galerie, zdjęcia, wybory
[ ] runner migracji + wersja schematu + instalator
[ ] testy izolacji tenantów na prawdziwym silniku SQL
[ ] logger, audit log, szyfrowanie sekretów
```

**Bramka:** fotograf A nie sięgnie po dane fotografa B żadną ścieżką — udowodnione testem.

## Sesja 4 — Storage, pipeline obrazów, kolejka

```
[ ] StorageProviderInterface + LocalStorage + szkielet S3
[ ] ścieżki i cykl życia plików: originals / previews / thumbs / finals
[ ] pipeline: metadane → warianty AVIF/WebP → miniatura → znak wodny
[ ] usuwanie EXIF i GPS z podglądów publicznych
[ ] deduplikacja po SHA-256 w obrębie tenanta
[ ] QueueInterface + Action Scheduler + limit współbieżności per tenant
[ ] podpisane URL-e i kontrolowany endpoint pobrania
[ ] testy dostępu do plików: bezpośredni URL, wygasły token, cudzy token
```

**Bramka:** oryginał nie jest osiągalny żadnym publicznym adresem; 800 zdjęć przetwarza się
w tle bez blokowania żądania.

---

# FAZA II — APLIKACJA I FRONTEND

> Sześć sesji na interfejs. To jest ta część, w której produkt wygrywa albo przegrywa —
> fotograf spędzi w nim setki godzin rocznie, a jego klientka podejmie decyzję zakupową
> w pierwszych trzech sekundach na telefonie.

## Sesja 5 — Konta, uwierzytelnianie, REST API v1

```
[ ] rejestracja fotografa, tenant, właściciel, zespół
[ ] uwierzytelnianie klienta poza wp_users (ADR-003): magic link + opcjonalne hasło
[ ] throttling logowania, magic linku i PIN-u galerii
[ ] routing: /app, /k, /g/{token}, /b/{studio} poza WP Admin
[ ] REST API v1 — konwencje, paginacja kursorowa, kształt błędów
[ ] permission_callback dla każdego endpointu + testy uprawnień
[ ] onboarding checklist z widocznym postępem
```

**Bramka:** fotograf rejestruje się i widzi swój panel; klient wchodzi magic linkiem
i nigdy nie widzi WP Admina.

## Sesja 6 — Powłoka aplikacji: design system dashboardu 🎨

> Osobna sesja wyłącznie na fundament interfejsu aplikacji. Bez tego każdy kolejny ekran
> byłby budowany od zera i produkt rozjechałby się wizualnie na trzecim module.

```
[ ] DECYZJA: bundler dla /app (ADR-013 celowo tego nie rozstrzygnął)
    kandydat: Preact + Signals ~5 KB, budowanie tylko dla /app, landing zostaje bez budowania
[ ] powłoka: nawigacja boczna, pasek górny, obszar treści, stany ładowania
[ ] komponenty danych: tabela sortowalna i filtrowalna, paginacja kursorowa,
    wyszukiwarka z debounce, puste stany, szkielety
[ ] komponenty akcji: dialog z pułapką fokusu, szuflada, popover, menu kontekstowe,
    toast, potwierdzenie operacji nieodwracalnej
[ ] formularze: walidacja inline, stany błędu, autozapis roboczy
[ ] paleta poleceń (Cmd+K) — nawigacja i wyszukiwanie z klawiatury
[ ] system powiadomień w interfejsie
[ ] responsywność aplikacji: 375 px jako pełnoprawny widok, nie okrojony
[ ] katalog komponentów jako strona podglądu dla dalszych sesji
```

**Bramka:** każdy komponent ma komplet stanów, działa z klawiatury i zdaje kontrast
w trzech motywach. Katalog komponentów renderuje się bez błędów.

## Sesja 7 — Galerie w panelu fotografa 🎨

```
[ ] lista galerii: filtry, sortowanie, wyszukiwarka, akcje masowe
[ ] tworzenie i edycja galerii, ustawienia dostępu, termin ważności
[ ] wysyłanie zdjęć: drag & drop, postęp pojedynczy i całościowy, wznawianie,
    anulowanie, wykrywanie duplikatów, czytelne błędy
[ ] siatka zdjęć z wirtualizacją — 1500 kadrów bez zacinania
[ ] zmiana kolejności przeciąganiem, wybór wielokrotny, okładka
[ ] podgląd galerii oczami klienta
[ ] widok „Dzisiaj” — jedno zapytanie zagregowane
```

**Bramka:** wysłanie galerii ślubnej z 800 zdjęciami nie blokuje interfejsu ani serwera.

## Sesja 8 — Galeria klienta 🎨

> Persona krytyczna: telefon, 22:30, jedną ręką, czasem słaby zasięg.

```
[ ] siatka mozaikowa z leniwym doładowywaniem i LQIP
[ ] lightbox: klawiatura, swipe, gesty, zoom, pełny ekran
[ ] View Transitions między siatką a lightboxem
[ ] trzy motywy galerii: Noir, Paper, Minimal
[ ] branding fotografa: logo, kolor, stopka
[ ] okładka, intro, ochrona PIN-em i hasłem
[ ] pobieranie pojedyncze i ZIP w tle
[ ] pełna obsługa z klawiatury i czytnika ekranu
[ ] budżet: ≤ 60 KB JS gzip
```

**Bramka:** LCP poniżej 2,5 s na 4G przy galerii z 500 zdjęciami; cała galeria obsługiwana
z klawiatury.

## Sesja 9 — Selection Room ⭐ 🎨

> Wyróżnik ②. Etap, na którym fotograf faktycznie zarabia.

```
[ ] stany zdjęcia: ulubione, wybrane, odrzucone
[ ] licznik pakietu liczony na żywo, widoczny przez cały czas
[ ] wyliczenie nadmiaru i kwoty dopłaty
[ ] filtrowanie wyboru, tryb porównania dwóch kadrów
[ ] komentarze klienta do zdjęcia
[ ] zatwierdzenie wyboru i ponowne otwarcie przez fotografa
[ ] odporność na słabą sieć: kolejkowanie zmian, wznowienie po zerwaniu
[ ] widok wyboru po stronie fotografa
```

**Bramka:** klientka wybiera 28 zdjęć przy pakiecie 20 i widzi kwotę dopłaty, zanim
o cokolwiek zapyta.

## Sesja 10 — Client Journey ⭐ i portal klienta 🎨

> Wyróżnik ①. Odpowiedź na pytanie „kiedy będą zdjęcia?”, zanim ktokolwiek je zada.

```
[ ] oś procesu: jedenaście etapów, widok klienta i widok fotografa
[ ] automatyczne przejścia statusów wywoływane zdarzeniami domenowymi
[ ] terminy gotowości i sygnalizowanie opóźnień
[ ] portal klienta: sesje, galerie, wybory, zamówienia, pliki, terminy, zgody
[ ] tablica produkcji dla fotografa: co jest w obróbce i u kogo
[ ] historia komunikacji przy kliencie
```

**Bramka:** klient w każdej chwili wie, na jakim etapie jest jego sesja, bez pytania.

---

# FAZA III — PIENIĄDZE

## Sesja 11 — Produkty, warianty, Print Room ⭐

```
[ ] elastyczny model opcji i wariantów — formaty i papiery nie są zakodowane na sztywno
[ ] typy produktów: odbitka, powiększenie, album, fotoobraz, produkt własny
[ ] cena zdjęcia ponad pakiet, pakiety 5 i 10, „kup wszystkie”
[ ] Print Room w galerii z PODGLĄDEM KADROWANIA dla każdego formatu
[ ] rekomendacje formatu, progi darmowej wysyłki
[ ] cenniki i rabaty czasowe
```

**Dlaczego podgląd kadrowania.** Zdjęcie 3:2 w formacie 13×18 zostanie przycięte. Klient,
który tego nie zobaczył, złoży reklamację u fotografa — nie u nas.

## Sesja 12 — Koszyk, checkout, płatności

```
[ ] koszyk i checkout w jednej kolumnie na telefonie
[ ] dane do wysyłki, metody dostawy
[ ] PaymentGatewayInterface + adapter testowy
[ ] pierwszy adapter produkcyjny z BLIK-iem (PayNow albo Przelewy24 — decyzja tej sesji)
[ ] webhooki: podpis, tolerancja czasowa, idempotencja przez klucz unikalny, kolejka
[ ] statusy zamówienia, zwroty, korekty
[ ] szyfrowanie kluczy API fotografa
[ ] testy webhooków: powtórzenie, zły podpis, stary znacznik czasu
```

**Bramka:** płatność BLIK-iem kończy się opłaconym zamówieniem, a powtórzony webhook
nie realizuje go dwa razy.

## Sesja 13 — Abonamenty, entitlementy, panel platformy

```
[ ] Stripe Billing: abonament, dodatki, faktury, proration
[ ] egzekwowanie entitlementów w każdym punkcie zapisu
[ ] liczniki zużycia przyrostowe + nocne przeliczanie
[ ] free tier: pięć projektów, przejście w tryb tylko do odczytu bez utraty danych
[ ] pauza konta jako mechanizm anty-churnowy
[ ] billing UX: plan, zużycie, faktury, zmiana, anulowanie w dwóch kliknięciach
[ ] panel platformy: MRR, churn, konwersja, KOSZT STORAGE PER TENANT VS PRZYCHÓD
[ ] feature flags, health, kolejka, nieudane webhooki
```

**Bramka:** przekroczenie limitu nigdy nie usuwa danych i nigdy nie wyłącza galerii,
za którą klient końcowy już zapłacił.

---

# FAZA IV — OPERACJE I WYDANIE

## Sesja 14 — Booking, kalendarz, CRM, automatyzacje 🎨

```
[ ] usługi: czas trwania, cena, zadatek, lokalizacja, bufory przed i po
[ ] dostępność, wyjątki, blackout dates
[ ] strona rezerwacji /b/{studio} + zadatek
[ ] kalendarz fotografa: miesiąc i tydzień, wykrywanie kolizji, przeciąganie terminów
[ ] adapter kalendarza zewnętrznego (szkielet pod Google Calendar)
[ ] CRM: karta klienta, historia, notatki, zgody, nadchodzące terminy
[ ] powiadomienia e-mail + szablony edytowalne per tenant
[ ] automatyzacje: zdarzenie → warunek → akcja
```

**Bramka:** system nie zarezerwuje dwóch sesji bez bufora między nimi.

## Sesja 15 — Odsłona ⭐, RODO, hardening, wydanie

```
[ ] dostawa plików finalnych, tokeny pobrania, limity, ZIP w tle
[ ] ODSŁONA — premiera gotowych zdjęć zamiast linku do archiwum
[ ] ponowna rezerwacja jednym kliknięciem, automat rocznicowy
[ ] privacy center: eksport, usunięcie, retencja, historia zgód
[ ] pozostałe dokumenty prawne
[ ] pełny przegląd bezpieczeństwa wg checklisty
[ ] audyt Core Web Vitals wszystkich powierzchni
[ ] audyt WCAG 2.2 AA w trzech motywach
[ ] testy E2E pięciu krytycznych ścieżek
[ ] czysta instalacja, migracje, aktualizacja, backup i odtworzenie
[ ] dokumentacja: instalacja, admin, fotograf, deweloper, API, changelog
[ ] pakiet wydania
```

---

## Krytyczne ścieżki E2E

```
J1  fotograf rejestruje się → tworzy galerię → wysyła zdjęcia → wysyła klientowi
J2  klient otwiera galerię → wybiera zdjęcia → zatwierdza wybór
J3  klient wybiera ponad limit → powstaje zamówienie → płaci
J4  fotograf dodaje gotowe zdjęcia → klient pobiera
J5  klient rezerwuje sesję i wpłaca zadatek
```

## Post-MVP

| Faza | Zakres |
|---|---|
| 1.1 | Consent & Usage Vault · pełny white label · drugi adapter płatności |
| 1.2 | SMS · zaawansowane role zespołu · automatyzacje warunkowe |
| 1.3 | Google Calendar · publiczne API · fakturowanie PL · laboratoria druku |
| 2.0 | Revenue Assistant · własne motywy galerii · wersja EN · aplikacja mobilna |

**Wymaga osobnej decyzji:** marketplace / split payments — STOP i osobny dokument.

## Definicja ukończenia v1.0

```
[ ] QA funkcjonalne            [ ] QA mobilne
[ ] QA dostępności             [ ] przegląd bezpieczeństwa
[ ] testy izolacji tenantów    [ ] testy płatności
[ ] testy dostępu do plików    [ ] audyt wydajności
[ ] audyt Core Web Vitals      [ ] test czystej instalacji
[ ] test aktywacji             [ ] test migracji
[ ] test aktualizacji          [ ] backup i odtworzenie
[ ] E2E ścieżki fotografa      [ ] E2E ścieżki klienta
```
