# CLAUDE.md — Kadr

> Przeczytaj ten plik **w całości** na początku każdej sesji, zanim dotkniesz czegokolwiek.
> Kolejność czytania na starcie sesji:
> 1. `CLAUDE.md` (ten plik)
> 2. `PROJECT_STATE.md`
> 3. `docs/DECISIONS.md`
> 4. `docs/ROADMAP.md`
> 5. ostatni wpis w `docs/SESSION-LOG.md`
>
> Nie wymyślaj architektury od nowa. Jest zapisana. Jeśli uważasz, że decyzja jest zła —
> nie zmieniaj jej po cichu. Napisz nowy ADR, który jawnie zastępuje stary.

---

## 1. Czym jest ten produkt

**Kadr** to autorska wtyczka WordPress będąca platformą SaaS dla profesjonalnych fotografów.
Prowadzi całą współpracę z klientem: zapytanie → rezerwacja → sesja → galeria proofingowa →
wybór zdjęć → dopłata/zamówienie → obróbka → dostawa → odbitki → ponowna rezerwacja.

**To nie jest:** hosting galerii, kreator stron portfolio, edytor zdjęć, system księgowy,
marketplace, ani kopia Pixieset / Pic-Time / Mafelo.

**Produkt sprzedaje dwie rzeczy, w tej kolejności:**
1. odzyskany czas fotografa (2–4 h administracji na sesję → kilkanaście minut),
2. przychód, który dziś wyparowuje (dopłaty za nadmiarowe zdjęcia, odbitki, powroty klientów).

**Cztery etapy procesu, które są całą wartością ekonomiczną produktu** (reszta to higiena):
wybór zdjęć · dopłata/zamówienie · dostawa · odbitki.
Jeśli funkcja nie wspiera żadnego z nich i nie jest wymagana prawnie — nie wchodzi do v1.0.

---

## 2. Protokół pracy

Domyślny tryb: **ANALIZA → PROPOZYCJA → AKCEPTACJA → IMPLEMENTACJA → TEST → AKCEPTACJA**.

Właściciel produktu może delegować akceptację („zostawiam checklistę tobie”). Wtedy:
- podejmujesz decyzję **zgodnie z rekomendacją zapisaną w dokumentacji**,
- zapisujesz ją jako ADR w `docs/DECISIONS.md`,
- raportujesz ją jawnie w podsumowaniu, żeby dało się ją cofnąć jedną poprawką.

Delegacja **nie obejmuje nigdy**:
- zmiany kierunku produktu lub modelu biznesowego,
- marketplace / split payments (§17 briefu — wymaga osobnego dokumentu i wyraźnej zgody),
- nieodwracalnego usuwania danych,
- instalowania dużych zależności bez wpisania uzasadnienia do `docs/DECISIONS.md`.

### Format PRZED większym pakietem zmian
```
CURRENT STATE · OBSERVATIONS · RECOMMENDED DIRECTION · ALTERNATIVES
PLANNED CHANGES (checkboxy) · FILES TO BE CREATED/MODIFIED
DATABASE IMPACT · PERFORMANCE IMPACT · SECURITY IMPACT
```

### Format PO implementacji
```
IMPLEMENTED · CHANGED FILES · TESTS · RESULTS · VISUAL QA (desktop/tablet/mobile)
PERFORMANCE · SECURITY · REMAINING ISSUES · DIFF SUMMARY
```

### Na koniec każdej sesji — obowiązkowo
1. zaktualizuj `PROJECT_STATE.md`,
2. dopisz wpis do `docs/SESSION-LOG.md`,
3. dopisz nowe ADR-y do `docs/DECISIONS.md`,
4. zostaw repozytorium w stanie stabilnego checkpointu.

---

## 3. Stack i wymagania

| | |
|---|---|
| PHP | 8.2+ (typy, enumy, readonly, `never`) |
| WordPress | 6.5+ (stabilne Interactivity API) |
| Baza | MySQL 8.0 / MariaDB 10.6+ |
| Obrazy | Imagick wymagany, GD jako degradowany fallback |
| Hosting | VPS. Shared hosting nie jest wspierany |
| Build | Vite. Bez frameworka na landingu |
| Slug / text domain | `kadr` |
| Namespace PHP | `Kadr\` (PSR-4, `src/` → `Kadr\`) |
| Prefiks tabel | `{$wpdb->prefix}kadr_` |
| REST namespace | `kadr/v1` |

---

## 4. Architektura — zasady nienegocjowalne

```
Presentation  → bloki Gutenberga, /app, /k, /g, /b — cienka warstwa, zero logiki biznesowej
Application   → use case'y (komendy i zapytania), orkiestracja, transakcje
Domain        → encje, reguły, polityki. ZERO kodu WordPressa. Testowalne bez ładowania WP
Infrastructure→ adaptery: $wpdb, Storage, Payments, Mail, Queue, Cache, WP Users, Image
```

1. **Warstwa Domain nie zna WordPressa.** Żadnego `add_action`, `get_option`, `wp_*`.
   Jeśli musisz — to znaczy, że kod należy do Infrastructure.
2. **Dane transakcyjne → custom tables. Nigdy CPT.** CPT wyłącznie dla treści marketingowej
   (strony, bloki, dokumenty, blog). Powód: 18 mln wierszy w `gallery_assets` przy 1000 fotografów.
3. **REST API v1 jest jedynym kontraktem danych.** Dashboard i portal klienta konsumują
   wyłącznie API. Zero `admin-ajax`, zero globalnych zmiennych jako kanału danych.
4. **Tenant isolation w repozytorium, nie w kontrolerze.** Każde repozytorium przyjmuje
   `TenantContext` w konstruktorze i samo dokłada `WHERE tenant_id = ?`.
   **Nie może istnieć metoda repozytorium, która pozwala pominąć tenanta.**
5. **Fotograf nigdy nie widzi WP Admin. Klient tym bardziej.** WP Admin jest tylko dla
   administratora platformy.
6. **Jeden `plugin.php` = bootstrap i nic więcej.** Maks. ~100 linii: sprawdzenie wymagań,
   autoload, rejestracja kontenera, hooki aktywacji. Zero logiki.

Szczegóły: `docs/ARCHITECTURE.md`.

---

## 5. Bezpieczeństwo — reguły, których nie wolno złamać

- `permission_callback => '__return_true'` jest **zakazane** dla czegokolwiek poza publiczną treścią marketingową.
- Każde zapytanie SQL przez `$wpdb->prepare()`. Zero interpolacji.
- Każde wyjście przez `esc_html` / `esc_attr` / `esc_url` / `wp_kses_post`.
- Każde wejście walidowane **i** sanityzowane. Nigdy nie ufaj nazwie pliku ani MIME z klienta.
- Prywatne zdjęcia **nigdy** pod przewidywalnym publicznym URL-em. Wyłącznie podpisane URL-e z krótkim TTL
  albo kontrolowany endpoint pobrania.
- Tokeny: losowe (`random_bytes`), w bazie trzymamy **hash tokenu**, nie token. Zawsze z wygaśnięciem
  i możliwością unieważnienia.
- Webhooki: weryfikacja podpisu → tolerancja czasowa → `UNIQUE(provider, external_event_id)` → kolejka.
  Idempotencja wymuszona na poziomie bazy, nie kodu.
- ID publiczne to ULID (`public_id`), nigdy sekwencyjny `id` w URL-ach i API.
- Sekrety (klucze API fotografa, webhook secrets) szyfrowane kluczem z `wp-config.php`.
  Nigdy plaintext w `wp_options`, nigdy w repo, nigdy w logach.

Szczegóły i model zagrożeń: `docs/SECURITY.md`.

---

## 6. Wydajność — budżety obowiązujące od pierwszej linii

| Metryka | Cel |
|---|---|
| LCP (landing, lab) | < 2,0 s |
| INP | ≤ 200 ms |
| CLS | ≤ 0,1 |
| JS na landingu | ≤ 30 KB gzip |
| CSS na landingu | ≤ 25 KB gzip |
| JS w galerii klienta | ≤ 60 KB gzip |

- Preload **tylko** faktycznego assetu LCP. Nie dziesięciu.
- Hero: `fetchpriority="high"`, `srcset`, `sizes`, AVIF/WebP. **Nigdy `loading="lazy"` na LCP.**
- Nie ładuj kodu dashboardu na landingu. Nie ładuj CSS aplikacji na stronie marketingowej.
- Każde zdjęcie ma znane wymiary → zero CLS.

Szczegóły: `docs/PERFORMANCE.md`.

---

## 7. Design — czego nie wolno robić

Kierunek: **Atelier** (marketing) + **Studio OS** (dashboard) + motywy galerii Paper/Noir/Minimal.
Pełna specyfikacja: `docs/DESIGN-SYSTEM.md`, reguły warsztatowe: `.claude/skills/premium-ui-design/SKILL.md`.

**Zakazane jako domyślny kierunek:** fioletowo-niebieskie gradienty SaaS · przypadkowe gradienty ·
glassmorphism · blur jako dekoracja · trzy identyczne karty w każdej sekcji · gigantyczne `border-radius` ·
wszędzie pill buttons · floating cards bez powodu · stockowe ilustracje ludzi przy laptopie ·
ikony rakiety/tarczy/błyskawicy · „Everything you need in one place” · nadmiar emoji ·
animowanie wszystkiego, bo się da · kopiowanie układu konkurencji.

**Copy:** konkretne, krótkie, po polsku, skierowane do fotografa. Zakazane: „zrewolucjonizuj”,
„uwolnij potencjał”, „seamless”, „next level”, „wszystko w jednym miejscu” bez treści.
**Nigdy nie wymyślaj opinii, ocen ani logotypów klientów** — puste, jawnie oznaczone sloty.

---

## 8. Zależności

Każda zewnętrzna biblioteka musi odpowiedzieć: *czy korzyść przewyższa koszt utrzymania,
bezpieczeństwa, rozmiaru i vendor lock-inu?* Jeśli 30 linii natywnego kodu rozwiązuje problem —
nie instaluj 200 kB zależności.

Zatwierdzone (stan na Session 1):
- **Action Scheduler** — kolejka zadań, zawsze za własnym `QueueInterface` (ADR-004)
- **async-aws/s3** — modułowy klient S3 (~10× mniejszy niż `aws/aws-sdk-php`) (ADR-011)

Każda kolejna zależność wymaga wpisu w `docs/DECISIONS.md` z uzasadnieniem.

---

## 9. Jakość kodu

- WordPress Coding Standards + PHP_CodeSniffer, PHPStan (docelowo level 6+ w `src/Domain`).
- ESLint + Prettier dla JS/CSS.
- **Nie wyciszaj reguły bez komentarza z uzasadnieniem.**
- Klasy małe, jedna odpowiedzialność. Jeśli klasa ma > 200 linii — prawdopodobnie robi dwie rzeczy.
- Wszystkie stringi UI przez `__()` / `_e()` / `esc_html__()` z text domain `kadr`.
  Nigdy tekstu na sztywno w komponencie.
- **Zero placeholderów w kodzie produkcyjnym**: żadnych `TODO: implement later`, fake API,
  `Math.random()` w statystykach, wymyślonych zamówień ani klientów. Mock data tylko w trybie demo/dev.

---

## 10. Git

- Branch rozwojowy: `claude/premium-photography-saas-u8xl6y`.
- Commit dopiero po zakończonym, spójnym pakiecie zmian. Wiadomość opisowa, po angielsku, imperatyw.
- Nie robimy `merge` do `main`, nie deployujemy, nie tworzymy PR bez wyraźnego polecenia.
- Środowisko sesji jest efemeryczne — niezacommitowana praca przepada. Zamykaj sesję pushem.

---

## 11. Pięć pytań przed każdą funkcją

1. Czy profesjonalny fotograf rzeczywiście tego potrzebuje?
2. Czy użytkownik zrozumie to bez instrukcji?
3. Czy to wygląda jak produkt premium?
4. Czy wytrzyma 1000 fotografów bez przepisywania połowy aplikacji?
5. Czy nie dokładamy złożoności bez realnej wartości?

Jedno „nie” = zatrzymaj się i zaproponuj coś lepszego.
