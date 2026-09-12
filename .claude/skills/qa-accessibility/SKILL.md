---
name: qa-accessibility
description: Standardy testowania i dostępności WCAG 2.2 AA dla Kadr. Użyj przed uznaniem funkcji za skończoną, przy pisaniu testów, przy QA i przy każdym komponencie interaktywnym — dialogu, lightboxie, formularzu, galerii.
---

# QA & Accessibility — Kadr

## 1. Piramida testów

```
        E2E (5 krytycznych ścieżek)          najmniej, najwolniejsze
      Integracyjne / REST / uprawnienia
   Jednostkowe warstwy Domain (bez ładowania WP)   najwięcej, najszybsze
```

Obowiązkowe zestawy specjalne:
```
tests/TenantIsolation/     tenant A próbuje sięgnąć po zasób tenanta B — dla KAŻDEGO endpointu
tests/FileAccess/          bezpośredni URL, wygasły token, cudzy token, token po unieważnieniu
tests/Webhooks/            powtórzenie, zły podpis, stary timestamp, nieznane zdarzenie
tests/Permissions/         każda capability osobno
```

**Nowy endpoint REST bez testu izolacji = niezaliczony build.**

## 2. Pięć krytycznych ścieżek E2E

```
J1  fotograf rejestruje się → tworzy galerię → wgrywa zdjęcia → wysyła klientowi
J2  klient otwiera galerię → wybiera zdjęcia → zatwierdza wybór
J3  klient wybiera ponad limit → powstaje zamówienie → płaci
J4  fotograf dodaje gotowe zdjęcia → klient pobiera
J5  klient rezerwuje sesję i wpłaca zadatek
```

Każda wykonywana również w widoku mobilnym 375 px.

## 3. WCAG 2.2 AA — wymagania

| Obszar | Wymóg |
|---|---|
| Kontrast | 4,5:1 tekst, 3:1 duży tekst i elementy UI — **w każdym z trzech motywów galerii** |
| Klawiatura | całość, **łącznie z galerią i lightboxem** |
| Focus | `:focus-visible` widoczny zawsze, min. 2 px, kontrast 3:1 |
| Semantyka | `<button>`, `<nav>`, `<main>`, `<dialog>` — nigdy `<div onclick>` |
| ARIA | tylko tam, gdzie semantyka HTML nie wystarcza |
| Dialogi | focus trap, Esc zamyka, fokus wraca do elementu wyzwalającego |
| Formularze | etykieta zawsze, błąd przez `aria-describedby`, fokus na pierwszym błędzie |
| Obrazy | sensowny `alt` — nie `IMG_4471.jpg` |
| Ruch | `prefers-reduced-motion` respektowane |
| Cel dotykowy | min. 44 × 44 px na mobile |
| Język | `lang` ustawiony poprawnie |

### Lightbox — wzorzec obowiązkowy
```
←  →     poprzednie / następne zdjęcie
Esc      zamknięcie, fokus wraca do miniatury
F / Spacja  ulubione
Tab      porusza się wyłącznie wewnątrz lightboxa (focus trap)
```
Galeria zdjęć **musi** być w pełni obsługiwana z klawiatury. To nie jest opcjonalne.

## 4. Obsługa błędów — czego użytkownik nigdy nie zobaczy

```
✖ Undefined index      ✖ REST 500       ✖ SQLSTATE / SQL Error
✖ stack trace          ✖ ścieżka pliku na serwerze
```

Zamiast tego: walidacja inline · toast z akcją „Spróbuj ponownie” · stan błędu w obrębie sekcji
· pasek utraty połączenia · strona błędu z identyfikatorem zgłoszenia.

## 5. Puste stany

Każdy pusty ekran mówi: **co to jest · dlaczego warto · jeden konkretny następny krok.**
„Brak galerii” to niezaliczony ekran.

## 6. QA responsywności

Testuj realnie na: **375** · 414 · 768 · 1024 · 1440 px.
Galeria klienta jest projektowana najpierw na telefon, nie zwężana z desktopu.

Sprawdź: swipe · ulubione jednym kciukiem · wybór bez opuszczania widoku ·
checkout w jednej kolumnie · pobieranie bez ZIP-a · formularz rezerwacji.

## 7. Checklista przed uznaniem funkcji za skończoną

```
[ ] testy jednostkowe logiki domenowej
[ ] test izolacji tenantów (jeśli dotyka danych)
[ ] test uprawnień (jeśli dotyka API)
[ ] wszystkie stany komponentu: hover, focus, active, disabled, loading, error, pusty
[ ] obsługa z klawiatury
[ ] kontrast sprawdzony we wszystkich motywach
[ ] 375 px sprawdzone realnie
[ ] komunikaty błędów przyjazne i przetłumaczone
[ ] pusty stan pomaga, a nie informuje
[ ] budżet wydajności niezłamany
[ ] zero placeholderów, mocków i wymyślonych danych
```
