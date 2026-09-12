# SECURITY — Kadr

> Traktujemy produkt jak prawdziwy SaaS przechowujący **prywatne zdjęcia cudzych rodzin**.
> To nie są „pliki użytkownika”. To zdjęcia czyichś dzieci, ślubów i pogrzebów.

---

## 1. Model zagrożeń

| # | Zagrożenie | Wpływ | Główna obrona |
|---|---|---|---|
| T1 | Fotograf A odczytuje dane fotografa B (zmiana ID w URL/API) | **Katastrofalny** | `TenantContext` w repozytorium (konstrukcyjnie) + testy izolacji w CI |
| T2 | Wyciek prywatnego zdjęcia przez bezpośredni URL do pliku | **Katastrofalny** | brak publicznych ścieżek, podpisane URL-e z krótkim TTL |
| T3 | Zgadnięcie/enumeracja linku do galerii | Wysoki | tokeny ≥ 128 bitów entropii, hash w bazie, opcjonalny PIN, rate limiting |
| T4 | Powtórzony webhook realizuje zamówienie dwa razy | Wysoki | `UNIQUE(provider, external_event_id)` + maszyna stanów |
| T5 | Upload pliku wykonywalnego udającego zdjęcie | Wysoki | walidacja treści (nie nazwy), rewalidacja po stronie serwera, katalog bez wykonywania |
| T6 | Przejęcie sesji klienta / kradzież magic linku | Wysoki | jednorazowy token, krótki TTL, unieważnienie po użyciu, powiązanie z `user-agent` |
| T7 | Brute force logowania fotografa | Średni | throttling per IP i per konto, rosnące opóźnienie |
| T8 | Wyciek kluczy API operatora płatności fotografa | **Katastrofalny** | szyfrowanie kluczem z `wp-config.php`, brak w logach, brak w API |
| T9 | XSS przez treść wprowadzoną przez fotografa lub klienta | Wysoki | escaping na wyjściu, `wp_kses_post` dla treści bogatej, CSP |
| T10 | SQL injection | **Katastrofalny** | wyłącznie `$wpdb->prepare()`, zero interpolacji |
| T11 | Nadużycie darmowego planu / wyczerpanie zasobów | Średni | limity uploadu, limit współbieżności per tenant, sygnały w panelu admina |
| T12 | Nadużycie dostępu przez administratora platformy | Wysoki | impersonacja tylko za zgodą, zawsze w `audit_log`, widoczna dla fotografa |

---

## 2. Izolacja tenantów (T1)

Pięć warstw, opisanych w [`ARCHITECTURE.md`](ARCHITECTURE.md#2-multi-tenancy--jak-izolacja-jest-wymuszona).
Warstwą rozstrzygającą jest **repozytorium** — pozostałe cztery mogą zostać przez kogoś ominięte,
ta jedna nie, bo nie istnieje metoda pozwalająca pominąć tenanta.

**Zasada odpowiedzi:** próba dostępu do cudzego zasobu zwraca **404**, nigdy 403.
Nie potwierdzamy istnienia zasobów innego fotografa.

**Bramka CI:** `tests/TenantIsolation/` — dla każdego endpointu REST test, w którym tenant A
próbuje sięgnąć po zasób tenanta B. Brak testu dla nowego endpointu = niezaliczony build.

---

## 3. Ochrona plików (T2, T3)

```
❌ /wp-content/uploads/kadr/2026/03/_MG_4471.jpg        ← nigdy
✅ /d/{token}                    → walidacja → stream albo 302 do podpisanego URL-a
✅ https://storage…/previews/…?X-Amz-Expires=300        ← TTL 5 minut
```

- **Oryginały nie są serwowane nigdy** — ani fotografowi w przeglądarce, ani klientowi.
  Klient dostaje `view` (1800 px), a po opłaceniu — `finals` przez token pobrania.
- LocalStorage trzyma pliki **poza** `/uploads`, w katalogu z `deny from all` (Apache)
  i bez mapowania w serwerze (nginx). Dostęp wyłącznie przez PHP.
- W ścieżkach i identyfikatorach wyłącznie ULID — zero sekwencyjnych ID.
- Podglądy publiczne mają **usunięte EXIF/GPS**. Oryginał zachowuje metadane.
  Zdjęcie z sesji newborn z geolokalizacją domu to realne zagrożenie dla klienta, nie teoria.

### Tokeny

| Właściwość | Reguła |
|---|---|
| Generowanie | `random_bytes(32)`, kodowanie base62 |
| Przechowywanie | **hash** (`hash('sha256', …)`), nigdy sam token |
| Wygaśnięcie | zawsze, domyślnie: galeria 90 dni, pobranie 24 h, magic link 15 min |
| Limit użyć | konfigurowalny; magic link — jednorazowy |
| Unieważnienie | `revoked_at`, natychmiastowe |
| Log | każde użycie w `download_log` z zanonimizowanym IP |

---

## 4. Upload (T5)

```
1. Rozszerzenie z listy dozwolonych        (allowlist, nie blocklist)
2. Rzeczywisty typ MIME z zawartości       (finfo, nie nagłówek klienta)
3. Weryfikacja, że plik da się zdekodować  (getimagesize / Imagick identify)
4. Nazwa pliku generowana przez nas        — nazwa od klienta trafia tylko do kolumny opisowej
5. Limit rozmiaru pojedynczego pliku i limit na żądanie
6. Sprawdzenie entitlementu storage PRZED zapisem
7. Zapis do katalogu bez prawa wykonywania
```

**Nigdy nie ufamy nazwie pliku ani nagłówkowi `Content-Type`.**
Dozwolone: `jpg`, `jpeg`, `png`, `webp`, `heic`, `tif`, `tiff`, `dng` (lista rozszerzalna
per tenant tylko przez administratora platformy).

---

## 5. Uwierzytelnianie

| Podmiot | Mechanizm |
|---|---|
| Administrator platformy | WordPress, `manage_options` |
| Fotograf / zespół | WordPress user + capabilities `kadr_*` |
| Klient | własny store (ADR-003): `wp_hash_password()`, sesja w bazie, domyślnie magic link |
| Gość galerii | token + opcjonalny PIN/hasło, bez konta |

**Nie piszemy własnej kryptografii.** Hashowanie haseł przez API WordPressa.
Sesje klienta to podpisane ciasteczko + rekord w `client_sessions` (unieważnialny),
nie samodzielne JWT — token, którego nie da się unieważnić, jest w tym produkcie nieakceptowalny.

### Throttling (T7, T6)

| Endpoint | Limit |
|---|---|
| logowanie fotografa | 5 prób / 15 min / konto, 20 / 15 min / IP |
| magic link | 3 / 15 min / adres e-mail |
| PIN galerii | 10 prób / godz. / token, potem blokada z powiadomieniem fotografa |
| upload | limit współbieżnych zadań per tenant |
| webhook | limit per provider, z logowaniem odrzuceń |

---

## 6. REST API

- `permission_callback` **przy każdym** endpoincie. `__return_true` dozwolone wyłącznie dla
  publicznej treści marketingowej i jest wtedy opatrzone komentarzem z uzasadnieniem.
- Nonce (`X-WP-Nonce`) dla żądań z sesji przeglądarkowej — ochrona CSRF.
- Walidacja argumentów przez `args` z `validate_callback` i `sanitize_callback`, nie ręcznie.
- Odpowiedzi błędów bez szczegółów technicznych. Klient nigdy nie widzi
  `Undefined index`, `SQLSTATE`, ścieżek plików ani stack trace'ów.
- Paginacja kursorowa dla list o dużym wolumenie — brak `per_page=100000`.

---

## 7. Sekrety (T8)

| Sekret | Gdzie |
|---|---|
| Klucz szyfrujący platformy | `wp-config.php` → `KADR_ENCRYPTION_KEY` |
| Klucze API fotografa | `tenant_secrets`, zaszyfrowane powyższym kluczem |
| Sekrety webhooków platformy | `wp-config.php` albo zmienne środowiskowe |

- **Nigdy** w repozytorium, nigdy w `wp_options` plaintextem, nigdy w logach, nigdy w odpowiedzi API.
- Odczyt sekretu zawsze przez `Infrastructure\Security\Encryptor` — nigdy bezpośrednio z bazy.
- Rotacja klucza: procedura opisana w dokumentacji wdrożeniowej (Session 6).
- W UI sekrety pokazywane wyłącznie jako `••••1234`. Brak endpointu zwracającego pełną wartość.
- **Nigdy nie przechowujemy danych kart płatniczych.** W żadnej formie, w żadnej tabeli.

---

## 8. Webhooki płatności (T4)

```
1. Weryfikacja podpisu HMAC                → brak/niezgodny = 401, koniec
2. Tolerancja czasowa ±5 min               → ochrona przed replay
3. INSERT do payment_events (UNIQUE)       → duplikat = 200 OK, zero działania
4. Zakolejkowanie przetwarzania            → szybka odpowiedź dla operatora
5. Maszyna stanów: pending → processing → succeeded | failed
6. Retry z backoffem; po 5 nieudanych próbach alert w panelu admina
```

Webhook **nigdy** nie wykonuje logiki biznesowej w swoim requeście.

---

## 9. Wyjście i XSS (T9)

- `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()` — bez wyjątków.
- Treść bogata od fotografa (intro galerii, opis produktu) przez `wp_kses_post()` z zawężoną listą tagów.
- Komentarze klientów do zdjęć: **wyłącznie tekst**, zero HTML.
- Content-Security-Policy na stronach aplikacji i galerii, bez `unsafe-inline` dla skryptów.
- Nagłówki: `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`,
  `X-Frame-Options` (galeria może być osadzana tylko na domenie fotografa w planie Pro).

---

## 10. Audit log

Rejestrujemy **kto, co, kiedy** dla operacji o skutkach:

```
gallery.published        gallery.deleted         gallery.restored
client.invited           client.deleted          client.data_exported
order.status_changed     payment.received        refund.issued
download.token_issued    download.executed
team.member_added        team.capabilities_changed
settings.payment_keys_changed
admin.impersonation_started   admin.impersonation_ended
```

Zapisujemy aktora, tenanta, encję, różnicę wartości, zanonimizowane IP i czas.
**Nie zapisujemy:** treści zdjęć, haseł, sekretów, pełnych ładunków webhooków.
Retencja: 24 miesiące.

---

## 11. Wsparcie techniczne i impersonacja (T12)

Administrator platformy nie ma cichego wglądu w dane fotografa.
Impersonacja wymaga: jawnego uruchomienia → wpisu do `audit_log` → widocznego dla fotografa śladu
w jego panelu → automatycznego wygaśnięcia sesji. Bez tego wsparcie techniczne jest niemożliwe,
a z tym — audytowalne.

---

## 12. Checklista przed premierą (Session 6)

```
[ ] testy izolacji tenantów dla każdego endpointu REST
[ ] testy dostępu do plików (bezpośredni URL, wygasły token, cudzy token)
[ ] testy webhooków: powtórzenie, zły podpis, stary timestamp, nieznane zdarzenie
[ ] testy uprawnień dla każdej capability
[ ] test throttlingu logowania i magic linku
[ ] przegląd każdego permission_callback
[ ] przegląd każdego zapytania SQL pod kątem prepare()
[ ] przegląd każdego echo/print pod kątem escapingu
[ ] skan zależności (composer audit, npm audit)
[ ] weryfikacja, że żaden sekret nie trafił do repozytorium (historia gita)
[ ] weryfikacja nagłówków bezpieczeństwa i CSP
[ ] test scenariusza backup/restore
```
