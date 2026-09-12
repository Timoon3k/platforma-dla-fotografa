# PERFORMANCE — Kadr

> Wydajność jest funkcją produktu, nie poprawką na końcu.
> Klientka z persony P4 otwiera galerię o 22:30 na telefonie, czasem w słabym zasięgu.
> **Pierwsze trzy sekundy decydują o tym, czy produkt zadziałał.**

---

## 1. Budżety — obowiązujące od pierwszej linii kodu

| Powierzchnia | LCP | INP | CLS | JS (gzip) | CSS (gzip) |
|---|---|---|---|---|---|
| Landing | < 2,0 s | ≤ 200 ms | ≤ 0,1 | ≤ 30 KB | ≤ 25 KB |
| Cennik / podstrony | < 2,0 s | ≤ 200 ms | ≤ 0,1 | ≤ 20 KB | ≤ 25 KB |
| Galeria klienta | < 2,5 s | ≤ 200 ms | ≤ 0,1 | ≤ 60 KB | ≤ 30 KB |
| Dashboard | < 2,5 s | ≤ 200 ms | ≤ 0,1 | ≤ 120 KB | ≤ 40 KB |

Budżet przekroczony = zmiana nie wchodzi. To jest bramka, nie wskazówka.

**Warunki pomiaru:** Lighthouse mobile, throttling 4G, CPU 4×, zimny cache.

---

## 2. Obrazy — największa dźwignia w tym produkcie

### Hero (LCP)
```html
<img src="hero-1200.webp"
     srcset="hero-600.avif 600w, hero-1200.avif 1200w, hero-2000.avif 2000w"
     sizes="(max-width: 768px) 100vw, 60vw"
     width="1200" height="800"
     fetchpriority="high"
     decoding="async"
     alt="…">
```
- **Nigdy `loading="lazy"` na obrazie LCP.**
- `<link rel="preload">` **tylko** dla faktycznego assetu LCP. Nie dla dziesięciu zasobów.
- `width` i `height` zawsze → zero CLS.

### Siatka galerii
- `loading="lazy"` dla wszystkiego poniżej pierwszego ekranu
- pierwsze ~6 zdjęć bez lazy (są w widoku przy starcie)
- placeholder o właściwych proporcjach (`aspect-ratio` z wymiarów w bazie) → zero przeskoków
- `IntersectionObserver` do doładowywania kolejnych stron, kursorowo
- LQIP: miniatura 20 px jako `background-image` w data URI — koszt ~400 bajtów na zdjęcie,
  a różnica w odczuwanej szybkości jest ogromna

### Warianty (ADR-011)

| Wariant | Szerokość | Formaty | Zastosowanie |
|---|---|---|---|
| `thumb` | 400 px | AVIF, WebP | siatka mobile |
| `grid` | 900 px | AVIF, WebP | siatka desktop / retina mobile |
| `view` | 1800 px | AVIF, WebP | lightbox |
| `wm` | 1800 px | WebP | podgląd ze znakiem wodnym (opcjonalnie) |

JPEG generowany **leniwie**, przy pierwszym żądaniu z przeglądarki bez AVIF/WebP.
Sześć plików na zdjęcie, nie dwadzieścia — storage jest kosztem SaaS-u.

---

## 3. JavaScript

| Zasada | |
|---|---|
| Landing | bez frameworka. Interactivity API w blokach tam, gdzie interakcja jest potrzebna |
| Galeria | vanilla + natywne API (`IntersectionObserver`, `View Transitions` tam, gdzie wspierane) |
| Dashboard | wyspy. Kandydat na bibliotekę: Preact + Signals (~5 KB) — decyzja w Session 3 |
| Ładowanie | `defer` domyślnie. `async` tylko dla niezależnych skryptów |
| Rozdzielenie | kod dashboardu **nie ładuje się** na landingu. Slider nie ładuje się tam, gdzie go nie ma |
| Zależności | każda biblioteka > 10 KB wymaga uzasadnienia w `DECISIONS.md` |

**Progressive enhancement:** galeria pokazuje zdjęcia, zanim wykona się jakikolwiek JavaScript.
Wybór zdjęć wymaga JS — i to jest jedyne miejsce, gdzie go wymaga.

---

## 4. Fonty

- Self-hosted (`woff2`), zero zapytań do zewnętrznych domen (również ze względu na RODO).
- `font-display: swap` + dopasowany fallback (`size-adjust`, `ascent-override`) → brak przeskoku układu.
- Preload **wyłącznie** wariantu użytego w pierwszym ekranie.
- Fraunces jako font zmienny (jeden plik zamiast pięciu grubości).
- Maksymalnie **dwie** rodziny + monospace dla danych. Trzecia rodzina wymaga uzasadnienia.

---

## 5. Baza danych

| Zasada | Powód |
|---|---|
| Każdy indeks zaczyna się od `tenant_id` | inaczej nieużyteczny w zapytaniach wielotenantowych |
| Liczniki zużycia przyrostowe, nie `SUM()` | `SUM` po 18 mln wierszy przy każdym wejściu = koniec |
| Paginacja kursorowa dla list o wolumenie | `OFFSET 50000` skanuje 50 000 wierszy |
| Zero N+1 | warianty zdjęć pobierane jednym zapytaniem dla całej strony |
| Wyszukiwarka z debounce ≥ 250 ms i indeksem | bez tego każde naciśnięcie klawisza to zapytanie |
| Widok „Dzisiaj” = jedno zapytanie zagregowane | nie osiem osobnych |

---

## 6. Cache

| Warstwa | Co |
|---|---|
| Object cache (WP) | entitlementy, ustawienia tenanta, branding — odczytywane przy każdym żądaniu |
| Transient | agregaty analityczne, liczniki dashboardu (TTL 5–15 min) |
| HTTP | assety statyczne z hashem w nazwie, `immutable`, rok |
| Podglądy zdjęć | długi cache po stronie CDN/przeglądarki; URL zmienia się przy zmianie wariantu |
| **Nie cache'ujemy** | niczego zależnego od tenanta bez `tenant_id` w kluczu cache |

Ostatni wiersz to jednocześnie zagadnienie bezpieczeństwa: klucz cache bez tenanta
to wyciek między tenantami.

---

## 7. Zadania w tle

Blokują request → są w kolejce:
generowanie wariantów · pakowanie ZIP · wysyłki masowe · archiwizacja · przeliczanie zużycia ·
import · eksport danych RODO.

- **Jedno zadanie na zdjęcie**, nie jedno na galerię → retry nie powtarza 800 operacji
- Limit współbieżności per tenant → jedno wesele nie głodzi pozostałych
- Widoczny postęp dla fotografa: „247 z 800 przetworzonych”

---

## 8. Upload

- Chunked upload (~5 MB na fragment) z wznawianiem po zerwaniu połączenia
- Równolegle maks. 3 pliki — więcej zabija łącze upload w domowym internecie
- Hash liczony po stronie klienta → wykrycie duplikatu **przed** wysłaniem pliku
- Postęp pojedynczego pliku i postęp całości, anulowanie, ponowienie
- Sprawdzenie limitu storage **przed** rozpoczęciem wysyłki, nie po

---

## 9. Pomiar

| Kiedy | Co |
|---|---|
| Session 2 | audyt Lighthouse landingu przed zamknięciem sesji |
| Session 3 | test galerii z 800 zdjęciami na 4G |
| Session 4 | czas przejścia przez koszyk i płatność |
| Session 6 | pełny audyt CWV wszystkich powierzchni + test obciążeniowy |

Wyniki trafiają do `docs/SESSION-LOG.md`. Regresja względem poprzedniego pomiaru jest blokerem.

---

## 10. Antywzorce — nie robimy tego

```
❌ preload dziesięciu zasobów „na wszelki wypadek”
❌ lazy loading na obrazie LCP
❌ ładowanie całego CSS aplikacji na stronie marketingowej
❌ biblioteka animacji dla jednego fade-in
❌ web font dla ikon (zamiast inline SVG)
❌ SUM/COUNT po tabelach o dużym wolumenie w widoku dashboardu
❌ zapytanie do API przy każdym naciśnięciu klawisza
❌ generowanie 20 wariantów każdego zdjęcia
❌ ZIP budowany w requeście użytkownika
❌ polyfille dla przeglądarek, których nasi użytkownicy nie mają
```
