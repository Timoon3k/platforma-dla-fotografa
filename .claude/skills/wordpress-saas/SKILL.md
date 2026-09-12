---
name: wordpress-saas
description: Zasady budowy Kadr jako wtyczki WordPress klasy SaaS. Użyj przed pisaniem jakiegokolwiek PHP, tworzeniem klasy, endpointu REST, repozytorium, migracji lub hooka WordPressa.
---

# WordPress SaaS — Kadr

## 1. Warstwy

```
Presentation → Application → Domain ← Infrastructure
```
Zależności tylko w dół. **Domain nie zna WordPressa** — żadnego `add_action`, `get_option`,
`wp_*`, `$wpdb`. Jeśli musisz — kod należy do Infrastructure.

Szczegóły: `docs/ARCHITECTURE.md`.

## 2. Zasady, których nie wolno złamać

1. **Dane transakcyjne → custom tables.** CPT tylko dla treści marketingowej.
2. **`plugin.php` (`kadr.php`) to bootstrap ≤ 100 linii.** Sprawdzenie wymagań, autoload,
   kontener, hooki aktywacji. Zero logiki.
3. **Repozytorium wymusza tenanta konstrukcyjnie.** Nie istnieje metoda pozwalająca go pominąć.
4. **`permission_callback` zawsze.** `__return_true` wyłącznie dla publicznej treści
   marketingowej, z komentarzem.
5. **`$wpdb->prepare()` zawsze.** Zero interpolacji w SQL.
6. **Escaping na wyjściu zawsze.** `esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`.
7. **Każdy string UI przez i18n** z text domain `kadr`.
8. **Fotograf nie widzi WP Admin. Klient tym bardziej.**

## 3. Wzorzec repozytorium

```php
final class WpdbGalleryRepository implements GalleryRepository {
    public function __construct(
        private \wpdb $db,
        private TenantContext $tenant,
    ) {}

    public function find( Ulid $id ): ?Gallery {
        $row = $this->db->get_row( $this->db->prepare(
            "SELECT * FROM {$this->table()} 
             WHERE public_id = %s AND tenant_id = %d AND deleted_at IS NULL",
            (string) $id,
            $this->tenant->id(),
        ) );
        return $row ? Gallery::fromRow( $row ) : null;
    }
}
```

Brak zasobu **lub** cudzy zasób → `null` → kontroler zwraca **404**, nigdy 403.

## 4. Wzorzec use case

```php
final class CreateGallery {
    public function __construct(
        private GalleryRepository $galleries,
        private Entitlements $entitlements,
        private EventDispatcher $events,
        private AuditLogger $audit,
    ) {}

    public function __invoke( CreateGalleryCommand $cmd ): Result {
        if ( $this->entitlements->limit('gallery_limit')->isReached() ) {
            return Result::failure( 'kadr_limit_reached', [ … ] );
        }
        $gallery = Gallery::create( $cmd->title, $cmd->clientId );
        $this->galleries->save( $gallery );
        $this->events->dispatch( new GalleryCreated( $gallery->id() ) );
        $this->audit->record( 'gallery.created', $gallery->id() );
        return Result::success( $gallery );
    }
}
```

**Nigdy `if ( $plan === 'pro' )`.** Zawsze przez `Entitlements` (`docs/BILLING.md`).

## 5. Migracje

Numerowane, z wersją w `wp_options` → `kadr_schema_version`.
Uruchamiane przy aktywacji, aktualizacji i przez świadomą akcję admina — **nigdy przy każdym
requeście**. Żadna migracja nie usuwa danych użytkownika bez jawnej decyzji administratora.

## 6. Deaktywacja vs usunięcie danych

```
Deaktywacja → zatrzymanie cronów. DANE POZOSTAJĄ.
Usunięcie wtyczki → DANE POZOSTAJĄ (domyślnie).
Usunięcie danych → tylko przez jawną, dwustopniowo potwierdzoną akcję admina.
```

## 7. Zależności

Każda biblioteka musi odpowiedzieć: *czy korzyść przewyższa koszt utrzymania, bezpieczeństwa,
rozmiaru i vendor lock-inu?* Zatwierdzone: Action Scheduler, async-aws/s3.
Każda kolejna wymaga wpisu do `docs/DECISIONS.md`.

## 8. Antywzorce

```
✖ get_posts() do danych aplikacyjnych        ✖ update_post_meta() dla stanu galerii
✖ globalne zmienne jako kanał danych         ✖ admin-ajax zamiast REST
✖ logika w hooku zamiast w use case          ✖ klasa Helpers / Utils / Functions
✖ flush_rewrite_rules() przy każdym requeście ✖ zapytania w pętli (N+1)
✖ TODO: implement later w kodzie produkcyjnym ✖ mock data poza trybem demo
```
