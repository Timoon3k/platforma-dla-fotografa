# ARCHITECTURE — Kadr

> Decyzje źródłowe: ADR-002, ADR-003, ADR-004, ADR-005 w [`DECISIONS.md`](DECISIONS.md).

---

## 1. Warstwy

```
┌───────────────────────────────────────────────────────────────────────┐
│ PRESENTATION                                                          │
│  • bloki Gutenberga (block.json + render PHP + Interactivity API)     │
│  • /app   dashboard fotografa                                         │
│  • /k     portal klienta                                              │
│  • /g     galeria (gość)                                              │
│  • /b     strona rezerwacji                                           │
│  • REST controllers (kadr/v1)                                         │
│  Cienka warstwa. Zero logiki biznesowej. Tłumaczy HTTP ↔ use case.    │
├───────────────────────────────────────────────────────────────────────┤
│ APPLICATION                                                           │
│  Use case'y: CreateGallery, UploadAsset, SubmitSelection,             │
│  CreateOrder, CapturePayment, BookSession, IssueDownloadToken…        │
│  Orkiestracja, transakcje, zdarzenia domenowe, autoryzacja operacji.  │
├───────────────────────────────────────────────────────────────────────┤
│ DOMAIN                                                                │
│  Encje i reguły: Gallery, Asset, Selection, Order, Booking,           │
│  Subscription, Entitlements, TenantContext, Money, DownloadToken…     │
│  ⚠ ZERO kodu WordPressa. Testowalne bez ładowania WP.                │
├───────────────────────────────────────────────────────────────────────┤
│ INFRASTRUCTURE                                                        │
│  $wpdb repozytoria · Storage (Local/S3) · Payments (adaptery) ·       │
│  Mail · Queue (Action Scheduler) · Cache · WP Users · Image pipeline ·│
│  Logger · Encryption · Migrations                                     │
└───────────────────────────────────────────────────────────────────────┘

Zależności wskazują tylko w dół. Domain nie zna nikogo.
```

### Struktura katalogów

```
src/
├── Domain/
│   ├── Tenancy/        Tenant, TenantContext, TenantUser, Role, Capability
│   ├── Client/         Client, ClientIdentity, Consent
│   ├── Gallery/        Gallery, Asset, AssetVariant, GalleryAccess, GalleryTheme
│   ├── Selection/      Selection, SelectionItem, SelectionState, PackageLimit
│   ├── Commerce/       Product, Variant, Order, OrderItem, Payment, Money, Discount
│   ├── Booking/        Service, Availability, Booking, TimeSlot
│   ├── Billing/        Plan, Entitlements, Subscription, UsageCounter
│   ├── Journey/        JourneyStage, JourneyTimeline
│   ├── Notification/   Notification, NotificationTemplate, Channel
│   ├── Automation/     AutomationRule, Trigger, Action
│   ├── Storage/        StorageProviderInterface, StoragePath, ObjectLifecycle
│   ├── Queue/          QueueInterface, Job
│   └── Shared/         Ulid, Clock, Result, DomainEvent, ValidationError
│
├── Application/
│   ├── Gallery/        CreateGallery, PublishGallery, ExpireGallery…
│   ├── Selection/      StartSelection, ToggleFavorite, SubmitSelection…
│   ├── Commerce/       AddToCart, PlaceOrder, HandlePaymentWebhook…
│   ├── Booking/        ListSlots, CreateBooking, CancelBooking…
│   ├── Billing/        ChangePlan, PauseAccount, RecordUsage…
│   └── Shared/         CommandBus, EventDispatcher, TransactionRunner
│
├── Infrastructure/
│   ├── Database/       WpdbGalleryRepository, Migrations, SchemaVersion…
│   ├── Storage/        LocalStorage, S3Storage, SignedUrlFactory
│   ├── Payments/       PaymentGatewayInterface, PayNowGateway, StripeGateway, FakeGateway
│   ├── Image/          ImagickPipeline, GdFallback, ExifStripper, Watermarker
│   ├── Queue/          ActionSchedulerQueue
│   ├── Mail/           WpMailer, TemplateRenderer
│   ├── Auth/           PhotographerAuth (WP users), ClientAuth (własny), MagicLink
│   ├── Security/       Encryptor, TokenHasher, RateLimiter, AuditLogger
│   └── WordPress/      Hooks, Capabilities, Rewrites, Cron, Uninstall
│
└── Presentation/
    ├── Rest/           kontrolery kadr/v1
    ├── Blocks/         bloki Gutenberga
    ├── App/            dashboard fotografa (szablony + wyspy JS)
    ├── Portal/         portal klienta
    ├── Gallery/        galeria dla gościa
    └── Admin/          ekrany administratora platformy (WP Admin)
```

---

## 2. Multi-tenancy — jak izolacja jest wymuszona

Tenant = fotograf albo studio. **Każda** tabela aplikacyjna ma `tenant_id`, również tam, gdzie
wynikałby z relacji (denormalizacja celowa — pozwala filtrować bez JOIN-a).

```php
final class WpdbGalleryRepository implements GalleryRepository {
    public function __construct(
        private \wpdb $db,
        private TenantContext $tenant,   // ← wstrzyknięty, nie przekazywany w wywołaniu
    ) {}

    public function find(Ulid $id): ?Gallery {
        $row = $this->db->get_row($this->db->prepare(
            "SELECT * FROM {$this->table} WHERE public_id = %s AND tenant_id = %d AND deleted_at IS NULL",
            (string) $id,
            $this->tenant->id(),      // ← zawsze, bez wyjątku
        ));
        return $row ? Gallery::fromRow($row) : null;
    }
}
```

**Reguła:** nie może istnieć metoda repozytorium pozwalająca pominąć tenanta.
Nie ma `findAny()`, nie ma `$ignoreTenant = true`. Jedyny wyjątek to repozytoria administratora
platformy w osobnym namespace `Infrastructure\Database\Platform\`, oznaczone jawnie
i objęte audit logiem.

**Warstwy obrony:**

| Warstwa | Mechanizm |
|---|---|
| 1. Routing | `/app/*` wymaga zalogowanego fotografa z aktywnym tenantem |
| 2. REST `permission_callback` | capability + przynależność do tenanta |
| 3. Use case | sprawdzenie własności zasobu przed operacją |
| 4. Repozytorium | `WHERE tenant_id = ?` wymuszone konstrukcyjnie |
| 5. Testy | dedykowany zestaw `tests/TenantIsolation/` jako bramka CI |

Fotograf A, zmieniając ID w URL-u lub w żądaniu API, dostaje **404**, nigdy 403 —
nie potwierdzamy istnienia cudzego zasobu.

---

## 3. Routing

| Ścieżka | Kto | Uwierzytelnienie |
|---|---|---|
| `/` i strony marketingowe | wszyscy | brak |
| `/rejestracja`, `/logowanie` | wszyscy | brak (throttling) |
| `/app/*` | fotograf, członek zespołu | WP user + capability `kadr_access_app` |
| `/k/*` | klient | sesja klienta (ADR-003) |
| `/g/{slug}-{token}` | gość / klient | token galerii + opcjonalny PIN/hasło |
| `/b/{studio}` | gość | brak |
| `/d/{token}` | posiadacz tokenu | token pobrania (jednorazowy TTL) |
| `/wp-json/kadr/v1/*` | wg endpointu | `permission_callback`, zawsze |
| `/wp-admin/admin.php?page=kadr` | administrator platformy | `manage_options` |

Rejestrowane przez `add_rewrite_rule()` w `Infrastructure\WordPress\Rewrites`.
Reguły przepisywania są flushowane **wyłącznie przy aktywacji i migracji**, nigdy przy każdym requeście.

---

## 4. Role i capabilities

Bezpieczeństwo **nie opiera się na nazwach ról** — role są jedynie zestawami capabilities.

| Rola | Opis |
|---|---|
| `platform_admin` | operator SaaS (WP `administrator`) |
| `kadr_owner` | właściciel tenanta — pełnia praw w swoim tenancie |
| `kadr_member` | członek zespołu — capabilities nadawane punktowo |
| *(klient)* | poza `wp_users`, własny zestaw uprawnień na poziomie zasobu |
| *(gość)* | wyłącznie dostęp tokenowy do konkretnej galerii |

Przykładowe capabilities:
```
kadr_access_app            kadr_manage_galleries      kadr_upload_assets
kadr_manage_clients        kadr_view_orders           kadr_manage_orders
kadr_manage_products       kadr_manage_calendar       kadr_view_analytics
kadr_manage_billing        kadr_manage_team           kadr_manage_settings
kadr_impersonate_client    kadr_delete_gallery        kadr_export_data
```

Scenariusz studia (persona P3): asystentka biura dostaje `kadr_access_app`, `kadr_manage_clients`,
`kadr_manage_calendar` — bez `kadr_manage_billing` i bez `kadr_view_analytics`.
Retuszerka zewnętrzna: dostęp wyłącznie do przypisanych galerii, przez ograniczony zakres.

---

## 5. REST API

Namespace `kadr/v1`. **Jedyny kontrakt danych** dla dashboardu i portalu klienta — te same
endpointy zostaną wystawione publicznie w planie Pro.

- `permission_callback` **zawsze**, nigdy `__return_true` poza publiczną treścią marketingową
- nonce (`X-WP-Nonce`) dla żądań z sesji przeglądarkowej
- odpowiedzi opakowane: `{ data, meta }`; błędy: `{ code, message, details }`
- paginacja kursorowa dla list o dużym wolumenie (zdjęcia, zamówienia)
- rate limiting na endpointach wrażliwych (logowanie, magic link, upload, webhooki)

Szczegóły i konwencje: [`API.md`](API.md).

---

## 6. Zdarzenia domenowe

Fundament pod automatyzacje (Session 5) i pod analitykę, bez budowania własnego Zapiera.

```
GalleryPublished · GalleryOpened · GalleryExpiring · GalleryExpired
SelectionStarted · SelectionSubmitted
OrderPlaced · OrderPaid · OrderFailed
BookingCreated · BookingReminderDue · BookingCancelled
AssetsDelivered · DownloadIssued
SubscriptionChanged · UsageLimitReached
```

Zdarzenia publikowane synchronicznie do dispatchera, obsługa **zawsze w kolejce**.
Każde zdarzenie ma `tenant_id` i `occurred_at`. Automatyzacje w Session 5 to nic innego jak
reguły `zdarzenie → warunek → akcja` zapisane w tabeli.

---

## 7. Kolejka i zadania w tle

Przez `Kadr\Domain\Queue\QueueInterface` (ADR-004). Typowe zadania:

| Zadanie | Uwaga |
|---|---|
| `GenerateAssetVariants` | jedno zadanie **na zdjęcie**, nie na galerię — retry bez powtarzania całości |
| `BuildGalleryZip` | limit rozmiaru, TTL pliku, powiadomienie po zakończeniu |
| `SendNotification` | backoff, log niepowodzeń |
| `ArchiveExpiredGalleries` | cykliczne, wsadowe |
| `RecalculateUsage` | weryfikacja liczników przyrostowych |
| `ProcessPaymentWebhook` | po zapisaniu zdarzenia, nigdy w requeście webhooka |

**Limit współbieżności per tenant** — jeden fotograf wgrywający wesele nie może zagłodzić kolejki
pozostałych.

---

## 8. Migracje i wersja schematu

- Numerowane migracje `Migration_0001_...` → `Migration_NNNN_...`
- Aktualna wersja w `wp_options` → `kadr_schema_version`
- Uruchamiane **wyłącznie** przy aktywacji, aktualizacji wtyczki i przez świadomą akcję admina.
  **Nigdy przy każdym requeście.**
- Migracja nigdy nie usuwa danych użytkownika bez jawnej decyzji administratora
- Każda migracja ma opis skutku i, jeśli to wykonalne, ścieżkę wycofania

Szczegóły: [`DATABASE.md`](DATABASE.md).

---

## 9. Deaktywacja vs usunięcie danych

```
Deaktywacja wtyczki  → zatrzymanie cronów i kolejki. DANE POZOSTAJĄ NIETKNIĘTE.
Usunięcie wtyczki    → domyślnie dane POZOSTAJĄ.
Usunięcie danych     → wyłącznie przez jawną, dwustopniowo potwierdzoną akcję w panelu admina.
```

`uninstall.php` nie kasuje niczego, dopóki administrator nie ustawi jawnie flagi
`kadr_delete_all_data_on_uninstall`. Domyślna wartość: `false`.

---

## 10. Frontend

| Powierzchnia | Podejście | Budżet JS |
|---|---|---|
| Landing | PHP + Interactivity API w blokach. **Bez frameworka** | ≤ 30 KB gzip |
| Galeria klienta | PHP shell + wyspa JS (vanilla + natywne API) | ≤ 60 KB gzip |
| Dashboard | PHP shell + wyspy (decyzja o bibliotece w Session 3 — kandydat: Preact + Signals, ~5 KB) | ≤ 120 KB gzip |
| Portal klienta | jak galeria | ≤ 60 KB gzip |

**Zasady:** nie ładujemy kodu dashboardu na landingu · nie ładujemy CSS aplikacji na stronie
marketingowej · code splitting tam, gdzie daje realną korzyść, nie z zasady ·
progressive enhancement — galeria pokazuje zdjęcia, zanim wykona się jakikolwiek JS.

---

## 11. Obserwowalność

Ekran health w panelu administratora: PHP i rozszerzenia · WordPress · baza i wersja schematu ·
cron i kolejka (zaległości, zadania nieudane) · storage (dostępność, zużycie) · e-mail ·
webhooki płatności (ostatnie zdarzenia, nieudane) · REST · wersja wtyczki.

Logger (`Infrastructure\Security\Logger`) zapisuje zdarzenia systemowe, błędy webhooków,
niepowodzenia zadań w tle, e-maili i storage'u.
**Nigdy nie loguje:** haseł, pełnych sekretów API, tokenów, danych kart.
