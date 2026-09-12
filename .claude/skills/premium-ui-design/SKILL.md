---
name: premium-ui-design
description: Zasady projektowania interfejsu dla Kadr. Użyj ZAWSZE przed napisaniem jakiegokolwiek HTML, CSS, komponentu, sekcji strony marketingowej, ekranu dashboardu lub widoku galerii. Zawiera listę zakazanych wzorców „AI design” i definicję premium w tym projekcie.
---

# Premium UI Design — Kadr

> Jedna rzecz, którą masz zapamiętać: **NIE TWÓRZ TYPOWEGO DESIGNU AI.**

## 1. Lista zakazów — czytaj przed każdym ekranem

Zakazane jako domyślny kierunek:

```
✖ fioletowo-niebieski gradient SaaS
✖ przypadkowe gradienty gdziekolwiek
✖ glassmorphism
✖ blur jako dekoracja
✖ trzy identyczne karty obok siebie w każdej sekcji
✖ gigantyczne border-radius
✖ pill buttons wszędzie
✖ floating cards bez powodu
✖ stockowe ilustracje ludzi przy laptopie
✖ ikony rakiety / tarczy / błyskawicy / żarówki
✖ „Everything you need in one place” i polskie odpowiedniki
✖ nadmiar emoji
✖ animowanie wszystkiego, bo się da
✖ kopiowanie układu konkurencji
✖ hero z abstrakcyjną ilustracją zamiast prawdziwego UI
```

Jeśli piszesz sekcję i wychodzą trzy karty w rzędzie — **zatrzymaj się i zaprojektuj to inaczej.**
Asymetria, różne rozmiary, inny rytm. Trzy równe karty to domyślny odruch, a nie decyzja.

## 2. Definicja premium w tym projekcie

Premium **nie** znaczy: więcej gradientów, animacji, funkcji.

Premium znaczy:
```
mniej tarcia · lepsza typografia · doskonały odstęp · świetny onboarding
szybkość · przemyślany workflow · spójność · bezpieczeństwo · brak błędów
doskonały mobile · jasny produkt
```

**Fotografia jest bohaterem. UI jest ramą.** Interfejs, który konkuruje ze zdjęciem, przegrywa.

## 3. Kierunek

Atelier (marketing) + Studio OS (dashboard) + motywy galerii Paper/Noir/Minimal.
Pełna specyfikacja tokenów, typografii i komponentów: `docs/DESIGN-SYSTEM.md`.

**Nie improwizuj wartości.** Kolor, odstęp, promień, cień, ruch — wyłącznie z tokenów.
`margin-top: 37px` i `#F5F2EC` napisane z pamięci nie istnieją.

## 4. Copy

| ✖ | ✓ |
|---|---|
| „Zrewolucjonizuj swój workflow” | „Klientka wybrała 28 zdjęć. Pakiet obejmuje 20. Dopłatę policzy system.” |
| „Uwolnij swój potencjał” | „Przestań szukać zdjęć opisanych w Messengerze.” |
| „Wszystko w jednym miejscu” | „Rezerwacja, galeria, wybór i płatność — jeden link dla klienta.” |
| „Seamless experience” | konkret albo nic |

Krótko. Po polsku. Do fotografa. Z liczbami tam, gdzie liczby są prawdziwe.

**Nigdy nie wymyślaj:** opinii, ocen („4,9/5 — 2000 fotografów”), logotypów klientów,
statystyk, case studies. Używaj jawnie oznaczonych, pustych slotów.

## 5. Animacja

Animacja ma wspierać **hierarchię, orientację, feedback albo narrację**. Nic więcej.

- CSS i natywne API w pierwszej kolejności
- biblioteka animacji wymaga uzasadnienia przed instalacją
- `prefers-reduced-motion` obsłużone zawsze
- czas trwania z tokenów, nie z palca

## 6. Zanim uznasz ekran za skończony

```
[ ] wygląda inaczej niż domyślny szablon SaaS?
[ ] każda wartość CSS pochodzi z tokenów?
[ ] stan hover, focus-visible, active, disabled, loading, error, pusty?
[ ] pusty stan mówi, co zrobić dalej?
[ ] działa na 375 px szerokości?
[ ] obsługiwane z klawiatury?
[ ] kontrast sprawdzony (4,5:1 / 3:1)?
[ ] fotografia jest bohaterem, a nie tłem dla UI?
[ ] każdy string przez i18n?
[ ] żadnej wymyślonej treści?
```
