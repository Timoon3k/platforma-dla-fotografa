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

## Sesja 3 — Warstwa danych i izolacja tenantów ✅

Deklaratywny schemat z dwiema gramatykami · 14 tabel · migracje z wersją schematu ·
`TenantRepository` z izolacją wymuszoną konstrukcyjnie · 5 repozytoriów · warstwa tenancy ·
arytmetyka dopłaty. Kierunek wizualny zmieniony na Obsidian (ADR-015).

## Sesja 4 — Storage, pipeline obrazów, kolejka ✅

```
[x] StorageProviderInterface + LocalStorage (zapis atomowy, ochrona katalogu)
[x] StoragePath — ścieżki z ULID-ów, walidacja przed obcięciem, rozdział prywatne/publiczne
[x] cykl życia plików: originals / previews / thumbs / finals / brand
[x] pipeline obrazów: GdProcessor (przetestowany) + ImagickProcessor + fabryka
[x] warianty AVIF/WebP, brak powiększania, korekta obrotu z EXIF
[x] USUWANIE EXIF I GPS z wariantów publicznych
[x] SecureToken + AccessGrant — hash w bazie, trzy niezależne powody odmowy
[x] tabela tokenów pobrania
[x] własna kolejka zadań: dzierżawa, ponawianie z backoffem, limit per tenant (ADR-016)
[x] 67 nowych testów (razem 129)

[ ] adapter S3 — świadomie odłożony do momentu, w którym będzie potrzebny (ADR-017)
[ ] wysyłanie częściami (chunked upload) — wymaga REST API, sesja 5
[ ] endpoint pobrania — wymaga routingu, sesja 5
```

**Bramka:** oryginał nie jest osiągalny publicznym adresem · 800 zdjęć przetwarza się w tle.
**Status:** pierwsza część spełniona i przetestowana. Druga ma komplet elementów
(kolejka, pipeline, magazyn), ale spięcie ich w ścieżkę wysyłania wymaga REST API z sesji 5.

## Sesja 5 — Konta, uwierzytelnianie, REST API v1 ✅ (częściowo)

```
[x] uwierzytelnianie klienta poza wp_users (ADR-003): magic link jednorazowy, sesje unieważnialne
[x] throttling: logowanie, magic link, PIN galerii, pobrania, odczyty API
[x] role i uprawnienia fotografa; fotograf nie widzi WP Admina
[x] routing poza WP Admin: /app, /k, /g/{token}, /b/{studio}, /d/{token}
[x] WYSYŁANIE ZDJĘĆ END-TO-END: fragmenty → scalenie → kolejka → warianty
[x] kontrola limitu planu i duplikatu PRZED transferem
[x] weryfikacja spójności pliku po scaleniu
[x] proces roboczy kolejki wyzwalany cronem + runner z handlerami
[x] baza kontrolerów REST: kształt odpowiedzi, mapowanie błędów, paginacja kursorowa,
    permission_callback (nigdy __return_true)
[x] kontener składający zależności w runtimie
[x] 31 nowych testów (razem 160)

[ ] konkretne endpointy REST — baza gotowa, brakuje tras
[ ] rejestracja fotografa i onboarding checklist
[ ] endpoint pobrania — elementy gotowe (SecureToken, AccessGrant, trasa /d)
```

**Bramka:** fotograf rejestruje się i widzi panel; klient wchodzi magic linkiem.
**Status:** uwierzytelnianie klienta i cała ścieżka wysyłania zdjęć działają i są
przetestowane. Rejestracja fotografa oraz widoki panelu przechodzą do sesji 6,
gdzie i tak powstaje powłoka aplikacji — budowanie formularza rejestracji przed
systemem komponentów oznaczałoby pisanie go dwa razy.

## Sesja 6 — Powłoka aplikacji: design system dashboardu 🎨

> Osobna sesja wyłącznie na fundament interfejsu aplikacji. Bez tego każdy kolejny ekran
> byłby budowany od zera i produkt rozjechałby się wizualnie na trzecim module.

```
[x] DECYZJA: bundler dla /app → ADR-018: Preact + Signals + htm jako moduły ES,
    bez bundlera i bez mapy importów. 9,9 KB gzip. Landing zostaje bez frameworka
[x] powłoka: nawigacja boczna, pasek górny, obszar treści, stany ładowania
[x] komponenty danych: tabela sortowalna, paginacja kursorowa w kliencie API,
    odwlekanie wyszukiwarki, puste stany, szkielety o strukturze docelowej tabeli
[x] komponenty akcji: dialog z pułapką fokusu, szuflada, popover, menu kontekstowe,
    toast, potwierdzenie operacji nieodwracalnej
[x] formularze: walidacja inline, stany błędu, kopia robocza formularza
[x] paleta poleceń (Cmd+K) — nawigacja i wyszukiwanie z klawiatury
[x] system powiadomień w interfejsie
[x] responsywność aplikacji: 375 px jako pełnoprawny widok, nie okrojony
[x] katalog komponentów jako strona podglądu dla dalszych sesji
[x] weryfikacja w prawdziwej przeglądarce: 18 sprawdzeń, zero błędów w konsoli
```

**Bramka:** każdy komponent ma komplet stanów, działa z klawiatury i zdaje kontrast
w trzech motywach. Katalog komponentów renderuje się bez błędów.
**Status: zdana z jednym zastrzeżeniem** — panel ma jeden motyw (Obsidian) i w nim
kontrast jest zmierzony. Trzy motywy dotyczą galerii klienta i powstają w sesji 8;
wtedy też narzędzie kontrastu dostanie ich palety.

160 testów PHP · 18/18 sprawdzeń w przeglądarce · 18/18 par kontrastu ·
13 plików JS bez błędów składni. Panel: 10,9 KB gzip kodu + 9,9 KB runtime'u
przy budżecie 120 KB.

**Filtrowanie i sortowanie tabeli liczy serwer, nie przeglądarka** — przy tysiącu
galerii ściąganie wszystkiego, żeby posortować lokalnie, jest bez sensu. Komponent
tabeli przyjmuje więc `sortKey`, `sortDirection` i `onSort`, a widok zamienia je
na parametry zapytania. Trasy REST powstają w sesji 7 razem z pierwszym widokiem,
który ich używa.

**Rejestracja fotografa i onboarding** (przeniesione z sesji 5) przechodzą do sesji 7:
formularz rejestracji jest pierwszym realnym zastosowaniem komponentu formularza
i powstanie razem z endpointem, który go obsłuży.

## Sesja 7 — Galerie w panelu fotografa 🎨

```
[x] rejestracja fotografa (konto + studio w jednym kroku) i własne logowanie
[x] panel renderowany przez wtyczkę: /app wychodzi poza motyw, powłoka z serwera
[x] trasy REST: /today, /galleries, /clients, /assets, /uploads
[x] lista galerii: filtry, wyszukiwarka i stronicowanie po stronie serwera
[x] tworzenie i edycja galerii w szufladzie, pakiet, dopłata, termin ważności
[x] wysyłanie zdjęć: drag & drop, postęp pojedynczy i całościowy, wznawianie,
    anulowanie, wykrywanie duplikatów, czytelne błędy
[x] siatka zdjęć z wirtualizacją i doczytywaniem stron
[x] widok „Dzisiaj” — jedno zapytanie zagregowane
[ ] zmiana kolejności przeciąganiem, wybór wielokrotny, okładka → sesja 8
[ ] podgląd galerii oczami klienta → sesja 8
[ ] onboarding checklist na prawdziwym stanie studia → sesja 8
```

**Bramka:** wysłanie galerii ślubnej z 800 zdjęciami nie blokuje interfejsu ani serwera.
**Status: zdana w części mierzalnej tutaj.** Siatka rysuje tylko widoczne wiersze
(przy 420 kadrach w podglądzie: kilkadziesiąt elementów zamiast wszystkich),
rezerwuje wysokość dla całości i doczytuje strony przy przewijaniu. Skrót pliku
liczy się przyrostowo, więc pamięć nie rośnie z rozmiarem RAW-a.

**Czego ta bramka NIE dowodzi:** nie ma tu WordPressa ani MySQL-a, więc
zachowanie serwera przy 800 równoległych zadaniach w kolejce pozostaje
nieprzetestowane (kwestia O9). Warstwa serwerowa ma testy na prawdziwym SQL-u,
ale nie pod obciążeniem.

**Przeniesione do sesji 8** (razem z galerią klienta, bo dotyczą tego samego
materiału): kolejność i okładka są ustawieniami tego, co zobaczy klient,
a podgląd „oczami klienta" wymaga, żeby widok klienta w ogóle istniał.

## Sesja 8 — Galeria klienta 🎨

> Persona krytyczna: telefon, 22:30, jedną ręką, czasem słaby zasięg.

```
[x] okładka ustawiana z panelu, oznaczona w siatce
[x] podgląd oczami klientki — otwiera PRAWDZIWY link, nie makietę
[x] siatka: rzędy o stałej wysokości, kolejność chronologiczna, LQIP w dokumencie
[x] lightbox: klawiatura, swipe, pełny ekran, powrót fokusu
[x] trzy motywy galerii: Noir, Paper, Minimal — z audytem kontrastu
[x] branding: nazwa studia, slot na logo, stopka
[x] okładka, intro, ochrona PIN-em z limitem prób
[x] pobieranie pojedyncze, gdy fotograf je włączył
[x] pełna obsługa z klawiatury: pominięcie, Tab, Enter, Escape
[x] budżet: 3,2 KB JS gzip przy limicie 60 KB
[ ] zmiana kolejności przeciąganiem i wybór wielokrotny → sesja 9
[ ] ZIP w tle → sesja 10 (dostawa)
[ ] View Transitions między siatką a lightboxem → odłożone, patrz niżej
```

**Bramka:** LCP poniżej 2,5 s na 4G przy galerii z 500 zdjęciami; cała galeria obsługiwana
z klawiatury.
**Status: obsługa z klawiatury zdana i zmierzona** (33 sprawdzenia w przeglądarce).
**LCP niezmierzone** — do pomiaru trzeba WordPressa, prawdziwych plików i dławienia
sieci, a tego środowiska tu nie ma (kwestia O9). Zrobione jest to, co decyduje
o LCP: pierwsze kadry są w dokumencie, mają `fetchpriority` i wymiary, a odkładanie
(`loading="lazy"`) zaczyna się dopiero od piątego.

**View Transitions odłożone świadomie.** Przejście siatka → lightbox wymaga
`view-transition-name` na obu elementach i działa dobrze dopiero wtedy, gdy
lightbox pokazuje TEN SAM plik, co kadr w siatce. U nas pokazuje większy wariant,
więc przejście i tak kończy się podmianą obrazka. Wróci razem z Selection Roomem,
gdzie ruch niesie znaczenie (licznik dopłaty), a nie samą ozdobę.

**Przeniesione do sesji 9:** zmiana kolejności przeciąganiem i wybór wielokrotny.
Oba są operacjami na zaznaczeniu, a zaznaczenie jest tematem Selection Roomu.

## Sesja 9 — Selection Room ⭐ 🎨

> Wyróżnik ②. Etap, na którym fotograf faktycznie zarabia.

```
[x] zmiana kolejności przeciąganiem i wybór wielokrotny w panelu (z sesji 8)
[x] stany zdjęcia: ulubione, wybrane, odrzucone
[x] licznik pakietu liczony na żywo, widoczny przez cały czas
[x] wyliczenie nadmiaru i kwoty dopłaty
[ ] filtrowanie wyboru, tryb porównania dwóch kadrów
[ ] komentarze klienta do zdjęcia
[x] zatwierdzenie wyboru i ponowne otwarcie przez fotografa
[x] odporność na słabą sieć: zmiana wysyłana od razu, wycofywana przy błędzie
[x] widok wyboru po stronie fotografa
[x] skrzynka „Wybory” — wszystkie wybory klientek w jednym miejscu (ponad plan)
```

**Bramka:** klientka wybiera 28 zdjęć przy pakiecie 20 i widzi kwotę dopłaty, zanim
o cokolwiek zapyta.
**Status: zdana i potwierdzona w przeglądarce** — licznik pokazuje „Wybrałaś 28 zdjęć ·
20 zdjęć w pakiecie · 8 zdjęć dodatkowo × 60 zł = 480 zł”, a ta sama kwota wychodzi
z testu PHP na `PackageTally`.

**Odłożone świadomie.** *Filtrowanie i tryb porównania* — filtr ma sens dopiero
przy wyborze w kilku rundach, a porównanie dwóch kadrów obok siebie wymaga
lightboxa dwuklatkowego; oba wracają razem z *komentarzami klientki do zdjęcia*,
bo to jedna funkcja: druga runda wyboru. *Odrzucanie kadrów* jest w modelu
(`SelectionState::Rejected`), ale nie ma przycisku — bez drugiej rundy nie ma
czego odrzucać.

**Przeciąganie działa w obrębie widocznego okna siatki.** Siatka jest
wirtualizowana, więc poza oknem nie ma elementu DOM, w który dałoby się celować.
Dalekie ruchy obsługuje zaznaczenie plus „Na początek” / „Na koniec” — i to jest
ruch, którego fotograf naprawdę potrzebuje: wybranie otwarcia galerii (ADR-027).

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
