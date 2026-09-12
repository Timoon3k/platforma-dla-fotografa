# BILLING — plany, entitlementy, free tier

> Decyzje źródłowe: ADR-007 (Stripe Billing), ADR-008 (cennik i free tier).
> **Dwie rozłączne domeny płatnicze** — patrz ADR-006. Ten dokument opisuje wyłącznie
> relację *fotograf → platforma*. Sprzedaż *klient → fotograf* to moduł `Commerce\`.

---

## 1. Zasada naczelna: zero `if ($plan === 'pro')`

Plany **nigdy** nie są sprawdzane po nazwie. Jedyny dopuszczalny sposób odpytania:

```php
if ( ! $entitlements->allows('white_label') ) { … }
if ( $entitlements->limit('gallery_limit')->isReachedBy($current) ) { … }
```

Jeden rejestr planów (`Domain\Billing\PlanRegistry`), jedno miejsce z cenami.
**Cena nie może pojawić się w dwóch plikach.** Strona cennika czyta ten sam rejestr,
co silnik limitów — inaczej po pierwszej zmianie cennika marketing rozjedzie się z produktem.

---

## 2. Klucze entitlementów

| Klucz | Typ | Opis |
|---|---|---|
| `gallery_limit` | int \| ∞ | aktywne galerie (nie: utworzone kiedykolwiek) |
| `storage_limit_bytes` | int | twardy limit |
| `client_limit` | int \| ∞ | |
| `team_seats` | int | |
| `projects_free` | int | dotyczy wyłącznie free tier |
| `sell_extra_photos` | bool | sprzedaż zdjęć ponad pakiet |
| `products_prints` | bool | produkty i odbitki |
| `bookings` | bool | |
| `automations` | enum | `basic` \| `full` \| `advanced` |
| `gallery_themes` | int \| ∞ | |
| `hide_platform_branding` | bool | |
| `custom_domain` | bool | |
| `custom_email_sender` | bool | |
| `analytics_level` | enum | `basic` \| `sales` \| `full` |
| `api_access` | bool | |
| `sms_credits` | int | |
| `support_level` | enum | `email` \| `priority` \| `priority_onboarding` |

---

## 3. Plany

| Klucz | Free | Starter | Studio | Pro |
|---|---|---|---|---|
| cena / mies. | 0 | 69 zł | **149 zł** | 299 zł |
| cena / rok | — | 690 zł | 1 490 zł | 2 990 zł |
| `gallery_limit` | — | 30 | 150 | ∞ |
| `projects_free` | **5** | — | — | — |
| `storage_limit_bytes` | 5 GB | 50 GB | 250 GB | 1 TB |
| `client_limit` | 25 | 300 | ∞ | ∞ |
| `team_seats` | 1 | 1 | 3 | 10 |
| `sell_extra_photos` | ✓ | ✓ | ✓ | ✓ |
| `products_prints` | — | — | ✓ | ✓ |
| `bookings` | ✓ | ✓ | ✓ | ✓ |
| `automations` | basic | basic | full | advanced |
| `gallery_themes` | 1 | 2 | 5 | ∞ |
| `hide_platform_branding` | — | — | ✓ | ✓ |
| `custom_domain` | — | — | — | ✓ |
| `custom_email_sender` | — | — | — | ✓ |
| `analytics_level` | basic | basic | sales | full |
| `api_access` | — | — | — | ✓ |
| `support_level` | email | email | priority | priority + onboarding |

Plan roczny = **dwa miesiące gratis** (−17%).

**Free tier ma włączoną sprzedaż dodatkowych zdjęć.** To nie jest przeoczenie — fotograf musi
przeżyć moment, w którym klient dopłaca. To jest cały argument sprzedażowy produktu.

---

## 4. Dodatki (recurring)

| Dodatek | Cena | Klucz |
|---|---|---|
| +100 GB storage | 25 zł / mies. | `storage_limit_bytes` += |
| dodatkowy użytkownik | 29 zł / mies. | `team_seats` += 1 |
| własna domena | 19 zł / mies. | `custom_domain` = true |
| pakiet SMS (250) | 39 zł / mies. | `sms_credits` += 250 |
| motywy premium | 29 zł / mies. | `gallery_themes` = ∞ |
| reaktywacja galerii | 9 zł jednorazowo | odarchiwizowanie |

Dodatki modyfikują entitlementy przez `entitlement_overrides` — nigdy przez zmianę planu.

---

## 5. Free tier — cykl życia

```
REJESTRACJA        5 projektów, 5 GB, bez karty, bez limitu czasu
      │
      │  projekt = galeria z co najmniej jednym zdjęciem, wysłana klientowi
      ▼
3 z 5 WYKORZYSTANE     dyskretna informacja w dashboardzie. Bez licznika odliczającego
      ▼
5 z 5 WYKORZYSTANE     konto → READ-ONLY
                       ✅ istniejące galerie klientów DZIAŁAJĄ DALEJ
                       ✅ klienci pobierają opłacone zdjęcia
                       ✅ zamówienia w toku realizują się
                       ❌ brak nowych projektów i nowych uploadów
      │
      │  dane przechowywane min. 12 miesięcy, powiadomienia na 30 i 7 dni przed
      ▼
UPGRADE            natychmiastowe odblokowanie, zero utraty danych
```

**Dark patterns zakazane:** liczniki odliczające · blokada pobierania opłaconych zdjęć ·
ciche wygaszanie danych · cena widoczna dopiero po podaniu karty · anulowanie przez e-mail do wsparcia.

**Uzasadnienie modelu:** cykl „sesja → galeria → wybór → dopłata → dostawa” trwa 2–6 tygodni.
Trial 14-dniowy strukturalnie uniemożliwia zobaczenie wartości produktu.

---

## 6. Przekroczenia limitów

| Limit | Zachowanie |
|---|---|
| Storage | upload blokowany **przed** rozpoczęciem wysyłki, z propozycją dodatku. Istniejące pliki nietknięte |
| Galerie | nowa galeria blokowana; archiwizacja starej zwalnia miejsce |
| Klienci | blokada dodania nowego |
| Seaty | blokada zaproszenia |
| SMS | przełączenie na e-mail + powiadomienie |

**Nigdy nie usuwamy danych z powodu przekroczenia limitu.** Nigdy nie wyłączamy galerii,
którą klient końcowy już opłacił.

---

## 7. Pauza konta — mechanizm anty-churnowy

Listopad–luty to zapaść w fotografii rodzinnej. Fotograf, który anuluje, zwykle nie wraca.

```
PAUZA   29 zł / mies.
        pliki zachowane, galerie klientów działają w trybie tylko-do-odczytu,
        fotograf nie tworzy nowych projektów
        wznowienie jednym kliknięciem, bez utraty czegokolwiek
```

To najtańszy mechanizm retencyjny, jaki możemy zbudować. Jest tańszy niż pozyskanie nowego klienta.

---

## 8. Zmiany planu

| Operacja | Zachowanie |
|---|---|
| Upgrade | natychmiast, proporcjonalne rozliczenie po stronie Stripe |
| Downgrade | **od kolejnego okresu** — bez nagłej utraty dostępu |
| Downgrade z przekroczeniem | ostrzeżenie **przed** potwierdzeniem, z listą konkretów („masz 180 galerii, plan Studio dopuszcza 150”) |
| Anulowanie | samodzielne, w dwóch kliknięciach. Dostęp do końca opłaconego okresu, potem read-only |
| Po anulowaniu | dane min. 12 miesięcy, z powiadomieniami |

---

## 9. Billing UX — co fotograf musi zobaczyć bez szukania

```
Plan i cykl rozliczeniowy       Następna płatność: kwota i data
Zużycie: storage / galerie / klienci / seaty — z paskami postępu
Faktury do pobrania             Zmiana planu · Dodatki · Pauza · Anulowanie
```

Anulowanie nie może wymagać napisania e-maila do wsparcia.
Zużycie storage podane w GB, nie w bajtach, i zestawione z limitem.

---

## 10. Faktury VAT (PL)

MVP: eksport danych rozliczeniowych do CSV/JSON + faktury generowane przez Stripe.
Integracja z Fakturownią / wFirmą: **post-MVP** (`ROADMAP.md`, faza 1.3).
Nie udajemy systemu księgowego — ale fotograf o to zapyta, więc odpowiedź musi być w FAQ.

---

## 11. Metryki, które musi widzieć administrator platformy

```
MRR · ARR · liczba kont free · konwersja free → paid · churn miesięczny
ARPU · LTV · konta w pauzie · nieudane płatności
KOSZT STORAGE PER TENANT ZESTAWIONY Z PRZYCHODEM PER TENANT
```

Ostatnia metryka jest najważniejsza operacyjnie: jeden fotograf ślubny generuje tyle danych,
co dziesięciu rodzinnych. Bez tego zestawienia nie da się zauważyć, że plan Pro przestał być rentowny.
