# Kadr

Platforma SaaS dla profesjonalnych fotografów — autorska wtyczka WordPress.

Prowadzi całą współpracę z klientem: **zapytanie → rezerwacja → sesja → galeria proofingowa →
wybór zdjęć → dopłata → obróbka → dostawa → odbitki → ponowna rezerwacja.**

> **Status: Session 2/6 — działająca wtyczka, warstwa marketingowa.**
> Wtyczkę można zainstalować i aktywować od razu po rozpakowaniu — bez `composer install`
> i bez `npm run build`. Zależności produkcyjnych: zero.
> Funkcje SaaS (galerie, wybór zdjęć, zamówienia) powstają od Session 3.

---

## Dokumentacja

| Plik | Zawartość |
|---|---|
| [`CLAUDE.md`](CLAUDE.md) | zasady pracy nad projektem — **czytaj pierwsze** |
| [`PROJECT_STATE.md`](PROJECT_STATE.md) | aktualny stan, decyzje, następny krok |
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | warstwy, routing, multi-tenancy, moduły |
| [`docs/DECISIONS.md`](docs/DECISIONS.md) | ADR-y — dlaczego jest tak, a nie inaczej |
| [`docs/ROADMAP.md`](docs/ROADMAP.md) | 6 sesji, zakres MVP, post-MVP |
| [`docs/DATABASE.md`](docs/DATABASE.md) | model danych, ERD, indeksy, migracje |
| [`docs/SECURITY.md`](docs/SECURITY.md) | model zagrożeń i reguły bezpieczeństwa |
| [`docs/PERFORMANCE.md`](docs/PERFORMANCE.md) | budżety, Core Web Vitals, pipeline obrazów |
| [`docs/DESIGN-SYSTEM.md`](docs/DESIGN-SYSTEM.md) | kierunek wizualny, tokeny, typografia, komponenty |
| [`docs/API.md`](docs/API.md) | konwencje REST API v1 |
| [`docs/BILLING.md`](docs/BILLING.md) | plany, entitlementy, free tier, dodatki |
| [`docs/LEGAL.md`](docs/LEGAL.md) | dokumenty, podział ról RODO |
| [`docs/SESSION-LOG.md`](docs/SESSION-LOG.md) | log kolejnych sesji |

## Wymagania

PHP 8.2+ · WordPress 6.5+ · MySQL 8.0 / MariaDB 10.6+ · Imagick · VPS (nie shared hosting)

## Instalacja

1. Skopiuj katalog do `wp-content/plugins/kadr/`.
2. Aktywuj wtyczkę w panelu WordPressa.
3. Utwórz stronę i wstaw wzorzec **Kadr → Strona główna — pełny układ**.

Composer i npm są potrzebne wyłącznie do narzędzi deweloperskich, nie do działania wtyczki.

## Narzędzia

```bash
php tools/run-tests.php      # testy warstwy Domain (bez zależności)
php tools/check-blocks.php   # spójność block.json ↔ render.php ↔ editor.js
./tools/package.sh session2  # archiwum RAR z checkpointem sesji
composer lint                # PHPCS (wymaga composer install)
composer analyse             # PHPStan (wymaga composer install)
```

## Licencja

Własnościowa. Wszelkie prawa zastrzeżone.
