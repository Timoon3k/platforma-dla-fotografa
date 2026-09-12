# src/ — kod aplikacji

Autoload PSR-4: `Kadr\` → `src/`

```
Domain/          encje, reguły, polityki. ZERO kodu WordPressa.
                 Testowalne bez ładowania WP.
Application/     use case'y (komendy i zapytania), orkiestracja, transakcje.
Infrastructure/  adaptery: $wpdb, Storage, Payments, Mail, Queue, Cache,
                 WP Users, Image pipeline, Logger, Encryption, Migrations.
Presentation/    REST controllers, bloki Gutenberga, /app, /k, /g, /b.
                 Cienka warstwa. Zero logiki biznesowej.
```

**Zależności wskazują tylko w dół. Domain nie zna nikogo.**

Pełny opis warstw, podkatalogów i wzorców: [`../docs/ARCHITECTURE.md`](../docs/ARCHITECTURE.md)

> Katalogi są puste — kod powstaje od Session 2 (bootstrap, design system)
> i Session 3 (rdzeń SaaS). Patrz [`../docs/ROADMAP.md`](../docs/ROADMAP.md).
