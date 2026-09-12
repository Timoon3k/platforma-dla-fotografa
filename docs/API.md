# API — Kadr REST v1

> Namespace: `kadr/v1` · Baza: `/wp-json/kadr/v1/`
> REST API jest **jedynym kontraktem danych** dla dashboardu i portalu klienta.
> Te same endpointy zostaną wystawione publicznie w planie Pro — projektujemy je tak,
> jakby ktoś z zewnątrz miał je czytać. Bo będzie.

---

## 1. Zasady

1. **`permission_callback` przy każdym endpoincie.** `__return_true` wyłącznie dla publicznej
   treści marketingowej, zawsze z komentarzem uzasadniającym.
2. Walidacja przez `args` z `validate_callback` i `sanitize_callback` — nie ręcznie w handlerze.
3. Zasoby w liczbie mnogiej, rzeczowniki, bez czasowników w ścieżce.
4. Identyfikatory w URL-ach to **ULID** (`public_id`), nigdy sekwencyjne `id`.
5. Odpowiedź zawsze opakowana: `{ "data": …, "meta": … }`.
6. Paginacja **kursorowa** dla list o dużym wolumenie.
7. Brak cudzego zasobu = **404**, nie 403 — nie potwierdzamy istnienia danych innego tenanta.
8. Wersjonowanie przez namespace. `v2` powstanie obok `v1`, nie zamiast.

---

## 2. Uwierzytelnianie

| Konsument | Mechanizm |
|---|---|
| Dashboard fotografa | ciasteczko WP + `X-WP-Nonce` |
| Portal klienta | ciasteczko sesji klienta + nonce |
| Galeria (gość) | token galerii w nagłówku `X-Kadr-Access` |
| Integracje (Pro, post-MVP) | klucz API per tenant, nagłówek `Authorization: Bearer` |

---

## 3. Format odpowiedzi

**Sukces**
```json
{
  "data": { "id": "01HQ8…", "title": "Sesja rodzinna — Kowalscy", "status": "published" },
  "meta": { "request_id": "01HQ9…" }
}
```

**Lista**
```json
{
  "data": [ … ],
  "meta": {
    "count": 50,
    "next_cursor": "01HQ8ZXK…",
    "has_more": true
  }
}
```

**Błąd**
```json
{
  "code": "kadr_limit_reached",
  "message": "Osiągnięto limit aktywnych galerii w Twoim planie.",
  "details": { "limit": 30, "current": 30, "upgrade_to": "studio" }
}
```

`message` jest przetłumaczony i nadaje się do pokazania użytkownikowi.
`details` jest opcjonalne i **nigdy nie zawiera** informacji technicznych o serwerze.

### Kody statusu

| Kod | Znaczenie |
|---|---|
| 200 / 201 | sukces |
| 202 | przyjęto, przetwarzanie w kolejce (upload, ZIP, eksport) |
| 400 | błąd walidacji |
| 401 | brak uwierzytelnienia |
| 403 | uwierzytelniony, ale bez uprawnienia |
| 404 | nie istnieje **albo nie należy do tego tenanta** |
| 409 | konflikt (np. termin zajęty, wybór już zatwierdzony) |
| 422 | poprawne dane, naruszona reguła biznesowa (np. limit planu) |
| 429 | throttling |
| 500 | błąd serwera — bez szczegółów w odpowiedzi, z `request_id` |

---

## 4. Zarys endpointów

### Galerie
```
GET    /galleries                       lista (filtry: status, client, cursor)
POST   /galleries                       utworzenie
GET    /galleries/{id}
PATCH  /galleries/{id}
DELETE /galleries/{id}                  soft delete
POST   /galleries/{id}/publish
POST   /galleries/{id}/restore
GET    /galleries/{id}/assets           kursorowo
POST   /galleries/{id}/assets           inicjacja uploadu → 202
POST   /galleries/{id}/assets/{aid}/chunks
PATCH  /galleries/{id}/assets/order
GET    /galleries/{id}/access
POST   /galleries/{id}/access           wygenerowanie linku/PIN-u
DELETE /galleries/{id}/access/{tid}     unieważnienie
GET    /galleries/{id}/stats
```

### Wybór (proofing)
```
GET    /galleries/{id}/selection
PUT    /galleries/{id}/selection/items/{aid}     stan: favorite|selected|rejected
DELETE /galleries/{id}/selection/items/{aid}
POST   /galleries/{id}/selection/submit          → zwraca podsumowanie i ew. kwotę dopłaty
POST   /galleries/{id}/selection/reopen          tylko fotograf
POST   /assets/{id}/comments
```

### Commerce
```
GET    /products      POST /products      PATCH /products/{id}
GET    /products/{id}/variants            POST /products/{id}/variants
POST   /cart/items    DELETE /cart/items/{id}    GET /cart
POST   /orders                            utworzenie z koszyka lub z wyboru
GET    /orders        GET /orders/{id}    PATCH /orders/{id}/status
POST   /orders/{id}/payment               inicjacja płatności → URL operatora
POST   /webhooks/payments/{provider}      publiczny, weryfikacja podpisu
```

### Rezerwacje
```
GET    /services      POST /services      PATCH /services/{id}
GET    /availability?service={id}&from=&to=        publiczny dla strony rezerwacji
POST   /bookings                                    utworzenie + zadatek
GET    /bookings      PATCH /bookings/{id}          przełożenie, anulowanie
```

### Klienci i CRM
```
GET    /clients       POST /clients       GET /clients/{id}
PATCH  /clients/{id}  DELETE /clients/{id}
GET    /clients/{id}/timeline             sesje, galerie, zamówienia, wiadomości
POST   /clients/{id}/notes
GET    /clients/{id}/consents             POST /clients/{id}/consents
POST   /clients/{id}/export               RODO → 202
POST   /clients/{id}/erase                RODO → wymaga potwierdzenia
```

### Konto i rozliczenia
```
GET    /me                                tenant, plan, entitlementy, zużycie
GET    /usage
GET    /billing/subscription              POST /billing/subscription/change
POST   /billing/subscription/pause        POST /billing/subscription/cancel
GET    /billing/invoices
GET    /onboarding                        stan checklisty
```

### Dostawa
```
POST   /galleries/{id}/finals             upload gotowych plików
POST   /galleries/{id}/deliver            → uruchamia „Odsłonę” i powiadomienie
POST   /downloads/tokens                  wygenerowanie tokenu
GET    /downloads/{token}                 publiczny, walidacja tokenu
```

### Platforma (administrator)
```
GET    /platform/tenants     GET /platform/metrics     GET /platform/storage
GET    /platform/queue       GET /platform/webhooks    GET /platform/health
POST   /platform/impersonate                 → zawsze do audit logu
```

---

## 5. Operacje asynchroniczne

Operacje długie zwracają **202** z identyfikatorem zadania:

```json
{ "data": { "job_id": "01HQ…", "status": "queued" },
  "meta": { "poll": "/wp-json/kadr/v1/jobs/01HQ…" } }
```

`GET /jobs/{id}` → `queued | running | done | failed` z postępem (`247/800`).
Dotyczy: uploadu i przetwarzania zdjęć, ZIP-ów, eksportu RODO, importów, wysyłek masowych.

---

## 6. Rate limiting

| Grupa | Limit |
|---|---|
| logowanie, magic link | patrz [`SECURITY.md`](SECURITY.md#5-uwierzytelnianie) |
| PIN galerii | 10 / godz. / token |
| upload | limit współbieżności per tenant |
| odczyty (dashboard) | 600 / min / tenant |
| webhooki | per provider, z logowaniem odrzuceń |

Przekroczenie → **429** z nagłówkiem `Retry-After`.

---

## 7. Czego nie robimy

```
❌ permission_callback => '__return_true' dla danych prywatnych
❌ sekwencyjne ID w URL-ach
❌ per_page bez górnego limitu
❌ zwracanie sekretów, hashy i pełnych ładunków webhooków
❌ komunikaty błędów ze ścieżkami plików, SQL-em i stack trace'ami
❌ zmiana kształtu odpowiedzi w obrębie v1 (breaking change = v2)
❌ logika biznesowa wykonywana w requeście webhooka
```
