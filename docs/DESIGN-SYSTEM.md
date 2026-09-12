# DESIGN SYSTEM — Kadr

> Decyzja źródłowa: **ADR-015** (Obsidian + warstwa ruchu), zastępuje ADR-009 i ADR-010.
> Reguły warsztatowe i lista zakazów: [`.claude/skills/premium-ui-design/SKILL.md`](../.claude/skills/premium-ui-design/SKILL.md)

---

## 1. Kierunek — OBSIDIAN

```
Cała powierzchnia produktu  →  ciemna precyzja
Ruch                        →  wyrazisty, natywny CSS + Web Animations API
Biblioteka animacji         →  BRAK
Motywy galerii klienta      →  Noir (domyślny) · Paper · Minimal
```

**Definicja premium w tym projekcie:** mniej tarcia · lepsza typografia · doskonały odstęp ·
szybkość · spójność · brak błędów · doskonały mobile · ruch, który coś tłumaczy.
**Nie:** więcej gradientów, więcej animacji, więcej funkcji.

**Fotografia jest bohaterem. Interfejs się cofa.** Ciemne tło jest tu decyzją funkcjonalną,
nie modą — zdjęcia na czerni wyglądają drożej, a interfejs przestaje z nimi konkurować.

### Gdzie przebiega granica ruchu

Ruch ma **pokazywać**, nie zdobić.

| ✓ Uzasadnione | ✗ Dekoracja |
|---|---|
| licznik dopłaty liczący się przy wejściu w widok — tłumaczy działanie produktu | parallax na tle |
| kaskadowe wejście listy etapów — buduje kolejność czytania | animowanie każdego nagłówka |
| podświetlenie karty planu pod kursorem — potwierdza, co jest aktywne | pulsujące ikony |
| uniesienie karty o 2 px przy najechaniu — sygnał interaktywności | uniesienie o 8 px i obrót |

### Dwa gradienty w całym produkcie

Zakaz gradientów z `CLAUDE.md` §7 obowiązuje. ADR-015 dopuszcza dokładnie dwa wyjątki,
oba niosące funkcję, a nie ozdobę:
1. **poświata otoczenia w hero** — odsuwa sekcję otwierającą od reszty strony,
2. **obrys planu rekomendowanego** — wyróżnia bez zmiany koloru tła karty.

Każdy kolejny wymaga osobnej decyzji.

## 2. Tokeny

Tokeny są **semantyczne**, nigdy dosłowne. `--kadr-surface`, nie `--kadr-beige`.
To jest warunek działania trzech motywów galerii bez duplikowania stylów.

### Kolor — Obsidian

Pełne wartości: [`assets/css/tokens.css`](../assets/css/tokens.css). Skrót:

| Rola | Wartość | Uwaga |
|---|---|---|
| `--kadr-surface` | `#08080A` | czerń z chłodnym odcieniem, nie `#000` |
| `--kadr-surface-raised` | `#101014` | karta, panel |
| `--kadr-surface-overlay` | `#17171D` | dialog, uniesiony panel |
| `--kadr-ink` | biel 96% | nagłówki — 18,35:1 |
| `--kadr-ink-muted` | biel 66% | treść — 8,72:1 |
| `--kadr-ink-subtle` | biel 48% | etykiety — 5,00:1 |
| `--kadr-accent` | `#4D7CFF` | **tekst i obrysy** — 5,38:1 |
| `--kadr-accent-solid` | `#3463E6` | **wypełnienia** — biel na nim 5,16:1 |
| `--kadr-signal` | `#38E8D0` | kwota, sukces, licznik — 12,31:1 |
| `--kadr-line-control` | biel 36% | obrys kontrolki — 3,19:1, wymóg WCAG 1.4.11 |

**Dlaczego akcent ma dwa warianty.** `#4D7CFF` jako tekst na ciemnym tle daje 5,38:1 i zdaje AA.
Ale biel na `#4D7CFF` jako wypełnieniu daje 3,72:1 i **nie zdaje** — a to jest przycisk główny,
najważniejszy element strony. Rozdzielenie wyszło z pomiaru, nie z estetyki.

Kontrast weryfikuje `php tools/check-contrast.php`, czytając paletę wprost z `tokens.css`.
Narzędzie wykryło oba powyższe błędy przed wdrożeniem.

**Obrysy jako biel o niskiej przezroczystości, nie szarości** — przezroczystość nawarstwia się
poprawnie na każdej warstwie powierzchni, szarość trzeba by dobierać osobno dla każdej.

### Odstęp — skala 4 px

```css
--kadr-space-1: 4px;    --kadr-space-2: 8px;    --kadr-space-3: 12px;
--kadr-space-4: 16px;   --kadr-space-5: 24px;   --kadr-space-6: 32px;
--kadr-space-7: 48px;   --kadr-space-8: 64px;   --kadr-space-9: 96px;
--kadr-space-10: 128px; --kadr-space-11: 160px;
```

Sekcje marketingowe: `space-9` do `space-11`. Dashboard: `space-3` do `space-6`.
**Zakaz wartości spoza skali.** `margin-top: 37px` nie istnieje.

### Promień

```css
--kadr-radius-sm:   6px;    /* pola, przyciski */
--kadr-radius-md:   10px;   /* karty, panele */
--kadr-radius-lg:   16px;   /* dialogi, ramki zrzutów */
--kadr-radius-full: 999px;  /* WYŁĄCZNIE avatar i badge licznika */
```

Pill buttons nadal są zakazane — 6 px to zaokrąglenie, 999 px to inna estetyka.

### Uniesienie — warstwy, nie cienie

```css
--kadr-elevation-1: 0 1px 0 rgba(255,255,255,.05) inset;                    /* karta */
--kadr-elevation-2: 0 8px 24px rgba(0,0,0,.5), inset highlight;             /* panel */
--kadr-elevation-3: 0 24px 64px rgba(0,0,0,.7), inset highlight;            /* zrzut hero, dialog */
--kadr-glow:        obrys akcentu + poświata;                               /* stan aktywny */
```

Na ciemnym tle cień jest słabo widoczny. Głębię robi **warstwa powierzchni + obrys +
wewnętrzne podświetlenie górnej krawędzi** — to ostatnie imituje światło padające z góry
i jest tym, co odróżnia dopracowany ciemny interfejs od czarnego prostokąta.

### Szerokości i warstwy

```css
--kadr-width-prose:   65ch;   --kadr-width-content: 1120px;
--kadr-width-wide:    1440px; --kadr-width-full:    100%;

--kadr-z-base: 0;      --kadr-z-sticky: 100;  --kadr-z-drawer: 200;
--kadr-z-dialog: 300;  --kadr-z-toast: 400;   --kadr-z-lightbox: 500;
```

### Ruch

```css
--kadr-motion-instant: 90ms;    /* zmiana stanu przycisku */
--kadr-motion-fast:    180ms;   /* hover, focus, toggle */
--kadr-motion-base:    320ms;   /* wejście panelu */
--kadr-motion-reveal:  620ms;   /* wejście sekcji przy scrollu */

--kadr-ease-spring: cubic-bezier(0.16, 1, 0.3, 1);   /* to on daje wrażenie „drogiego” ruchu */
```

Implementacja: [`assets/css/motion.css`](../assets/css/motion.css) +
[`assets/js/motion.js`](../assets/js/motion.js). Zero zależności — 2,0 KB gzip.

**Progressive enhancement jest tu warunkiem, nie ozdobą.** Stany początkowe animacji
obowiązują wyłącznie, gdy JavaScript zdąży oznaczyć dokument klasą `kadr-motion`.
Bez JS strona jest kompletna i czytelna — nigdy nie ukrywamy treści, której nie umiemy pokazać.

Przy `prefers-reduced-motion: reduce` skrypt **kończy pracę zanim cokolwiek zrobi**, a CSS
zeruje stany początkowe. Zmiana preferencji w trakcie sesji zatrzymuje ruch natychmiast.

---

## 3. Typografia

| Rola | Krój | Uwagi |
|---|---|---|
| Display | **Bricolage Grotesque** (variable, OFL) | zmienna szerokość i waga — charakter bez ozdobników |
| UI / tekst | **Geist Sans** (OFL) | zaprojektowany pod interfejsy, świetny w małych stopniach |
| Dane | **Geist Mono** (OFL) | kwoty, liczniki, etykiety sekcji, ID |

Wszystkie trzy kroje są na licencji OFL, więc **kwestia licencyjna O4 jest zamknięta** —
zostaje wyłącznie osadzenie plików `woff2` w `assets/fonts/`.

**Nie używamy Inter + Poppins.** Typografia jest elementem identyfikacji produktu.
Etykiety sekcji, liczby i dane techniczne idą krojem monospace — to on niesie charakter
„narzędzia" w tym kierunku.

### Skala (płynna, `clamp`)

```css
--kadr-text-display: clamp(2.75rem, 6vw, 5rem);      /* hero. line-height .95, letter-spacing -.02em */
--kadr-text-h1:      clamp(2rem, 4vw, 3.25rem);      /* 1.05 */
--kadr-text-h2:      clamp(1.5rem, 2.5vw, 2.25rem);  /* 1.15 */
--kadr-text-h3:      1.375rem;                        /* 1.25 */
--kadr-text-body-lg: 1.125rem;                        /* 1.6 — lead */
--kadr-text-body:    1rem;                            /* 1.6 */
--kadr-text-sm:      0.875rem;                        /* 1.5 */
--kadr-text-xs:      0.75rem;                         /* 1.4 — etykiety, wersaliki, +.06em */
```

**Reguły:** maks. 65 znaków w wierszu tekstu ciągłego · nagłówki Fraunces z ujemnym trackingiem ·
tekst UI nigdy poniżej 14 px · liczby zawsze `tabular-nums` (kwoty nie mogą skakać przy zmianie).

---

## 4. Motywy galerii

Jeden komplet komponentów, trzy zestawy tokenów.

| Motyw | Charakter | Kiedy | Plan |
|---|---|---|---|
| **Noir** | domyślny, zgodny z Obsidian — zdjęcia świecą | ślub, portret, fine-art | wszystkie |
| **Paper** | ciepły, jasny, papierowy | rodzinna, newborn, lifestyle | Starter+ |
| **Minimal** | czysta biel, zero ozdób | produktowa, komercyjna | Studio+ |

Fotograf nadpisuje: logo, kolor akcentu, krój nagłówków (z krótkiej listy), stopkę.
**Nie może** zmienić odstępu, siatki ani skali typograficznej — to jest granica chroniąca
premium wygląd przed przypadkowym zniszczeniem (wymóg z briefu §6).

W planach Studio i Pro: ukrycie brandingu platformy. W Pro: własna domena.

---

## 5. Komponenty bazowe (Session 2)

```
Button (primary / secondary / ghost / danger · sm / md / lg)
Input · Textarea · Select · Checkbox · Radio · Switch · FileDrop
Card · Panel · Divider · Badge · Tag · Avatar
Table (sortowalna, z pustym stanem) · Pagination
Dialog (focus trap, Esc, przywrócenie fokusu) · Drawer · Popover · Tooltip
Toast · InlineAlert · EmptyState · Skeleton · ProgressBar
Tabs · Breadcrumb · Stepper · JourneyTimeline
Lightbox (klawiatura, swipe, focus trap)
```

**Każdy komponent musi mieć:** stan hover, focus-visible, active, disabled, loading, error, pusty ·
wersję mobilną · test kontrastu w trzech motywach · obsługę z klawiatury.

---

## 6. Puste stany

Pusty ekran to część onboardingu, nie komunikat o błędzie.

```
❌  „Brak galerii”

✅  Tytuł:      Jeszcze żadnej galerii
    Opis:       Galeria to miejsce, w którym klient wybiera zdjęcia i dopłaca
                za te ponad pakiet. Pierwszą przygotujesz w kilka minut.
    Akcja:      [ Utwórz galerię ]
    Drugorzędna: Zobacz przykładową galerię
```

Każdy pusty stan: co to jest · dlaczego warto · jeden konkretny następny krok.

---

## 7. Obsługa błędów

| Sytuacja | Wzorzec |
|---|---|
| Błąd walidacji pola | komunikat inline pod polem, `aria-describedby`, fokus na pierwszym błędzie |
| Nieudana akcja | toast z treścią i akcją „Spróbuj ponownie” |
| Błąd ładowania sekcji | stan błędu w obrębie sekcji, reszta strony działa |
| Utrata połączenia | pasek trwały, kolejkowanie akcji tam, gdzie to możliwe |
| Błąd serwera | strona przyjazna + identyfikator zgłoszenia do wsparcia |

Klient **nigdy** nie widzi `Undefined index`, `REST 500`, `SQLSTATE` ani ścieżki pliku.

---

## 8. Strona marketingowa — kolejność sekcji

1. **Hero** — rezultat w jednym zdaniu + prawdziwy zrzut ekranu wyboru zdjęć z widocznym licznikiem dopłaty
2. **Problem** — jak wygląda dziś obsługa jednej sesji. *Fotograf ma w 10 sekund pomyśleć „to o mnie”*
3. **Client Journey** — oś procesu jako jedna duża grafika (nasz wyróżnik, więc wysoko)
4. **Galeria** — realny podgląd na makiecie telefonu
5. **Wybór i dopłata** — z liczbami: „pakiet 20, wybrała 28, dopłata 480 zł, BLIK w 40 sekund”
6. **Print Room** — odbitki bez wychodzenia z galerii
7. **Odsłona** — dostawa jako wydarzenie
8. **Rezerwacje**
9. **Automatyzacje** — wymienione z nazwy, nie „potęga automatyzacji”
10. **Bezpieczeństwo i RODO** — w Polsce to argument sprzedażowy, nie stopka
11. **Cennik**
12. **Opinie** — *puste, jawnie oznaczone sloty. Zero wymyślonych cytatów, ocen i logotypów*
13. **FAQ** — prawdziwe obiekcje („czy to zastąpi moją stronę”, „co ze zdjęciami, jeśli zrezygnuję”,
    „czy klient musi zakładać konto”)
14. **CTA** — „Zacznij od 5 darmowych projektów. Bez karty.”

**Problem idzie przed rozwiązaniem. Dowód społeczny nisko** — bo go jeszcze nie mamy
i nie będziemy go udawać.

### Hero pokazuje prawdziwy UI produktu
Nie abstrakcyjną ilustrację, nie render 3D, nie „laptop na biurku ze stocka”.
Potencjalny klient ma zobaczyć galerię, dashboard, zamówienie i kalendarz.

---

## 9. Bloki Gutenberga (Session 2)

```
kadr/hero              kadr/problem-statement   kadr/journey-timeline
kadr/feature-split     kadr/gallery-showcase    kadr/pricing-table
kadr/faq               kadr/testimonial-slot    kadr/comparison
kadr/cta-band          kadr/logo-wall           kadr/document-body
```

Zasady: `block.json` · render PHP tam, gdzie potrzebna jest logika serwerowa ·
Interactivity API dla interakcji (FAQ, przełącznik cennika) · natywne komponenty w edytorze ·
**bez Reacta na froncie tylko dlatego, że potrafimy**.

**Granice dla administratora:** wybiera warianty i treść, nie dowolne wartości.
Kolory z palety, odstępy ze skali, typografia z tokenów. Administrator nie ma jak zepsuć układu.

---

## 10. Dostępność — WCAG 2.2 AA

| Wymóg | |
|---|---|
| Kontrast | 4,5:1 tekst, 3:1 duży tekst i elementy UI — **weryfikowany w każdym motywie** |
| Klawiatura | cała aplikacja, **łącznie z galerią i lightboxem** (strzałki, Esc, Tab, Enter/Spacja) |
| Focus | widoczny zawsze, `:focus-visible`, min. 2 px, kontrast 3:1 |
| Semantyka | prawdziwe `<button>`, `<nav>`, `<main>`, `<dialog>` — nie `<div onclick>` |
| ARIA | tylko tam, gdzie semantyka HTML nie wystarcza |
| Dialogi | focus trap, Esc, przywrócenie fokusu do elementu wyzwalającego |
| Obrazy | sensowny `alt`; zdjęcia w galerii: opis kontekstowy, nie „IMG_4471.jpg” |
| Ruch | `prefers-reduced-motion` respektowane |
| Cel dotykowy | min. 44 × 44 px na mobile |
| Formularze | etykieta zawsze, błąd powiązany przez `aria-describedby` |

---

## 11. Mobile first — w galerii to nie jest hasło

Galeria klienta jest projektowana **najpierw na telefon**, nie zwężana z desktopu.

```
swipe między zdjęciami           ulubione jednym kciukiem
wybór bez wychodzenia z widoku   podsumowanie w dolnym panelu
checkout w jednej kolumnie       BLIK jako pierwsza metoda
pobieranie bez ZIP-a na telefonie
```

Punkty łamania: `480 / 768 / 1024 / 1440`. Testujemy realnie na 375 px szerokości.
