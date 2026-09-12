---
name: gutenberg
description: Zasady tworzenia bloków Gutenberga dla strony marketingowej Kadr. Użyj przed napisaniem jakiegokolwiek bloku, block.json, render.php lub kodu edytora WordPress.
---

# Gutenberg — bloki marketingowe Kadr

## 1. Podejście

- `block.json` jako źródło metadanych **zawsze**
- render PHP (`render.php`) tam, gdzie potrzebna jest logika serwerowa
  (cennik czytający rejestr planów, dokumenty, dane dynamiczne)
- **WordPress Interactivity API** dla interakcji na froncie (FAQ, przełącznik cennika, tabs)
- natywne pakiety WordPressa w edytorze (`@wordpress/components`, `block-editor`, `i18n`)
- **bez Reacta na froncie tylko dlatego, że potrafimy**
- bez Elementora, bez WPBakery, bez uzależnienia od ciężkiego page buildera

## 2. Granice dla administratora — rzecz kluczowa

Administrator ma edytować **treść i warianty**, nie dowolne wartości.

```
✅ tekst, nagłówki, CTA, obrazy, kolejność sekcji, wybór wariantu układu
✅ kolor z palety motywu (nie color picker RGB)
✅ odstęp ze skali (nie dowolny px)

✖ dowolny kolor          ✖ dowolny rozmiar czcionki
✖ dowolny odstęp         ✖ dowolny border-radius
```

**Cel: administrator nie może przypadkowo zniszczyć premium wyglądu.**
W `block.json`:
```json
"supports": {
  "color": { "text": false, "background": false },
  "spacing": { "padding": false, "margin": false },
  "typography": { "fontSize": false, "lineHeight": false }
}
```
Warianty wystawiaj jako `attributes` z `enum`, nie jako swobodne pola.

## 3. Bloki (Session 2)

```
kadr/hero               kadr/problem-statement    kadr/journey-timeline
kadr/feature-split      kadr/gallery-showcase     kadr/pricing-table
kadr/faq                kadr/testimonial-slot     kadr/comparison
kadr/cta-band           kadr/logo-wall            kadr/document-body
```

`kadr/pricing-table` **czyta rejestr planów** (`docs/BILLING.md`), nie ma cen w atrybutach.
Cena nie może istnieć w dwóch miejscach.

`kadr/testimonial-slot` i `kadr/logo-wall` renderują **jawnie oznaczone puste sloty**,
dopóki nie ma prawdziwych treści. Nigdy nie generuj przykładowych opinii ani logotypów.

## 4. Wydajność

- CSS bloku ładowany **tylko gdy blok jest na stronie** (`"style"` w `block.json`)
- to samo dla skryptu (`"viewScript"`, `"viewScriptModule"`)
- zero globalnego bundla ładowanego na każdej stronie
- obrazy z `srcset`, `sizes`, wymiarami; hero z `fetchpriority="high"` i bez `lazy`
- budżet landingu: ≤ 30 KB JS, ≤ 25 KB CSS (gzip)

## 5. Dostępność

Semantyczny HTML w `render.php` (`<section>`, `<h2>`, `<button>`), nie `<div>` z klasami.
FAQ przez `<details>`/`<summary>` albo poprawny wzorzec accordion z `aria-expanded`.
Każdy blok obsługiwany z klawiatury. Każdy obraz z sensownym `alt` edytowalnym przez admina.

## 6. i18n

Etykiety w edytorze przez `__()` z `@wordpress/i18n`, text domain `kadr`.
Treść wpisana przez administratora **nie jest** tłumaczona przez i18n — to dane.

## 7. Checklista bloku

```
[ ] block.json z supports wyłączającymi swobodne style?
[ ] render.php zamiast zapisanego HTML tam, gdzie potrzeba logiki?
[ ] CSS i JS ładowane tylko przy użyciu bloku?
[ ] warianty jako enum, nie dowolne pola?
[ ] semantyczny HTML i obsługa klawiatury?
[ ] podgląd w edytorze odpowiada frontowi?
[ ] działa na 375 px?
[ ] zero wymyślonych treści (opinii, logotypów, statystyk)?
```
