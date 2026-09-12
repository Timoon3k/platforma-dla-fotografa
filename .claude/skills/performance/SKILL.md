---
name: performance
description: Budżety i techniki wydajnościowe dla Kadr. Użyj przed pisaniem frontendu, ładowaniem skryptów lub stylów, obsługą obrazów, zapytaniami do bazy, listami danych i operacjami długotrwałymi.
---

# Performance — Kadr

> Wydajność jest funkcją produktu, nie poprawką na końcu.
> Klientka otwiera galerię o 22:30 na telefonie. Pierwsze trzy sekundy decydują o wszystkim.

Pełne budżety i metodyka pomiaru: `docs/PERFORMANCE.md`.

## 1. Budżety (bramka, nie wskazówka)

| Powierzchnia | LCP | JS gzip | CSS gzip |
|---|---|---|---|
| Landing | < 2,0 s | ≤ 30 KB | ≤ 25 KB |
| Galeria klienta | < 2,5 s | ≤ 60 KB | ≤ 30 KB |
| Dashboard | < 2,5 s | ≤ 120 KB | ≤ 40 KB |

Wszędzie: INP ≤ 200 ms, CLS ≤ 0,1. Pomiar: Lighthouse mobile, 4G, CPU 4×, zimny cache.

## 2. Obrazy — największa dźwignia

```html
<!-- HERO / LCP -->
<img src="hero-1200.webp"
     srcset="hero-600.avif 600w, hero-1200.avif 1200w, hero-2000.avif 2000w"
     sizes="(max-width:768px) 100vw, 60vw"
     width="1200" height="800"
     fetchpriority="high" decoding="async" alt="…">
```

```
✅ preload TYLKO faktycznego assetu LCP
✅ width i height zawsze → zero CLS
✅ pierwsze ~6 zdjęć siatki bez lazy, reszta lazy
✅ LQIP 20 px w data URI (~400 B) — ogromna różnica w odczuwanej szybkości
✖ NIGDY loading="lazy" na obrazie LCP
✖ nie generuj 20 wariantów zdjęcia — sześć wystarczy (ADR-011)
```

## 3. JavaScript

```
Landing   → bez frameworka, Interactivity API w blokach
Galeria   → vanilla + natywne API
Dashboard → wyspy, nie SPA
```
`defer` domyślnie · kod dashboardu **nie ładuje się** na landingu · CSS aplikacji nie ładuje się
na stronie marketingowej · biblioteka > 10 KB wymaga wpisu w `DECISIONS.md`.

**Progressive enhancement:** galeria pokazuje zdjęcia, zanim wykona się jakikolwiek JS.

## 4. Baza danych

```
✅ każdy indeks zaczyna się od tenant_id
✅ liczniki zużycia przyrostowe, nie SUM()
✅ paginacja kursorowa, nie OFFSET
✅ warianty zdjęć pobierane jednym zapytaniem dla całej strony
✅ wyszukiwarka z debounce ≥ 250 ms i indeksem
✅ widok „Dzisiaj” = jedno zapytanie zagregowane, nie osiem
```

`SELECT SUM(bytes) FROM gallery_assets WHERE tenant_id = ?` przy 18 mln wierszy,
wykonywane przy każdym wejściu do dashboardu, to koniec wydajności produktu.

## 5. Cache

Wszystko, co zależy od tenanta, **musi mieć `tenant_id` w kluczu cache.**
Klucz bez tenanta to nie problem wydajności, tylko wyciek danych.

## 6. Zadania w tle

Do kolejki: warianty obrazów · ZIP · wysyłki masowe · archiwizacja · eksport RODO · importy.
**Jedno zadanie na zdjęcie**, nie na galerię — retry nie powtarza 800 operacji.
Limit współbieżności per tenant. Widoczny postęp: „247 z 800”.

## 7. Antywzorce

```
✖ preload dziesięciu zasobów            ✖ lazy na LCP
✖ cały CSS aplikacji na landingu        ✖ biblioteka animacji dla jednego fade-in
✖ web font dla ikon                     ✖ SUM/COUNT po dużych tabelach w dashboardzie
✖ zapytanie przy każdym klawiszu        ✖ ZIP budowany w requeście
✖ polyfille dla przeglądarek, których nasi użytkownicy nie mają
```
