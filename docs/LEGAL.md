# LEGAL — mapa dokumentów i podział ról

> ⚠️ **To nie jest opinia prawna.** Dokumenty przygotowywane w ramach projektu są
> profesjonalnymi draftami pod ten konkretny produkt i rynek PL/EU.
> **Nie twierdzimy, że są „w 100% zgodne z prawem”.** Każdy dokument ma oznaczone miejsca
> wymagające weryfikacji przez prawnika przed premierą.

---

## 1. Podział ról — rzecz, którą trzeba ustalić najpierw

To jest najczęściej mylony element w produktach tego typu i musi być jednoznaczny
w każdym dokumencie:

```
┌─────────────────────────────────────────────────────────────────────────┐
│ PLATFORMA (Kadr)                                                        │
│  • ADMINISTRATOR danych: konta fotografów, billing, logi systemowe      │
│  • PODMIOT PRZETWARZAJĄCY dane klientów fotografa — przetwarza je       │
│    wyłącznie na polecenie fotografa, na podstawie DPA                   │
├─────────────────────────────────────────────────────────────────────────┤
│ FOTOGRAF                                                                │
│  • ADMINISTRATOR danych swoich klientów (imię, e-mail, telefon,         │
│    wizerunek na zdjęciach, historia zamówień)                           │
│  • powierza je platformie umową powierzenia (DPA)                       │
├─────────────────────────────────────────────────────────────────────────┤
│ KLIENT KOŃCOWY                                                          │
│  • osoba, której dane dotyczą                                           │
│  • swoje prawa realizuje wobec FOTOGRAFA; platforma dostarcza narzędzia │
└─────────────────────────────────────────────────────────────────────────┘
```

**Konsekwencja produktowa:** żądanie usunięcia danych od klienta końcowego trafia do fotografa,
nie do nas. Nasz privacy center musi to poprawnie kierować — i musi dać fotografowi narzędzie
do wykonania takiego żądania jednym kliknięciem.

**Szczególny przypadek — wizerunek.** Zdjęcia to dane osobowe szczególnej wagi w praktyce
(wizerunek dziecka, sesja w domu klienta, metadane GPS). Stąd: usuwanie EXIF/GPS z podglądów
publicznych (`SECURITY.md` §3) i moduł zgód (`client_consents`).

---

## 2. Dokumenty do przygotowania

| # | Dokument | Kto adresat | Sesja |
|---|---|---|---|
| 1 | Regulamin platformy (ToS) | fotograf | 2 (draft) / 5 (pełny) |
| 2 | Polityka prywatności platformy | fotograf, odwiedzający | 2 |
| 3 | Polityka cookies | wszyscy | 2 |
| 4 | Umowa powierzenia przetwarzania (DPA) | fotograf | 5 |
| 5 | Lista subprocesorów | fotograf | 5 |
| 6 | Zasady płatności i abonamentu | fotograf | 4 |
| 7 | Zasady rezygnacji z abonamentu | fotograf | 4 |
| 8 | Acceptable Use Policy | fotograf | 5 |
| 9 | Zasady przechowywania i usuwania plików | fotograf | 5 |
| 10 | Security Information | fotograf | 5 |
| 11 | Deklaracja dostępności | wszyscy | 6 |
| 12 | Procedura reklamacyjna | fotograf, klient | 5 |
| 13 | Informacja o żądaniach dotyczących prywatności | wszyscy | 5 |
| 14 | Informacja o preferencjach cookies | wszyscy | 2 |
| 15 | **Szablon zgody na wykorzystanie wizerunku** (dla fotografa → jego klient) | klient końcowy | 5 |

Pozycja 15 nie jest dokumentem platformy, tylko **narzędziem dla fotografa** — i jest jednym
z bardziej wartościowych elementów produktu w polskich realiach. Musi być jasno oznaczona
jako wzór wymagający własnej weryfikacji przez fotografa.

---

## 3. Lista subprocesorów (do uzupełnienia w trakcie realizacji)

| Rola | Dostawca | Region | Dane |
|---|---|---|---|
| Hosting aplikacji | *do ustalenia* | EU | wszystkie |
| Object storage | *do ustalenia* | **EU** | zdjęcia, pliki |
| Poczta transakcyjna | *do ustalenia* | EU | e-mail, imię |
| Billing platformy | Stripe | EU/US (SCC) | dane rozliczeniowe fotografa |
| Płatności klientów | **operator fotografa** | — | **poza naszą kontrolą — do zaznaczenia w DPA** |
| SMS (post-MVP) | *do ustalenia* | EU | numer telefonu |

**Region EU dla object storage jest wymogiem, nie preferencją** — obiecujemy to w DPA
i wynika to z ADR-011.

Ostatni wiersz wymaga szczególnej uwagi w DPA: gdy fotograf podłącza własnego operatora
płatności (ADR-006), to on zawiera z nim relację, nie my. Musi to być napisane wprost.

---

## 4. Funkcje wymagane przez RODO (moduł Privacy Center, Session 5)

```
[ ] eksport danych użytkownika (fotografa i klienta) — format maszynowy
[ ] usunięcie danych — z rozróżnieniem: soft delete vs twarde usunięcie na żądanie
[ ] polityka retencji z automatycznym egzekwowaniem
[ ] wycofanie zgody — z natychmiastowym skutkiem
[ ] historia zgód z dowodem (kiedy, na co, w jaki sposób)
[ ] rejestr żądań dotyczących prywatności, z terminami
[ ] minimalizacja danych — nie zapisujemy tego, czego nie potrzebujemy
[ ] anonimizacja IP w logach i statystykach
```

---

## 5. Cookies

Kategorie: **niezbędne** · **analityka** · **marketing** · **preferencje**.

- Skrypty wymagające zgody **nie mogą wykonać się przed jej udzieleniem**. Nie „ładują się i czekają”
  — nie ładują się w ogóle.
- Domyślnie zaznaczone są wyłącznie cookies niezbędne.
- Odmowa musi być tak samo łatwa jak zgoda — jedno kliknięcie, ta sama waga wizualna przycisku.
- Zgoda jest wersjonowana i zapisywana z datą.
- Własny, lekki komponent albo adapter dla zewnętrznego CMP — bez ciężkiej zewnętrznej biblioteki.

---

## 6. Analityka bez naruszania prywatności

Fotograf chce wiedzieć, czy klient otworzył galerię — i to jest uzasadniona potrzeba biznesowa.
Ale:

```
✅ liczba otwarć galerii, liczba wyświetleń zdjęcia, konwersja wyboru na zamówienie
❌ śledzenie klienta między galeriami różnych fotografów
❌ profilowanie behawioralne
❌ przekazywanie danych klientów do zewnętrznych sieci reklamowych
❌ fingerprinting
```

IP zanonimizowane. Identyfikator odwiedzającego jest zakresowany do konkretnej galerii
i wygasa razem z nią.

---

## 7. Czego nie wolno napisać w dokumentach

```
❌ „w pełni zgodny z RODO”          ❌ „gwarantujemy bezpieczeństwo danych”
❌ „100% zgodności z prawem”        ❌ „zatwierdzone przez prawnika” (dopóki nie jest)
```

Zamiast tego: opis faktycznych mechanizmów i jawne oznaczenie miejsc do weryfikacji.

---

## 8. Przed premierą — obowiązkowo

```
[ ] weryfikacja wszystkich dokumentów przez prawnika specjalizującego się w IT/RODO
[ ] uzupełnienie listy subprocesorów o rzeczywistych dostawców
[ ] potwierdzenie regionu przetwarzania każdego subprocesora
[ ] weryfikacja obowiązków wobec konsumentów (prawo odstąpienia, reklamacje)
[ ] weryfikacja zasad rozliczeń i faktur dla polskich jednoosobowych działalności
[ ] sprawdzenie nazwy produktu w EUIPO/UPRP (PROJECT_STATE.md, kwestia O1)
```
