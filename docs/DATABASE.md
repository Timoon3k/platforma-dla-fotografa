# DATABASE — Kadr

> **To jest projekt schematu, nie implementacja.** Tabele powstają w Session 3 (rdzeń),
> Session 4 (commerce, billing) i Session 5 (booking, automatyzacje).
> Decyzja źródłowa: ADR-005.

Prefiks: `{$wpdb->prefix}kadr_`. Silnik: InnoDB. Kolacja: `utf8mb4_unicode_520_ci`.

---

## 1. Dlaczego nie CPT

```
1000 fotografów × 60 galerii/rok × 300 zdjęć  = 18 000 000 zdjęć
~12 atrybutów na zdjęcie                      → ~216 000 000 wierszy w wp_postmeta

„Pokaż wybrane zdjęcia z galerii X”:
  CPT            → 6 JOIN-ów po tabeli EAV, pełne skanowanie meta
  custom tables  → jeden indeks złożony, jeden odczyt
```

---

## 2. Konwencje obowiązujące każdą tabelę

| Reguła | Powód |
|---|---|
| `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY` | wewnętrzny klucz, nigdy w URL-u |
| `public_id CHAR(26) NOT NULL UNIQUE` — ULID | brak enumerowalnych identyfikatorów; ULID sortuje się czasowo (UUIDv4 nie) |
| `tenant_id BIGINT UNSIGNED NOT NULL` | **w każdej** tabeli aplikacyjnej, również przy relacji pośredniej |
| każdy indeks zaczyna się od `tenant_id` | inaczej jest bezużyteczny w zapytaniach wielotenantowych |
| `created_at`, `updated_at` `DATETIME NOT NULL` | UTC, zawsze |
| `deleted_at DATETIME NULL` | soft delete (30-dniowy kosz) tam, gdzie utrata boli |
| kwoty: `amount_minor INT` + `currency CHAR(3)` | grosze jako liczba całkowita. **Nigdy FLOAT** |
| statusy: `VARCHAR(32)` + enum w PHP | czytelne w bazie, typowane w kodzie |
| dane JSON: `LONGTEXT` z walidacją w PHP | brak zależności od wersji MySQL; nie indeksujemy po JSON-ie |

---

## 3. ERD — rdzeń

```
                              ┌───────────┐
                              │  tenants  │◄─── tenant_users ───► wp_users
                              └─────┬─────┘
                                    │
   ┌───────────┬───────────┬────────┼────────┬───────────┬──────────────┐
   ▼           ▼           ▼        ▼        ▼           ▼              ▼
┌────────┐ ┌─────────┐ ┌──────┐ ┌────────┐ ┌────────┐ ┌─────────────┐ ┌───────────┐
│clients │ │galleries│ │orders│ │products│ │services│ │subscriptions│ │automations│
└───┬────┘ └────┬────┘ └──┬───┘ └───┬────┘ └───┬────┘ └─────────────┘ └───────────┘
    │           │         │         │          │
    │           ▼         ▼         ▼          ▼
    │  ┌──────────────┐ ┌──────────┐ ┌────────────────┐ ┌──────────┐
    │  │gallery_assets│ │order_items│ │product_variants│ │ bookings │
    │  └──────┬───────┘ └──────────┘ └────────────────┘ └────┬─────┘
    │         │                                               │
    │         ├──► asset_variants                             │
    │         └──► asset_comments                             │
    │                                                          │
    │  ┌───────────────┐                                       │
    │  │gallery_access │  (tokeny, PIN, zaproszenia)           │
    │  └───────────────┘                                       │
    │                                                          │
    └──────────► selections ──► selection_items ◄──────────────┘
```

---

## 4. Domeny i tabele

### ① Tenancy i billing

| Tabela | Zawartość |
|---|---|
| `tenants` | studio: nazwa, slug, strefa czasowa, waluta, status (`active`/`readonly`/`paused`), plan |
| `tenant_users` | członkostwo: `tenant_id`, `wp_user_id`, rola, capabilities (JSON), `invited_at`, `accepted_at` |
| `tenant_settings` | ustawienia klucz–wartość per tenant |
| `tenant_branding` | logo, kolory, motyw galerii, stopka, nadawca e-mail |
| `tenant_secrets` | **zaszyfrowane** klucze API operatorów płatności fotografa |
| `subscriptions` | plan, cykl, status, `current_period_end`, `external_subscription_id`, `cancel_at` |
| `subscription_invoices` | faktury platformy: kwota, status, URL, okres |
| `entitlement_overrides` | wyjątki od planu (`tenant_id`, `key`, `value`, `expires_at`, `reason`) |
| `usage_counters` | `storage_bytes`, `active_galleries`, `clients`, `team_seats` — **liczone przyrostowo** |
| `feature_flags` | flaga, zakres (globalny / per tenant), stan |

**Plany żyją w kodzie** (`docs/BILLING.md`), nie w bazie. W bazie są wyłącznie odstępstwa.

### ② CRM

| Tabela | Zawartość |
|---|---|
| `clients` | `tenant_id`, imię, nazwisko, e-mail, telefon, źródło, notatka, `deleted_at` |
| `client_sessions` | uwierzytelnianie klienta (ADR-003): `token_hash`, `expires_at`, `ip_hash`, `revoked_at` |
| `client_notes` | notatki fotografa, autor, treść |
| `client_consents` | rodzaj zgody, stan (`granted`/`withdrawn`), znacznik czasu, dowód, zakres (np. wizerunek) |

`clients` ma `UNIQUE(tenant_id, email)` — ta sama osoba może być klientką wielu fotografów.
To jest dokładnie powód, dla którego klienci nie mogą mieszkać w `wp_users` (ADR-003).

### ③ Galerie

| Tabela | Kluczowe kolumny |
|---|---|
| `galleries` | `tenant_id`, `client_id`, tytuł, slug, `cover_asset_id`, motyw, intro, status (`draft`/`published`/`expired`/`archived`), `expires_at`, `package_limit`, `extra_photo_price_minor`, `allow_download`, `watermark`, `lifecycle_state` |
| `gallery_assets` | `tenant_id`, `gallery_id`, `public_id`, nazwa oryginalna, `storage_path`, `content_hash`, `bytes`, `width`, `height`, `taken_at`, `sort_order`, `status` |
| `asset_variants` | `asset_id`, `variant` (`thumb`/`grid`/`view`/`wm`), `format` (`avif`/`webp`/`jpeg`), `storage_path`, `bytes`, `width` |
| `gallery_access` | `gallery_id`, typ (`link`/`pin`/`password`/`invite`), `token_hash`, `pin_hash`, `expires_at`, `max_uses`, `used_count`, `revoked_at` |
| `gallery_events` | `gallery_id`, typ (`opened`/`viewed`/`shared`), `occurred_at`, zanonimizowany identyfikator odwiedzającego |

### ④ Proofing

| Tabela | Kluczowe kolumny |
|---|---|
| `selections` | `tenant_id`, `gallery_id`, `client_id`, status (`open`/`submitted`/`reopened`), `submitted_at`, `included_count`, `extra_count` |
| `selection_items` | `selection_id`, `asset_id`, `state` (`favorite`/`selected`/`rejected`), `position` |
| `asset_comments` | `asset_id`, autor (klient/fotograf), treść, `created_at` |

### ⑤ Commerce (klient → fotograf)

| Tabela | Kluczowe kolumny |
|---|---|
| `products` | typ (`extra_photos`/`print`/`album`/`canvas`/`custom`), nazwa, opis, status |
| `product_options` | np. „Format”, „Papier” — `product_id`, nazwa, `position` |
| `product_option_values` | np. „13×18”, „mat” |
| `product_variants` | `sku`, `price_minor`, `stock` (nullable), `weight` |
| `variant_option_values` | tabela łącząca wariant z wartościami opcji |
| `orders` | `tenant_id`, `client_id`, `gallery_id` (nullable), numer, status, sumy, waluta, dane dostawy |
| `order_items` | typ, `variant_id` lub `asset_id`, ilość, cena jednostkowa, suma |
| `payments` | `order_id`, `provider`, `external_id`, status, kwota, `paid_at` |
| `payment_events` | `provider`, `external_event_id`, ładunek, `processed_at`, `error` |
| `discounts` | kod, typ, wartość, warunki, limit użyć, ważność |

Zamówienia obsługują trzy źródła: zadatek za rezerwację, dopłata za nadmiarowe zdjęcia,
odbitki i produkty. Jedna tabela, trzy `order_items.type`.

### ⑥ Booking

| Tabela | Kluczowe kolumny |
|---|---|
| `services` | nazwa, czas trwania, cena, zadatek, lokalizacja, bufory przed/po, opis |
| `availability_rules` | dzień tygodnia, od–do, `service_id` (nullable), `member_id` (nullable) |
| `availability_exceptions` | blokady, urlopy, pojedyncze terminy dodatkowe |
| `bookings` | `client_id`, `service_id`, `starts_at`, `ends_at`, status, `order_id` (zadatek), lokalizacja, notatki |

### ⑦ System

| Tabela | Kluczowe kolumny |
|---|---|
| `notifications` | odbiorca, kanał, szablon, dane, status, `sent_at`, `error` |
| `notification_templates` | per tenant, edytowalne, z wersją domyślną |
| `automations` | zdarzenie, warunki (JSON), akcja, aktywna |
| `automation_runs` | `automation_id`, kontekst, wynik, `ran_at` |
| `download_tokens` | `token_hash`, zakres (galeria/zdjęcie), `expires_at`, `max_downloads`, `used`, `revoked_at` |
| `download_log` | token, co pobrano, kiedy, zanonimizowane IP |
| `audit_log` | `tenant_id`, aktor, akcja, typ i id encji, zmiany (JSON), IP, `created_at` |
| `job_log` | zadanie, status, próby, błąd, czas trwania |

---

## 5. Indeksy krytyczne

```sql
-- wolumen: najczęstsze zapytania galerii
gallery_assets     (tenant_id, gallery_id, sort_order)
gallery_assets     (tenant_id, content_hash)          -- deduplikacja przy uploadzie
asset_variants     (asset_id, variant, format)

-- proofing
selection_items    (selection_id, asset_id) UNIQUE    -- brak podwójnych wyborów
selection_items    (tenant_id, selection_id, state)

-- commerce
orders             (tenant_id, status, created_at)
orders             (tenant_id, client_id)
payment_events     (provider, external_event_id) UNIQUE   -- ⚠ idempotencja webhooków
payments           (order_id, status)

-- booking: wykrywanie kolizji terminów
bookings           (tenant_id, starts_at, status)
availability_rules (tenant_id, weekday)

-- dostęp i bezpieczeństwo
gallery_access     (token_hash) UNIQUE
download_tokens    (token_hash) UNIQUE
client_sessions    (token_hash) UNIQUE
clients            (tenant_id, email) UNIQUE

-- operacyjne
audit_log          (tenant_id, created_at)
audit_log          (entity_type, entity_id)
usage_counters     (tenant_id, metric) UNIQUE
```

`payment_events (provider, external_event_id) UNIQUE` to jedyny mechanizm gwarantujący,
że powtórzony webhook nie zrealizuje zamówienia dwa razy. **Wymuszenie na poziomie bazy,
nie na poziomie kodu** — kod da się ominąć wyścigiem, klucz unikalny nie.

---

## 6. Liczniki zużycia

`usage_counters` aktualizowane **przyrostowo** przy każdej operacji (upload, usunięcie,
publikacja galerii), nie przez `SUM()` na żądanie.

Powód: zapytanie `SELECT SUM(bytes) FROM gallery_assets WHERE tenant_id = ?` przy 18 mln wierszy
wykonywane przy każdym wejściu do dashboardu jest nie do utrzymania.

Zadanie `RecalculateUsage` uruchamiane nocnie weryfikuje liczniki i koryguje rozjazdy,
zapisując różnicę do `job_log`.

---

## 7. Soft delete i kosz

Soft delete (`deleted_at`) obejmuje: `galleries`, `gallery_assets`, `clients`, `products`, `bookings`.

- Fotograf ma **30 dni** na przywrócenie z kosza.
- Po 30 dniach zadanie w tle usuwa rekordy i powiązane pliki.
- Usunięcie tenanta nigdy nie jest natychmiastowe — okno karencji + powiadomienia.
- Żądanie usunięcia danych w trybie RODO ma ścieżkę **twardego** usunięcia, poza tym mechanizmem,
  z wpisem do `audit_log`.

---

## 8. Migracje

```
Infrastructure/Database/Migrations/
├── Migration_0001_Tenancy.php
├── Migration_0002_Clients.php
├── Migration_0003_Galleries.php
└── …
```

- Wersja w `wp_options` → `kadr_schema_version`
- Uruchamiane przy aktywacji, aktualizacji wtyczki i przez świadomą akcję administratora.
  **Nigdy przy każdym requeście.**
- Każda migracja: `up()`, opis skutku, i `down()` jeśli wycofanie jest wykonalne
- **Żadna migracja nie usuwa danych użytkownika bez jawnej decyzji administratora**
- Duże zmiany na tabelach o wysokim wolumenie wykonywane wsadowo w kolejce, nie w jednym `ALTER`

---

## 9. Szacowany wolumen (1000 fotografów, rok 1)

| Tabela | Rzędy wielkości |
|---|---|
| `gallery_assets` | ~18 mln |
| `asset_variants` | ~108 mln (6 wariantów na zdjęcie) |
| `selection_items` | ~3 mln |
| `gallery_events` | ~5 mln (agregowane i przycinane po 90 dniach) |
| `orders` | ~120 tys. |
| `audit_log` | ~2 mln (retencja 24 miesiące) |

`asset_variants` to największa tabela w systemie. Rozważyć w Session 6: przeniesienie wariantów
do kolumny JSON w `gallery_assets`, jeśli pomiary wykażą, że osobna tabela nie daje korzyści —
warianty są zawsze odczytywane razem ze zdjęciem i nigdy nie są przedmiotem samodzielnych zapytań.
**Decyzja na podstawie pomiaru, nie przeczucia.**
