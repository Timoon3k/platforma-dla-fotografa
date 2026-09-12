---
name: product-strategy
description: Ramy decyzji produktowych dla Kadr — komu to służy, co wchodzi do zakresu, jak oceniać nowe pomysły. Użyj przy planowaniu sesji, przy propozycji nowej funkcji, przy rozstrzyganiu priorytetów i zawsze, gdy pojawia się pokusa rozszerzenia zakresu.
---

# Product Strategy — Kadr

## 1. Wizja w jednym zdaniu

> System operacyjny relacji z klientem dla fotografa — prowadzi całą współpracę od pierwszego
> zapytania do ponownej rezerwacji i po drodze sam sprzedaje to, czego fotograf normalnie
> nie zdąży sprzedać.

Produkt sprzedaje **odzyskany czas** i **przychód, który dziś wyparowuje**. W tej kolejności.

## 2. Non-goals

Nie jesteśmy: edytorem zdjęć · kreatorem stron portfolio · systemem księgowym ·
marketplace'em kojarzącym klientów z fotografami · pośrednikiem finansowym ·
konkurencją dla CRM klasy enterprise.

## 3. Test wejścia do zakresu v1.0

Funkcja wchodzi, jeśli spełnia **co najmniej jeden** warunek:
1. obsługuje etap **wybór · dopłata · dostawa · odbitki**,
2. jest wymagana prawnie,
3. bez niej produktu nie da się sprzedać ani uruchomić.

Nie spełnia żadnego → post-MVP. Bez negocjacji.
**Największym ryzykiem tego projektu jest rozrost zakresu, nie brak funkcji.**

## 4. Pięć pytań przed każdą funkcją

```
1. Czy profesjonalny fotograf rzeczywiście tego potrzebuje?
2. Czy zrozumie to bez instrukcji?
3. Czy wygląda jak produkt premium?
4. Czy wytrzyma 1000 fotografów bez przepisywania połowy aplikacji?
5. Czy nie dokładamy złożoności bez realnej wartości?
```
Jedno „nie” → zatrzymaj się i zaproponuj coś lepszego.

## 5. Ocena nowego pomysłu

Punktuj 1–5 w pięciu wymiarach i porównaj z istniejącym backlogiem:

| Wymiar | Pytanie |
|---|---|
| Wartość dla klienta | czy fotograf zauważy różnicę w tygodniu pracy? |
| Koszt wytworzenia | *(im wyżej, tym gorzej)* |
| Potencjał przychodu powtarzalnego | czy uzasadnia plan wyżej albo dodatek? |
| Wyróżnik | czy konkurencja może to skopiować w miesiąc? |
| Retencja | czy fotograf trudniej odejdzie, jeśli to ma? |

## 6. Wybrane wyróżniki

```
① Client Journey     widoczna oś współpracy dla fotografa i klienta
② Selection Room     wybór z licznikiem pakietu i kasą w tym samym widoku
③ Odsłona            dostawa gotowych zdjęć jako wydarzenie, nie link do ZIP-a
```

Dlaczego te trzy: są **jedną opowieścią**, nie trzema funkcjami. Są nieskopiowalne punktowo —
żeby je odtworzyć, trzeba mieć booking, galerię, zamówienia i produkcję w jednym systemie.
② robi fotografowi pieniądze bezpośrednio. ② i ③ widzi klient końcowy, czyli **nasz jedyny
darmowy kanał akwizycji**.

Kolejny w kolejce (post-MVP): **Consent & Usage Vault** — tani, specyficznie europejski,
konkurencja zza oceanu strukturalnie go nie ma.

Świadomie odłożony: **Revenue Assistant** — wymaga danych historycznych, których w dniu premiery
nie mamy. Zgadujący asystent niszczy zaufanie do całego produktu.

## 7. Persony — skrót

| | Kto | Co decyduje |
|---|---|---|
| P1 | Ola, fotografka rodzinna, solo | persona podstawowa — dla niej projektujemy domyślnie |
| P2 | Marek, ślubny premium | white label, custom domain, ogromny storage |
| P3 | Studio Lumen, 3–5 osób | role, seaty, tablica produkcji — najwyższy LTV |
| P4 | Kasia, klientka | **persona krytyczna** — decyduje o sukcesie produktu |
| P5 | Administrator platformy | koszt vs przychód per tenant |

Szczegóły: log Session 1 w `docs/SESSION-LOG.md`.

## 8. Zasady biznesowe

- Free tier oparty na **projektach, nie na czasie** — cykl sesji trwa 2–6 tygodni,
  trial 14-dniowy strukturalnie nie działa w tej branży.
- **Storage jest jedynym istotnie zmiennym kosztem** — każda decyzja o wariantach,
  retencji i limitach jest decyzją o marży.
- Platforma **nie dotyka pieniędzy klienta końcowego** (ADR-006).
- **Zero dark patterns.** Żadnych liczników odliczających, blokad pobierania opłaconych zdjęć,
  cichego wygaszania danych, anulowania przez e-mail do wsparcia.
