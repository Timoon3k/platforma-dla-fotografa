---
name: security-privacy
description: Reguły bezpieczeństwa i prywatności dla Kadr. Użyj przed pisaniem kodu dotykającego uwierzytelniania, uprawnień, endpointów REST, uploadu, plików, tokenów, płatności, webhooków, danych osobowych lub sekretów.
---

# Security & Privacy — Kadr

> Przechowujemy prywatne zdjęcia cudzych rodzin. Nie „pliki użytkownika”.

Pełny model zagrożeń: `docs/SECURITY.md`.

## 1. Dziesięć reguł, które zawsze obowiązują

```
1.  permission_callback przy każdym endpoincie. __return_true tylko dla publicznej treści.
2.  $wpdb->prepare() zawsze. Zero interpolacji w SQL.
3.  Escaping na każdym wyjściu.
4.  Walidacja I sanityzacja każdego wejścia.
5.  Prywatne zdjęcia nigdy pod przewidywalnym publicznym URL-em.
6.  Tokeny: random_bytes, w bazie HASH, z TTL, unieważnialne.
7.  Sekrety zaszyfrowane, nigdy w repo, nigdy w logach, nigdy w API.
8.  Webhooki: podpis → tolerancja czasowa → UNIQUE → kolejka.
9.  Cudzy zasób = 404, nigdy 403.
10. Nigdy nie przechowujemy danych kart.
```

## 2. Izolacja tenantów — rzecz najważniejsza

`TenantContext` wstrzyknięty do repozytorium. Nie istnieje `findAny()` ani `$ignoreTenant = true`.

**Dodając endpoint REST, dodajesz test do `tests/TenantIsolation/`** — tenant A próbuje sięgnąć
po zasób tenanta B. Brak testu = niezaliczony build.

## 3. Upload — nigdy nie ufaj klientowi

```
1. Rozszerzenie z allowlisty (nie blocklisty)
2. Rzeczywisty MIME z zawartości (finfo), nie z nagłówka
3. Weryfikacja, że plik da się zdekodować
4. Nazwa pliku generowana przez nas — nazwa od klienta tylko do kolumny opisowej
5. Limity rozmiaru
6. Sprawdzenie entitlementu storage PRZED zapisem
7. Katalog bez prawa wykonywania
```

## 4. Prywatność zdjęć

- **Usuwaj EXIF/GPS z publicznych podglądów.** Zdjęcie z sesji newborn z geolokalizacją domu
  klienta to realne zagrożenie dla tej rodziny, nie teoria. Oryginał zachowuje metadane.
- Oryginały nie są serwowane nigdy — ani fotografowi w przeglądarce, ani klientowi.
- Identyfikator odwiedzającego zakresowany do jednej galerii, wygasa razem z nią.
- Zero śledzenia klienta między galeriami różnych fotografów.

## 5. Logger — czego nigdy nie zapisujemy

```
✖ hasła            ✖ pełne sekrety API      ✖ numery kart
✖ tokeny           ✖ pełne ładunki webhooków ✖ treść zdjęć
✖ pełne IP (anonimizuj)
```

Zapisujemy: zdarzenia systemowe, błędy webhooków, niepowodzenia zadań w tle,
błędy e-maili i storage'u.

## 6. Audit log — kto, co, kiedy

Obowiązkowy dla: publikacji i usunięcia galerii, zaproszenia i usunięcia klienta,
zmiany statusu zamówienia, płatności, wygenerowania i użycia tokenu pobrania,
zmian uprawnień zespołu, zmiany kluczy płatności, impersonacji przez administratora.

## 7. RODO — podział ról (mylony najczęściej)

```
Platforma  → administrator danych fotografów; PODMIOT PRZETWARZAJĄCY dane ich klientów
Fotograf   → ADMINISTRATOR danych swoich klientów
Klient     → osoba, której dane dotyczą; prawa realizuje wobec FOTOGRAFA
```

Żądanie usunięcia od klienta końcowego kierujemy do fotografa i dajemy mu narzędzie
do jego wykonania. Nie realizujemy go za niego samodzielnie.

## 8. Zanim uznasz kod za skończony

```
[ ] permission_callback sprawdzony?
[ ] każde zapytanie przez prepare()?
[ ] każde wyjście zescapowane?
[ ] tenant wymuszony w repozytorium?
[ ] test izolacji dodany?
[ ] token ma TTL i da się go unieważnić?
[ ] sekret nie trafia do logu ani do odpowiedzi API?
[ ] komunikat błędu nie zdradza szczegółów technicznych?
[ ] operacja trafia do audit logu, jeśli powinna?
```
