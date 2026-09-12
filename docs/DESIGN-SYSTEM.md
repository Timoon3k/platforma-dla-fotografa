# DESIGN SYSTEM — Kadr

> Decyzje źródłowe: ADR-009 (kierunek wizualny), ADR-010 (dark mode).
> Reguły warsztatowe i lista zakazów: [`.claude/skills/premium-ui-design/SKILL.md`](../.claude/skills/premium-ui-design/SKILL.md)

---

## 1. Kierunek

```
Strona marketingowa  →  ATELIER        ciepły papier, editorial, spokój
Dashboard fotografa  →  STUDIO OS      gęsty, precyzyjny, w palecie Atelier
Galeria klienta      →  MOTYW: Paper | Noir | Minimal (wybiera fotograf)
```

**Definicja premium w tym projekcie:** mniej tarcia · lepsza typografia · doskonały odstęp ·
świetny onboarding · szybkość · spójność · brak błędów · doskonały mobile.
**Nie:** więcej gradientów, więcej animacji, więcej funkcji.

**Fotografia jest bohaterem. UI jest ramą.** Jeśli interfejs konkuruje ze zdjęciem — interfejs przegrywa.

---

## 2. Tokeny

Tokeny są **semantyczne**, nigdy dosłowne. `--kadr-surface`, nie `--kadr-beige`.
To jest warunek działania trzech motywów galerii bez duplikowania stylów.

### Kolor — Atelier (baza)

```css
:root {
  /* powierzchnie */
  --kadr-surface:          #F4F1EA;   /* kość słoniowa — tło strony */
  --kadr-surface-raised:   #FBFAF7;   /* karta, panel */
  --kadr-surface-sunken:   #EBE7DD;   /* pole, obszar wklęsły */
  --kadr-surface-inverse:  #1A1917;

  /* treść */
  --kadr-ink:              #1A1917;   /* tekst podstawowy */
  --kadr-ink-muted:        #5C574F;   /* tekst drugorzędny */
  --kadr-ink-subtle:       #8A8378;   /* etykiety, podpisy */
  --kadr-ink-inverse:      #F4F1EA;

  /* akcenty — DWA, nigdy więcej */
  --kadr-accent:           #B4543A;   /* terakota — akcja główna */
  --kadr-accent-hover:     #9A4530;
  --kadr-accent-soft:      #F0DED7;
  --kadr-secondary:        #5A6046;   /* oliwka — akcent drugorzędny */

  /* stany */
  --kadr-success:          #4A6B47;
  --kadr-warning:          #9C6B1F;
  --kadr-danger:           #A33A2E;
  --kadr-info:             #46596B;

  /* linie */
  --kadr-line:             #DDD7CA;   /* hairline 1px */
  --kadr-line-strong:      #C4BCAA;
  --kadr-focus:            #B4543A;
}
```

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
--kadr-radius-sm: 2px;    /* pola, przyciski */
--kadr-radius-md: 4px;    /* karty, panele */
--kadr-radius-lg: 8px;    /* dialogi */
--kadr-radius-full: 999px; /* WYŁĄCZNIE avatar i badge licznika */
```

Atelier jest kanciasty. Duże zaokrąglenia i pill buttons to estetyka, której unikamy.

### Cień — używany oszczędnie

```css
--kadr-shadow-sm: 0 1px 2px rgba(26,25,23,.06);
--kadr-shadow-md: 0 2px 8px rgba(26,25,23,.08);
--kadr-shadow-lg: 0 8px 32px rgba(26,25,23,.12);   /* tylko dialog i lightbox */
```

Separacja przez **światło i hairline**, nie przez cień. Cień oznacza „to unosi się nad stroną” —
a unosi się wyłącznie dialog.

### Szerokości i warstwy

```css
--kadr-width-prose:   65ch;   --kadr-width-content: 1120px;
--kadr-width-wide:    1440px; --kadr-width-full:    100%;

--kadr-z-base: 0;      --kadr-z-sticky: 100;  --kadr-z-drawer: 200;
--kadr-z-dialog: 300;  --kadr-z-toast: 400;   --kadr-z-lightbox: 500;
```

### Ruch

```css
--kadr-motion-instant: 80ms;   /* zmiana stanu przycisku */
--kadr-motion-fast:    160ms;  /* hover, focus, toggle */
--kadr-motion-base:    240ms;  /* wejście panelu */
--kadr-motion-slow:    480ms;  /* pojawienie się obrazu */
--kadr-ease:        cubic-bezier(.2,0,.2,1);
--kadr-ease-out:    cubic-bezier(0,0,.2,1);
```

```css
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: .01ms !important;
    transition-duration: .01ms !important;
    scroll-behavior: auto !important;
  }
}
```
Ta reguła jest obowiązkowa, nie opcjonalna.

---

## 3. Typografia

| Rola | Krój | Uwagi |
|---|---|---|
| Display | **Fraunces** (variable, OFL) | oś optyczna i `SOFT`/`WONK` — charakter bez ozdobników |
| UI / tekst | **General Sans** (Fontshare) | czytelny grotesk, szeroki zakres grubości |
| Dane | **General Sans** + `font-variant-numeric: tabular-nums` | kwoty, daty, liczniki |

⚠️ Licencje do potwierdzenia przed Session 2 (`PROJECT_STATE.md`, kwestia O4).
Zapasowo, gdyby licencja nie pozwalała: **Newsreader** (display) + **Public Sans** (UI), oba OFL.

**Nie używamy Inter + Poppins.** Typografia jest elementem identyfikacji produktu.

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
| **Paper** | ciepły, jasny, editorial (baza Atelier) | rodzinna, newborn, lifestyle | wszystkie |
| **Noir** | głęboka czerń `#0B0B0C`, zdjęcia świecą | ślub, portret, fine-art | Starter+ |
| **Minimal** | czysta biel `#FFFFFF`, zero ozdób | produktowa, komercyjna | Studio+ |

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
