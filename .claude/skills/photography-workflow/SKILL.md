---
name: photography-workflow
description: Wiedza domenowa o tym, jak realnie pracuje fotograf i czego oczekuje jego klient. Użyj przy projektowaniu lub implementacji galerii, wyboru zdjęć, proofingu, dopłat, odbitek, dostawy, rezerwacji i komunikacji z klientem.
---

# Photography Workflow — domena produktu

## 1. Jak wygląda jedna sesja dzisiaj (bez naszego produktu)

```
zapytanie w DM na Instagramie
→ ustalanie terminu w wiadomościach
→ zadatek BLIK-iem („przelałam, wysyłam potwierdzenie”)
→ sesja
→ podglądy przez WeTransfer (link wygasa po 7 dniach, klientka pisze po 10)
→ WYBÓR ZDJĘĆ W WIADOMOŚCIACH:
  „te z drugiego rzędu, to gdzie Zosia się śmieje i to w niebieskiej sukience,
   ale bez tego trzeciego”
→ ręczne odszukiwanie plików
→ obróbka
→ Dysk Google
→ „a mogę dokupić jeszcze dwa?” → kolejny przelew → kolejny link
```

**2–4 godziny administracji na jedną sesję, rozłożone na 20 przerwań.**
To jest problem, który rozwiązujemy. Każda funkcja ma się do niego odnosić.

## 2. Cztery etapy, które są całą wartością ekonomiczną

```
① WYBÓR ZDJĘĆ      największy ból operacyjny
② DOPŁATA          tu wyparowuje przychód (fotograf „dorzuca gratis”, bo prosić niezręcznie)
③ DOSTAWA          moment, który generuje polecenia
④ ODBITKI          dziś nie sprzedaje się ich wcale
```

Funkcja, która nie wspiera żadnego z tych etapów i nie jest wymagana prawnie,
**nie wchodzi do v1.0**.

## 3. Klient końcowy — realny kontekst użycia

```
telefon · wieczorem 21:30–23:00 · jedną ręką · dziecko śpi obok · czasem słaby zasięg
oczekiwania ukształtowane przez Allegro i Netflixa, nie przez software fotograficzny
```

**Nie zrobi:** nie założy konta z hasłem · nie zainstaluje aplikacji · nie otworzy ZIP-a
na telefonie · nie przeczyta instrukcji.

**Zrobi chętnie:** kliknie link z maila/SMS-a · poda 4-cyfrowy PIN · przesunie palcem ·
kliknie serduszko · zapłaci BLIK-iem w 20 sekund.

**Dlatego:** magic link zamiast hasła · mobile first naprawdę, nie jako wersja okrojona ·
BLIK jako pierwsza metoda płatności · pobieranie bez ZIP-a na telefonie.

## 4. Proofing — jak to ma działać

```
Stany zdjęcia:  favorite · selected · rejected · (bez stanu)
```

Licznik pakietu musi być widoczny przez cały czas i liczyć na żywo:

```
Pakiet obejmuje 20 zdjęć
Wybrałaś 28
────────────────────────
20 w pakiecie
 8 dodatkowych × 60 zł = 480 zł
```

Klient płaci **od razu, w tym samym widoku**. Każde przejście do innego kanału
(e-mail, przelew, rozmowa) zabija konwersję.

Fotograf musi móc **otworzyć wybór ponownie** — klientka zawsze się rozmyśli.

## 5. Odbitki — szczegół, który decyduje o zwrotach

**Proporcje.** Zdjęcie 3:2 wydrukowane w formacie 10×15 (3:2) jest pełne, ale w 13×18 (≈1,38:1)
zostanie przycięte. Klient, który nie zobaczył podglądu kadru, dostanie odbitkę z obciętą głową
i złoży reklamację — u fotografa, nie u nas.

**Dlatego Print Room musi pokazywać podgląd kadrowania dla każdego formatu.**

Typowe formaty PL: 10×15 · 13×18 · 15×21 · 20×30 · 30×40 · 50×70
Typowe papiery: mat · błysk · silk

Model opcji i wariantów musi być elastyczny — **nie koduj formatów na sztywno**.

## 6. Rezerwacje — czego potrzebuje fotograf

```
usługa · czas trwania · cena · zadatek · lokalizacja
dni i godziny dostępności · przerwy · bufor przed i po · blackout dates
```

Bufor jest kluczowy i często pomijany: fotograf potrzebuje 30–60 minut między sesjami
na dojazd i przygotowanie. Bez bufora system zarezerwuje dwie sesje pod rząd i to jest błąd,
który kosztuje fotografa realne pieniądze.

## 7. Skala danych — o czym pamiętać

| Rodzaj sesji | Zdjęć w galerii | Waga oryginałów |
|---|---|---|
| Newborn / rodzinna | 40–150 | 1–5 GB |
| Portretowa | 30–80 | 1–3 GB |
| Ślubna | 500–1500 | **30–80 GB** |
| Eventowa | 200–600 | 5–20 GB |

Fotograf ślubny generuje tyle danych, co dziesięciu rodzinnych. To ma konsekwencje
dla cennika, dla limitów i dla wydajności uploadu.

## 8. Sezonowość

```
Szczyt:  maj–październik (wesela, sesje plenerowe)
Zapaść:  listopad–luty (fotografia rodzinna prawie zamiera)
```

Dlatego: plan miesięczny, pauza konta zamiast anulowania, brak agresywnego windykowania
płatności w martwym sezonie.

## 9. Język produktu

Fotograf mówi: *sesja*, *kadr*, *wybór*, *proofing*, *retusz*, *wywołanie*, *pakiet*,
*zadatek*, *plener*, *stykówka*.

Fotograf **nie mówi**: *asset*, *deliverable*, *SKU*, *tenant*, *workflow engine*.
Te słowa mogą być w kodzie. Nie mogą być w interfejsie.
