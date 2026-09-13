# DECISIONS — Architecture Decision Records

Każda istotna decyzja ma tu wpis. **Decyzji nie zmienia się po cichu** — pisze się nowy ADR,
który jawnie zastępuje (`Zastąpiony przez: ADR-0XX`) poprzedni.

Szablon nowego ADR: [`adr/TEMPLATE.md`](adr/TEMPLATE.md)

| # | Decyzja | Status | Sesja |
|---|---|---|---|
| [001](#adr-001) | Nazwa produktu: Kadr | Zaakceptowany (warunkowo) | 1 |
| [002](#adr-002) | Architektura: WP jako platforma, wtyczka jako aplikacja | Zaakceptowany | 1 |
| [003](#adr-003) | Klient końcowy poza `wp_users` | Zaakceptowany | 1 |
| [004](#adr-004) | Kolejka zadań: Action Scheduler za `QueueInterface` | **Zastąpiony przez ADR-016** | 1 |
| [005](#adr-005) | Custom tables zamiast CPT dla danych transakcyjnych | Zaakceptowany | 1 |
| [006](#adr-006) | Płatności klientów: własne konto fotografa, BLIK w pierwszym adapterze | Zaakceptowany | 1 |
| [007](#adr-007) | Billing platformy: Stripe Billing | Zaakceptowany | 1 |
| [008](#adr-008) | Cennik i free tier oparty na projektach | Zaakceptowany | 1 |
| [009](#adr-009) | Kierunek wizualny: Atelier + Studio OS + motywy galerii | **Zastąpiony przez ADR-015** | 1 |
| [010](#adr-010) | Brak globalnego dark mode w v1.0 | **Zastąpiony przez ADR-015** | 1 |
| [011](#adr-011) | Storage: abstrakcja + Local/S3, cykl życia plików | Zaakceptowany | 1 |
| [012](#adr-012) | i18n od pierwszej linii, MVP po polsku | Zaakceptowany | 1 |
| [013](#adr-013) | Brak kroku budowania w warstwie marketingowej | Zaakceptowany | 2 |
| [014](#adr-014) | Testy warstwy Domain bez frameworka | Zaakceptowany | 2 |
| [015](#adr-015) | Kierunek wizualny: Obsidian (ciemna precyzja) + warstwa ruchu | Zaakceptowany | 3 |
| [016](#adr-016) | Własna kolejka zadań zamiast Action Scheduler | Zaakceptowany | 4 |
| [017](#adr-017) | S3 odłożone; storage lokalny jako pełna implementacja | Zaakceptowany | 4 |
| [018](#adr-018) | Preact + Signals + htm jako moduły ES, bez bundlera | Zaakceptowany | 6 |

---

<a name="adr-001"></a>
## ADR-001 — Nazwa produktu: **Kadr**

**Status:** Zaakceptowany warunkowo · Session 1

**Kontekst.** Rozważono 10 kierunków nazewniczych (Kadr, Stykówka, Odsłona, Passepartu, Klisza,
Aperta, Atelia, Wywołane, Proofroom, Lumea). Kryteria: charakter fotograficzny, potencjał marki
premium, długość, wymawialność, skalowalność poza Polskę.

**Decyzja.** **Kadr.** Slug wtyczki i text domain: `kadr`. Namespace PHP: `Kadr\`.
Prefiks tabel: `{$wpdb->prefix}kadr_`. REST: `kadr/v1`.

**Uzasadnienie.** Jedno słowo, cztery litery, twarde spółgłoski — dobrze znosi duży stopień pisma
w typografii display. Jest wprost fotograficzne bez bycia opisowym. Działa fonetycznie poza Polską
(*cadre*, *kader*). Skalowalne jako „Kadr Studio”.
Alternatywa przy strategii global-first: **Aperta**.

**Konsekwencje.**
- Zmiana nazwy teraz = operacja na dokumentacji (minuty). Po Session 3 = namespace + prefiks tabel
  + migracja danych (dni). **Decyzja o nazwie musi zapaść ostatecznie przed Session 3.**
- ⚠️ **Nie zweryfikowano** dostępności domeny ani kolizji znaków towarowych (EUIPO/UPRP).
  To warunek zawieszający — patrz `PROJECT_STATE.md`, kwestia O1.

---

<a name="adr-002"></a>
## ADR-002 — Architektura: WordPress jako platforma, wtyczka jako aplikacja

**Status:** Zaakceptowany · Session 1

**Kontekst.** Trzy warianty: (A) klasyczny WordPress na CPT + postmeta, (B) wtyczka OOP z własnymi
tabelami i własnym frontendem aplikacji, (C) headless — WP jako CMS + osobna aplikacja.

**Decyzja.** Wariant **B**. Cztery warstwy: `Domain` / `Application` / `Infrastructure` / `Presentation`.
WordPress jest adapterem infrastruktury, nie rdzeniem produktu. CPT wyłącznie dla treści marketingowej.
Własny routing poza WP Admin: `/app` (fotograf), `/k` (klient), `/g/{token}` (galeria), `/b/{studio}` (booking).

**Odrzucone.**
- **A** — `wp_postmeta` przy 18 mln zdjęć to dziesiątki milionów wierszy w tabeli EAV; brak jakiejkolwiek
  izolacji tenantów (`get_post(123)` zwraca cudze dane); UI uwiązane do WP Admin, co łamie wymóg
  osobnego, premium dashboardu; niemożliwe do spełnienia budżety Core Web Vitals.
- **C** — łamie założenie „autorska wtyczka WordPress”; dwa deploymenty, dwa stacki, dwa zestawy
  sekretów; koszt utrzymania ~2× przy tym samym zakresie; fotograf nie kupi produktu, którego nie
  wgra na swój hosting.

**Konsekwencje.**
- Warstwa domenowa testowalna bez ładowania WordPressa → szybkie testy jednostkowe.
- Ewentualne późniejsze przejście na C = wymiana `Infrastructure` i `Presentation`, bez dotykania domeny.
- Więcej pracy na starcie: własny routing, własny auth klienta, własne ekrany.
- Wymaga VPS z Imagickiem — zawęża rynek, ale bez tego pipeline obrazów jest wolny i brzydki.

---

<a name="adr-003"></a>
## ADR-003 — Klient końcowy jest przechowywany poza `wp_users`

**Status:** Zaakceptowany · Session 1

**Kontekst.** Klient fotografa musi móc się zalogować do portalu `/k` i otwierać galerie.
Naturalne byłoby użycie użytkowników WordPressa z dedykowaną rolą.

**Decyzja.** Klienci żyją w tabeli `kadr_clients` z własnym mechanizmem sesji
(`kadr_client_sessions`). **Fotograf** pozostaje użytkownikiem WordPressa z custom capabilities.

**Uzasadnienie — argument rozstrzygający.** `wp_users.user_email` jest unikalny globalnie.
W modelu wielotenantowym ta sama osoba może być klientką dwóch różnych fotografów. W modelu
opartym na `wp_users` oznacza to albo jedno współdzielone konto widoczne dla obu fotografów
(wyciek danych między tenantami), albo konflikt przy rejestracji. **Multi-tenancy wyklucza użycie
`wp_users` dla klientów.** Skala (50 tys. klientów spowalniających cały WordPress) jest argumentem
dodatkowym, nie głównym.

**Konsekwencje i mitygacja ryzyka.**
- Piszemy własną warstwę uwierzytelniania → ryzyko błędu bezpieczeństwa.
  Mitygacja: **nie piszemy własnej kryptografii.** Hasła przez `wp_hash_password()` / `wp_check_password()`.
  Sesje jako podpisane ciasteczka + rekord w bazie (unieważnialny), nie jako samodzielne JWT.
- Domyślna ścieżka logowania klienta to **magic link**, nie hasło — klient z persony P4 i tak nie
  chce zakładać konta. Hasło jest opcjonalne.
- Klient nie ma i nie może mieć dostępu do `/wp-admin` — nie istnieje jako użytkownik WordPressa.
- Wymagany dedykowany zestaw testów: izolacja tenantów, wygaszanie sesji, throttling logowania.

---

<a name="adr-004"></a>
## ADR-004 — Kolejka zadań: Action Scheduler, zawsze za własnym interfejsem

**Status:** ⛔ **Zastąpiony przez [ADR-016](#adr-016)** · Session 1

**Kontekst.** Generowanie wariantów dla 800 zdjęć, pakowanie ZIP-ów, wysyłki masowe i archiwizacja
nie mogą blokować requestu użytkownika.

**Rozważone.** (A) `wp_cron` — odpala się tylko przy ruchu, brak współbieżności i kontroli.
(B) **Action Scheduler** — biblioteka z WooCommerce, własne tabele, miliony instalacji, podgląd zadań.
(C) własna kolejka — ~2 tygodnie na odtworzenie czegoś, co istnieje. (D) zewnętrzny worker (Redis
+ supervisor) — najwydajniejsze, ale podnosi wymagania hostingowe i utrudnia dystrybucję wtyczki.

**Decyzja.** **B**, ale kod aplikacyjny nigdy nie woła Action Schedulera bezpośrednio — wyłącznie
przez `Kadr\Domain\Queue\QueueInterface` (`dispatch`, `dispatchIn`, `cancel`, `status`).

**Uzasadnienie.** Kolejka jest infrastrukturą, nie wyróżnikiem produktu — pisanie jej od zera to
koszt bez zwrotu. Własny interfejs kosztuje jeden plik i pozwala przejść na wariant D dla dużych
instalacji, nie dotykając ani jednego use case'a.

**Konsekwencje.** Pierwsza większa zależność PHP w projekcie. Wymagany limit współbieżności per tenant,
żeby jeden fotograf wgrywający wesele nie zagłodził kolejki pozostałych.

---

<a name="adr-005"></a>
## ADR-005 — Dane transakcyjne w custom tables, nigdy w CPT

**Status:** Zaakceptowany · Session 1

**Kontekst.** WordPress zachęca do trzymania wszystkiego w `wp_posts` + `wp_postmeta`.

**Decyzja.** Wszystkie dane aplikacyjne (tenanci, klienci, galerie, zdjęcia, wybory, zamówienia,
płatności, rezerwacje, subskrypcje, logi) w dedykowanych tabelach z prefiksem `kadr_`.
CPT wyłącznie dla treści marketingowej: strony, bloki, dokumenty prawne, blog.

**Uzasadnienie — liczby.**
```
1000 fotografów × 60 galerii/rok × 300 zdjęć = 18 000 000 zdjęć
~12 atrybutów na zdjęcie → ~216 000 000 wierszy w wp_postmeta
Zapytanie „wybrane zdjęcia z galerii X” = 6 JOIN-ów po tabeli EAV.
W custom tables: jeden indeks złożony, jeden odczyt.
```

**Konsekwencje.** Własny system migracji z wersją schematu. Brak darmowych funkcji WordPressa
(rewizje, wyszukiwarka, listy w adminie) — i tak ich nie chcemy, bo budujemy własny interfejs.

---

<a name="adr-006"></a>
## ADR-006 — Płatności klientów idą bezpośrednio do fotografa; pierwszy adapter musi mieć BLIK

**Status:** Zaakceptowany · Session 1

**Kontekst.** Istnieją dwie rozłączne domeny płatnicze: fotograf → platforma (abonament) oraz
klient → fotograf (zadatek, dopłaty, odbitki).

**Decyzja.**
1. Platforma **nigdy nie jest stroną transakcji klient → fotograf** i nie przyjmuje tych środków.
   Fotograf podłącza własne klucze API swojego operatora; pieniądze trafiają bezpośrednio na jego konto.
2. Abstrakcja `PaymentGatewayInterface` od pierwszego dnia, z adapterem testowym w zestawie.
3. Pierwszy adapter produkcyjny: **PayNow albo Przelewy24** — decyzja o wyborze konkretnego operatora
   w Session 4, na podstawie porównania warunków dla jednoosobowych działalności.

**Uzasadnienie.** Model marketplace ze split payments oznacza status agenta rozliczeniowego /
instytucji płatniczej i jest osobnym projektem prawnym, księgowym i technicznym.
**BLIK jest warunkiem koniecznym** — klientka otwierająca galerię o 22:30 na telefonie nie wyjmie
karty. Bez BLIK-a konwersja w kroku „dopłata” załamuje się i cała przewaga produktu przestaje działać.

**Konsekwencje.**
- Brak przychodu prowizyjnego od transakcji klientów — cały przychód pochodzi z abonamentów.
- Przechowujemy sekrety fotografa → szyfrowanie symetryczne kluczem z `wp-config.php`,
  nigdy plaintext w `wp_options`, nigdy w logach.
- Onboarding ma dodatkowy krok (weryfikacja fotografa u operatora). Trzeba go zaprojektować uczciwie:
  galerie i sesje działają od razu, płatności online dopiero po tym kroku.
- **Powrót do modelu marketplace wymaga zatrzymania prac i osobnego dokumentu** — patrz `CLAUDE.md` §2.

---

<a name="adr-007"></a>
## ADR-007 — Billing platformy na Stripe Billing

**Status:** Zaakceptowany · Session 1

**Decyzja.** Abonamenty fotografów (i dodatki: storage, seaty, domena, SMS) obsługuje **Stripe Billing**.

**Uzasadnienie.** Dojrzały recurring, proration przy zmianie planu, SCA, portal klienta, przewidywalne
webhooki. To jest **nasze jedno konto**, więc złożoność integracji jest jednorazowa — inaczej niż
w ADR-006, gdzie każdy fotograf konfiguruje własnego operatora.

**Konsekwencje.**
- Moduł `Billing\` jest całkowicie oddzielony od `Commerce\` — osobne tabele, osobne webhooki,
  osobne statusy. Nie współdzielą kodu.
- **Faktury VAT PL:** MVP daje eksport danych (CSV/JSON). Integracja z Fakturownią/wFirmą to post-MVP.
  Nie udajemy systemu księgowego.
- Wymagany mechanizm **pauzy konta** (sezonowość listopad–luty, patrz ADR-008), nie tylko anulowania.

---

<a name="adr-008"></a>
## ADR-008 — Cennik i free tier oparty na projektach, nie na czasie

**Status:** Zaakceptowany · Session 1

**Decyzja.**
- **Free:** 5 pełnych projektów klienckich, bezterminowo, 5 GB, bez karty, z włączoną sprzedażą.
- **Starter 69 zł** / **Studio 149 zł** / **Pro 299 zł** miesięcznie; rocznie −17% (dwa miesiące gratis).
- Dodatki recurring: storage, seat, domena, SMS, motywy premium, reaktywacja galerii.

**Uzasadnienie — dlaczego nie trial czasowy.** Cykl „sesja → galeria → wybór → dopłata → dostawa”
trwa w fotografii 2–6 tygodni. Trial 14-dniowy **strukturalnie uniemożliwia** zobaczenie wartości
produktu. Free tier oparty na projektach jest w tej branży jedynym mechanizmem, który działa.

**Dlaczego free tier ma włączoną sprzedaż.** Fotograf musi przeżyć moment, w którym klient dopłaca
za nadmiarowe zdjęcia. To jest cały argument sprzedażowy produktu — ukrycie go za paywallem
oznacza, że free tier nie sprzedaje.

**Po wyczerpaniu limitu.** Konto przechodzi w **read-only**. Istniejące galerie klientów działają dalej
(klient nie może być karą dla fotografa). Dane przechowywane min. 12 miesięcy, z powiadomieniem
na 30 i 7 dni przed. **Zakazane:** liczniki odliczające, blokada pobierania opłaconych zdjęć,
ciche wygaszanie danych, cena widoczna dopiero po podaniu karty.

**Konsekwencje.**
- **Storage jest jedynym istotnie zmiennym kosztem.** Limity muszą być egzekwowane twardo,
  a liczniki zużycia liczone przyrostowo (nie `SUM()` na żądanie).
- Potrzebna **pauza konta** (~29 zł/mies, read-only + storage) jako mechanizm anty-churnowy
  na martwy sezon. Tańsza niż odzyskiwanie utraconego klienta.
- Ceny **nigdy nie są zakodowane w wielu miejscach** — jeden rejestr planów, patrz `docs/BILLING.md`.

---

<a name="adr-009"></a>
## ADR-009 — Kierunek wizualny: Atelier na zewnątrz, Studio OS w środku

**Status:** ⛔ **Zastąpiony przez [ADR-015](#adr-015)** · Session 1

**Kontekst.** Opracowano trzy odrębne kierunki: **Darkroom** (editorial noir), **Atelier**
(ciepły, papierowy, galeryjny), **Studio OS** (precyzyjny, techniczny, gęsty).

**Decyzja.**
```
Strona marketingowa  → ATELIER
Dashboard fotografa  → STUDIO OS w palecie Atelier
Galeria klienta      → motyw wybierany przez fotografa: Paper / Noir / Minimal
```

**Uzasadnienie.** Fotograf kupuje emocją, a pracuje rozumem. Strona sprzedażowa musi wyglądać jak
jego portfolio — inaczej nie uwierzy, że galerie będą wyglądać dobrze. W dashboardzie, gdzie spędzi
300 godzin rocznie, chce gęstości i czytelności, nie poezji. Żaden z trzech kierunków się nie marnuje:
Darkroom i Minimal stają się motywami galerii, czyli **entitlementem planu** (Starter: 2 motywy,
Studio: 5, Pro: wszystkie).

**Konsekwencje.**
- Tokeny muszą być **semantyczne** (`--surface`, `--ink`, `--accent`), nigdy dosłowne (`--beige`).
  Motyw = wymiana warstwy zmiennych, nie drugi arkusz stylów.
- Jeden system komponentów obsługuje trzy skórki → każdy komponent testowany w trzech motywach.
- Typografia: **Fraunces** (display, variable) + **General Sans** (UI) + `tabular-nums` dla liczb.
  ⚠️ Licencje do potwierdzenia przed Session 2 (`PROJECT_STATE.md`, kwestia O4).

---

<a name="adr-010"></a>
## ADR-010 — Brak globalnego dark mode w v1.0

**Status:** ⛔ **Zastąpiony przez [ADR-015](#adr-015)** · Session 1

**Decyzja.** v1.0 nie ma przełącznika jasny/ciemny. Marketing jest jasny (Atelier), dashboard jasny,
**galeria ma motywy — w tym ciemny Noir**, wybierane przez fotografa.

**Uzasadnienie.** Globalny dark mode podwaja powierzchnię QA i testów kontrastu WCAG na każdym
komponencie, a fotograf pracuje przy skalibrowanym monitorze w dzień. Ciemny motyw ma realną wartość
dokładnie w jednym miejscu — w galerii, gdzie zdjęcia wyglądają lepiej na czerni — i tam go dajemy.

**Konsekwencje.** Mimo to tokeny od początku projektujemy semantycznie, żeby dodanie dark mode
po premierze nie wymagało przepisania stylów. Preferencja `prefers-color-scheme` jest respektowana
w galerii tam, gdzie fotograf wybrał motyw „auto”.

---

<a name="adr-011"></a>
## ADR-011 — Abstrakcja storage, cykl życia plików, region EU

**Status:** Zaakceptowany · Session 1

**Decyzja.**
1. `StorageProviderInterface` (`put`, `get`, `delete`, `exists`, `size`, `signedUrl`, multipart).
2. Implementacje: **LocalStorage** (dev i małe instalacje, poza `/uploads`) oraz **S3Storage**
   (object storage kompatybilny z S3, **region EU**).
3. Klient S3: **`async-aws/s3`**, nie `aws/aws-sdk-php` — modułowy, ~10× mniejszy, potrzebujemy
   jednej usługi z kilkudziesięciu.
4. Cykl życia: `AKTYWNA` → (wygaśnięcie + 30 dni) → `ZARCHIWIZOWANA` (oryginały do cold storage,
   podglądy zostają, reaktywacja płatna) → (+12 mies., po dwóch powiadomieniach) → `DO USUNIĘCIA`.
5. Warianty derywatów: `thumb` 400 px, `grid` 900 px, `view` 1800 px × {AVIF, WebP} + opcjonalny
   znak wodny. **Sześć plików na zdjęcie, nie dwadzieścia.** JPEG generowany leniwie.
6. Deduplikacja po `SHA-256` w obrębie tenanta.

**Uzasadnienie.** Storage jest jedynym istotnie zmiennym kosztem SaaS-u — każdy dodatkowy wariant
musi mieć uzasadnienie biznesowe. Archiwizacja jest jednocześnie mechanizmem oszczędności
i mikro-upsellem (reaktywacja galerii). Region EU wynika z RODO i z tego, co obiecujemy w DPA.

**Konsekwencje.** Oryginały **nigdy** nie są serwowane bezpośrednio. Usunięcie plików zawsze
poprzedzone powiadomieniem — nigdy po cichu. Panel administratora musi pokazywać koszt storage
per tenant zestawiony z przychodem per tenant.

---

<a name="adr-012"></a>
## ADR-012 — i18n od pierwszej linii, MVP po polsku

**Status:** Zaakceptowany · Session 1

**Decyzja.** Wszystkie stringi UI przechodzą przez WordPress i18n (`__`, `_e`, `esc_html__`,
`_n`, `wp.i18n` w JS) z text domain `kadr`. MVP dostarczamy po polsku; struktura gotowa na EN
i kolejne języki.

**Uzasadnienie.** Doklejanie i18n po fakcie do kilkudziesięciu komponentów to praca na tygodnie
i gwarantowane pominięcia. Zrobione od początku kosztuje zero.

**Konsekwencje.**
- Zakaz tekstu na sztywno w komponencie — również w placeholderach, `aria-label` i komunikatach błędów.
- Format dat, liczb i walut przez warstwę lokalizacji, nie przez `date('d.m.Y')`.
- Treści redagowalne przez fotografa (szablony e-maili, teksty galerii) to **dane**, nie stringi i18n —
  trzymane w bazie, per tenant.


---

<a name="adr-013"></a>
## ADR-013 — Brak kroku budowania w warstwie marketingowej

**Status:** Zaakceptowany · Session 2
**Zastępuje:** notatkę „Build: Vite” z tabeli stacku w `CLAUDE.md` §3.

**Kontekst.** Bloki Gutenberga zwyczajowo pisze się w JSX i buduje przez
`@wordpress/scripts` albo Vite. To wymaga `npm install` (setki MB) i `npm run build`
przed każdym uruchomieniem wtyczki.

**Rozważone warianty.**
- **A — `@wordpress/scripts`.** Oficjalny toolchain WordPressa, JSX, obsługa i18n,
  generowanie `*.asset.php`. Koszt: wtyczka nie działa po rozpakowaniu, dopóki ktoś nie zbuduje.
- **B — Vite.** Szybszy, ale wymaga ręcznego odtworzenia tego, co wp-scripts robi samo
  (zewnętrzne zależności `wp.*`, i18n, manifesty). Ten sam koszt uruchomieniowy co A.
- **C — bez kroku budowania.** Bloki renderowane po stronie serwera (`render.php`),
  jedna warstwa edytora generowana z deklaratywnej specyfikacji przez `wp.element.createElement`,
  interakcje przez Interactivity API jako moduły ES.

**Decyzja.** **C**, dla warstwy marketingowej i galerii.

**Uzasadnienie.**
1. **Wtyczka działa zaraz po rozpakowaniu archiwum.** To ma znaczenie praktyczne: checkpointy
   sesji są dostarczane jako RAR, a artefakt, który wymaga `npm install`, nie jest wtyczką,
   tylko kodem źródłowym wtyczki.
2. WordPress 6.5+ dostarcza mapę importów dla `@wordpress/interactivity`, więc moduł ES
   z `import { store } from '@wordpress/interactivity'` działa **bez bundlera**. Nie tracimy
   nowoczesnego API, tracimy tylko krok kompilacji.
3. Warstwa edytora tych dziewięciu bloków to w praktyce RichText, kilka kontrolek i repeater.
   Generowanie ich z jednej specyfikacji (`assets/js/editor.js`) daje mniej kodu niż dziewięć
   plików JSX — a nie więcej, jak sugerowałaby intuicja.
4. Mierzalny efekt: JS landingu to **2,1 KB gzip** przy budżecie 30 KB.

**Konsekwencje.**
- Kod edytora jest bardziej rozwlekły składniowo niż JSX. Akceptujemy to, bo jest go mało
  i jest generowany z jednego miejsca.
- **Ta decyzja nie obejmuje dashboardu fotografa (Session 3).** Interfejs z tabelami, filtrami
  i stanem to inna klasa problemu — wtedy wracamy do tematu i najpewniej wprowadzamy bundler
  dla `/app`, zachowując brak budowania dla części publicznej.
- Ryzyko rozjazdu między `block.json`, `render.php` i `editor.js` jest realne, więc pilnuje go
  `tools/check-blocks.php` (uruchamiane przed każdym commitem dotykającym bloków).

---

<a name="adr-014"></a>
## ADR-014 — Testy warstwy Domain bez frameworka

**Status:** Zaakceptowany · Session 2

**Kontekst.** `composer.json` deklaruje PHPUnit, PHPCS i PHPStan jako zależności deweloperskie.
W środowisku, w którym powstaje ten kod, `composer install` nie może pobrać pakietów —
proxy blokuje uwierzytelnianie do github.com. Bez uruchamialnych testów pozostaje pisanie
kodu „na wiarę”.

**Decyzja.** Warstwa Domain ma własny mikro-runner: `tools/run-tests.php` + `tools/TestCase.php`
(łącznie ~150 linii, tylko używane asercje). Testy leżą w `tests/Domain/`.

**Uzasadnienie.** Warstwa Domain z założenia nie zna WordPressa i nie ma zależności
(ADR-002), więc jej testy nie potrzebują ani bootstrapu WP, ani frameworka. Runner działa
wszędzie, gdzie jest PHP — łącznie z rozpakowanym archiwum. Koszt: sto pięćdziesiąt linii.
Korzyść: testy faktycznie się uruchamiają, zamiast być zadeklarowane.

Pierwszy wynik tej decyzji pojawił się natychmiast: test wykrył, że `Plan::limit()` zamieniał
„bez limitu” na limit zerowy (operator `??` reaguje na `null`), przez co plan Pro blokowałby
tworzenie galerii najdroższym klientom.

**Konsekwencje.**
- PHPUnit pozostaje w `composer.json` jako docelowy toolchain. Migracja tych testów to
  zamiana klasy bazowej i nazw asercji — świadomie trzymamy się podzbioru zgodnego z PHPUnit.
- Runner obsługuje **wyłącznie** warstwę Domain. Testy integracyjne, REST i izolacji tenantów
  (Session 3 i 6) wymagają środowiska WordPressa i frameworka — tam mikro-runner nie wystarczy.
- Nie rozbudowujemy runnera. Jeśli zacznie mu brakować funkcji, to sygnał, żeby przejść
  na PHPUnit, a nie żeby pisać własny framework.


---

<a name="adr-015"></a>
## ADR-015 — Kierunek wizualny: Obsidian (ciemna precyzja) + warstwa ruchu

**Status:** Zaakceptowany · Session 3
**Zastępuje:** ADR-009 (Atelier + Studio OS) i ADR-010 (brak globalnego dark mode).

**Kontekst.** Właściciel produktu odrzucił kierunek Atelier: *„styl musi mieć animacje i wygląd
najlepszej platformy premium, a nie papieru i terakoty”*. Przedstawiono trzy kierunki premium
(Obsidian, Kinetic, Spectrum) oraz trzy poziomy intensywności ruchu.

**Decyzja.**
```
Cała powierzchnia produktu  →  OBSIDIAN, ciemna
Ruch                        →  wyrazisty, wyłącznie natywny CSS + Web Animations API
Biblioteka animacji         →  BRAK (GSAP odrzucony przez właściciela)
Motywy galerii klienta      →  zostają: Noir (domyślny) · Paper · Minimal
```

| | |
|---|---|
| Baza | `#08080A` — czerń z lekkim chłodnym odcieniem, nie czysta |
| Powierzchnie | warstwowe: `#101014` → `#17171D` → `#1F1F27` |
| Obrysy | biel o niskiej przezroczystości, nie szarości — poprawnie się nawarstwiają |
| Tekst | biel pełna w nagłówkach, 70% w treści, 45% w etykietach |
| Akcent | elektryczny błękit `#4D7CFF`, sygnałowy cyjan `#38E8D0` — dwa, nigdy więcej |
| Typografia | display **Bricolage Grotesque** · UI **Geist Sans** · dane **Geist Mono** — wszystkie OFL |
| Promień | 6–10 px zamiast 2 px z Atelier |

**Uzasadnienie.** Ciemne tło jest funkcjonalnie właściwe dla produktu, którego bohaterem jest
fotografia — zdjęcia świecą, a interfejs się cofa. Dodatkowo znosi to podział z ADR-009
(jasny marketing, ciemna galeria) na rzecz jednego systemu, co zmniejsza powierzchnię QA
zamiast ją zwiększać, wbrew temu, czego obawiał się ADR-010.

**Dlaczego bez biblioteki animacji.** Scroll reveals, animowane liczniki, podświetlenie
podążające za kursorem i przejścia stanów realizuje się natywnie: `IntersectionObserver`,
`Web Animations API`, właściwości niestandardowe CSS i `View Transitions`. GSAP kosztowałby
~70 KB gzip — trzykrotność całego obecnego budżetu JS landingu — za funkcje, których tu
nie potrzebujemy.

**Gdzie przebiega granica.** Ruch ma coś pokazywać, nie zdobić. Licznik dopłaty liczący się
przy wejściu w widok tłumaczy działanie produktu. Parallax na tle nie tłumaczy niczego.

**Dwa gradienty dopuszczone jako świadome wyjątki** od zakazu z `CLAUDE.md` §7:
poświata otoczenia w sekcji hero i obrys planu rekomendowanego w cenniku. Każdy inny wymaga
osobnej decyzji.

**Konsekwencje.**
- Koszt zmiany jest niski, bo tokeny od początku były semantyczne (`--kadr-surface`, nie
  `--kadr-beige`). Wymianie podlegają wartości tokenów, dochodzi warstwa ruchu i typografia.
  **Markup dziewięciu bloków, warstwa domenowa, cennik, zgody i testy pozostają nietknięte.**
  To jest moment, w którym decyzja z ADR-009 o semantycznych tokenach się zwróciła.
- `prefers-reduced-motion` przestaje być formalnością i staje się realną ścieżką: przy jego
  włączeniu strona musi być w pełni czytelna i kompletna bez ani jednej animacji.
- Kontrast trzeba sprawdzać na ciemnym tle — tekst 70% bieli na `#101014` daje ~11:1,
  ale akcent `#4D7CFF` na ciemnym wymaga weryfikacji przy każdym użyciu na tekście.
- Budżet JS landingu rośnie z 2,1 KB do **3,8 KB gzip** (szacowałem ~10 KB — wyszło mniej,
  bo warstwa ruchu to same natywne API). Limit 30 KB pozostaje bez zmian.
- Powstało `tools/check-contrast.php`, czytające paletę wprost z `tokens.css`. Audyt wykrył
  dwa błędy jeszcze przed wdrożeniem: etykiety 4,35:1 i biel na przycisku głównym 3,72:1.


---

<a name="adr-016"></a>
## ADR-016 — Własna kolejka zadań zamiast Action Scheduler

**Status:** Zaakceptowany · Sesja 4
**Zastępuje:** ADR-004.

**Kontekst.** ADR-004 wybrał Action Scheduler z uzasadnieniem „kolejka jest infrastrukturą,
nie wyróżnikiem produktu — pisanie jej od zera to koszt bez zwrotu”. Przy próbie wdrożenia
okazało się, że w środowisku, w którym powstaje ten kod, nie da się pobrać żadnego pakietu:
`composer install` nie uwierzytelnia się do github.com, a Packagist jest nieosiągalny.
To zmusiło do ponownej oceny, zamiast zablokowania prac.

**Rozważone warianty.**
- **A — Action Scheduler.** Dojrzały, miliony instalacji. Wymaga pobrania i dołączenia
  ~500 KB kodu, którego w tym środowisku nie da się zdobyć.
- **B — `wp_cron` bez tabeli zadań.** Odpala się tylko przy ruchu, nie ma współbieżności,
  nie ma ponawiania ani dzierżawy. Niewystarczające dla przetwarzania 800 zdjęć.
- **C — własna kolejka na tabeli.** ~250 linii, oparta o istniejące narzędzia schematu.

**Decyzja.** **C.** `DatabaseQueue` za niezmienionym interfejsem `Queue`.

**Uzasadnienie — dlaczego to nie jest tylko kapitulacja wobec środowiska.**
1. Mieliśmy już całą infrastrukturę: deklaratywny schemat, migracje, kontrakt bazy
   i testy na prawdziwym silniku SQL. Koszt okazał się dnia pracy, nie dwóch tygodni,
   jak szacował ADR-004.
2. Action Scheduler rozwiązuje problem szerszy niż nasz: cykliczne akcje, interfejs
   administracyjny, zgodność wsteczna z WooCommerce. Z tego wszystkiego potrzebujemy
   zajęcia zadania, ponowienia i dzierżawy.
3. **Limit współbieżności per tenant**, którego Action Scheduler nie ma, jest u nas
   wymaganiem produktowym: jeden fotograf wysyłający wesele nie może zagłodzić kolejki
   pozostałych. W wariancie A trzeba by to obejść z zewnątrz.
4. Zachowujemy zasadę zera zależności produkcyjnych.

**Co kolejka gwarantuje.** Zajęcie zadania odporne na wyścig dwóch workerów · ponawianie
z rosnącym opóźnieniem (1→2→4→8→16 min, górna granica godzina) · limit prób · zwolnienie
zadań po awarii procesu roboczego · priorytety · limit współbieżności per tenant ·
anulowanie zadań oczekujących, ale nie tych w trakcie.

**Konsekwencje.**
- Utrzymanie kolejki jest nasze. Mitygacja: 14 testów na prawdziwym silniku SQL,
  w tym scenariusze wyścigu, wygasłej dzierżawy i zagłodzenia.
- Pobieranie zadań jest ponadtenantowe, co łamie regułę „każdy indeks zaczyna się
  od `tenant_id`”. Wyjątek jest zadeklarowany jawnie metodą `crossTenantReads()`
  z uzasadnieniem, a test pilnuje, że uzasadnienie istnieje i że wiersze nadal
  należą do tenantów.
- Wyzwalanie workera stoi na `wp_cron`. Dla dużych instalacji dokumentacja wdrożeniowa
  opisze przejście na systemowy `cron` — to zmiana konfiguracji, nie kodu.

---

<a name="adr-017"></a>
## ADR-017 — S3 odłożone; magazyn lokalny jako pełna implementacja

**Status:** Zaakceptowany · Sesja 4
**Uszczegóławia:** ADR-011.

**Kontekst.** ADR-011 zakładał `async-aws/s3` jako klienta object storage. Tej zależności,
jak każdej innej, nie da się w tym środowisku pobrać (patrz ADR-016).

**Decyzja.** `StorageProviderInterface` i `LocalStorage` powstają w pełni teraz.
Implementacja S3 **nie powstaje** do momentu, w którym będzie potrzebna — czyli gdy
pojawi się instalacja z realnym wolumenem danych.

**Uzasadnienie.** Napisanie adaptera S3, którego nie da się uruchomić ani przetestować
przeciwko prawdziwej usłudze, dałoby kod wyglądający na gotowy i niesprawdzony w jedynym
miejscu, które ma znaczenie — w kontakcie z usługą. To jest gorsze niż brak kodu, bo
brak jest widoczny, a fałszywa gotowość nie.

Interfejs jest zaprojektowany pod obie implementacje (`temporaryUrl` zwraca w wariancie
lokalnym adres kontrolowanego endpointu, a w S3 podpisany URL), więc dołożenie adaptera
nie zmieni ani jednej linii kodu aplikacyjnego.

**Gdy przyjdzie moment — dwie drogi, decyzja wtedy:**
- podpisywanie SigV4 własnym kodem (~120 linii) na `wp_remote_request`, bez zależności;
  poprawność da się sprawdzić przeciwko opublikowanym wektorom testowym AWS,
- `async-aws/s3` zgodnie z pierwotnym planem, jeśli okaże się, że multipart i ponawianie
  są warte zależności.

**Konsekwencje.** MVP działa na dysku lokalnym, co dla jednego fotografa jest poprawne,
a dla platformy z tysiącem fotografów nie wystarczy. Pozycja pozostaje otwarta
w `PROJECT_STATE.md` (kwestia O3) i jest warunkiem skalowania, nie warunkiem premiery.


---

<a name="adr-018"></a>
## ADR-018 — Preact + Signals + htm jako dołączone moduły ES, bez bundlera

**Status:** Zaakceptowany · Sesja 6
**Domyka:** otwartą kwestię z ADR-013 („bundler dla `/app`”, kwestia O7).

**Kontekst.** ADR-013 celowo nie rozstrzygnął, czym budować panel fotografa, bo w sesji 2
nie było wiadomo, jak ciężki będzie ten interfejs. Dziś wiadomo: tabele z sortowaniem
i filtrowaniem, dialogi, przeciąganie zdjęć, wysyłanie z postępem, paleta poleceń,
kalendarz, a w sesji 8 siatka z półtora tysiąca kadrów i wirtualizacją.

**Rozważone warianty.**
- **A — dalej bez frameworka, czysty JavaScript.** Zero zależności, ale przy tej liczbie
  stanowych widoków skończyłoby się pisaniem własnej warstwy reaktywnej — gorszej
  od istniejących i bez dokumentacji dla kogokolwiek poza autorem.
- **B — Preact + Signals z bundlerem.** Standardowe podejście, ale wtyczka przestałaby
  działać zaraz po rozpakowaniu, a to była główna korzyść z ADR-013.
- **C — Preact + Signals + htm jako gotowe moduły ES, dołączone do wtyczki, ładowane
  ścieżkami względnymi.**

**Decyzja.** **C.**

**Uzasadnienie.**
1. Wszystkie trzy biblioteki publikują buildy ES, więc przeglądarka ładuje je wprost.
   **Krok budowania nie jest potrzebny** — korzyść z ADR-013 zostaje nienaruszona.
   Nie potrzeba nawet mapy importów: skrypt przepisuje odwołania na ścieżki względne,
   więc przeglądarka rozwiązuje je sama.
2. `htm` daje składnię bliską JSX przez szablony tagowane, za 660 bajtów gzip.
   JSX bez kompilacji nie działa; to jest jego zamiennik, a nie proteza.
3. Koszt: **9,9 KB gzip** na cały runtime. Budżet JS panelu to 120 KB, więc zostaje
   ponad 90% na właściwy kod.
4. Signals rozwiązują dokładnie ten problem, który mamy w Selection Roomie: licznik
   dopłaty ma się przeliczać przy każdej zmianie wyboru, bez ręcznego odświeżania widoku.

**Konsekwencje.**
- **Deklaracja „zero zależności produkcyjnych” przestaje obowiązywać.** Wtyczka dołącza
  trzy biblioteki: preact i @preact/signals (MIT) oraz htm (Apache-2.0). Spis, wersje
  i procedura aktualizacji: `assets/vendor/LICENSES.md`, skrypt `tools/vendor-preact.sh`.
- Pliki są dołączone, nie pobierane — instalacja nadal nie wymaga npm ani Composera.
- Odwołania między pakietami przepisujemy na ścieżki plików, bo ładujemy je bez bundlera.
  Robi to skrypt, nie ręka, i jest to jedyna zmiana w kodzie bibliotek.
- **Landing pozostaje bez frameworka.** Ta decyzja dotyczy wyłącznie `/app`, `/k` i `/g`.
  Strona marketingowa ma dalej 3,8 KB JS i nie ładuje ani bajta Preacta.

---

## ADR-019 — Własne ekrany rejestracji i logowania zamiast `wp-login.php`

**Status:** Zaakceptowany · Sesja 7

**Kontekst.** Fotograf musi się skądś zalogować. WordPress ma gotowy ekran
(`wp-login.php`) z obsługą ciasteczek, resetu hasła i ochroną przed atakami —
napisanie własnego to wzięcie na siebie pracy, którą ktoś już wykonał.

**Rozważone warianty.**
- **A — `wp-login.php` ze stylowaniem.** Najtaniej. Ale ekran logowania jest
  pierwszą rzeczą, którą fotograf widzi każdego dnia, a ten ekran mówi
  „to jest WordPress", zanim ktokolwiek zdąży cokolwiek kliknąć. Stylowanie
  go przez `login_enqueue_scripts` to walka ze strukturą, której nie kontrolujemy.
- **B — własne trasy `/rejestracja` i `/logowanie`, hasło sprawdza WordPress.**
- **C — całkowicie własne uwierzytelnianie fotografa.** Odrzucone od razu:
  własna kryptografia haseł to ryzyko bez żadnej korzyści.

**Decyzja.** **B.**

**Uzasadnienie.**
1. **Reguła §4.5 briefu jest kategoryczna**: fotograf nigdy nie widzi WordPressa.
   Ekran logowania nie jest od niej wyjątkiem.
2. **Hasło dalej liczy WordPress** (`wp_signon`, `wp_insert_user`) — nie piszemy
   ani linii własnej kryptografii, zgodnie z ADR-003.
3. Rejestracja zakłada konto **i studio w jednym kroku**. Rozdzielenie ich to
   dodatkowy ekran, na którym część ludzi odpada, a konto bez studia nie ma
   czym zarządzać.

**Konsekwencje.**
- Trzy rzeczy, o które rdzeń WordPressa by nie zadbał, są nasze: limit prób
  na konto i adres IP, **jeden komunikat** dla złego hasła i nieistniejącego
  konta, oraz sprawdzenie uprawnienia `kadr_access_app` po zalogowaniu.
- Adres powrotu po zalogowaniu jest ograniczony do ścieżek wewnątrz `/app`.
  Formularz przyjmujący dowolny adres zwrotny jest nośnikiem phishingu.
- `wp-login.php` nadal działa dla administratora platformy. Nie wyłączamy go —
  to jedyna droga awaryjna, gdy nasze trasy padną.
- Reset hasła nie ma jeszcze własnego ekranu. Do czasu, aż powstanie,
  prowadzi przez `wp-login.php?action=lostpassword` (kwestia O11).

---

## ADR-020 — Własna, przyrostowa implementacja SHA-256 w przeglądarce

**Status:** Zaakceptowany · Sesja 7

**Kontekst.** Wysyłanie zdjęcia wymaga skrótu SHA-256 całego pliku: serwer
weryfikuje nim scalony transfer, a przed transferem używa go do wykrycia
duplikatu. Przeglądarka ma `crypto.subtle.digest`.

**Problem.** `crypto.subtle.digest` przyjmuje **całą** zawartość naraz.
Plik RAW z wesela ma sto megabajtów, a fotograf wysyła ich osiemset.
Oznacza to wczytanie każdego pliku w całości do pamięci tylko po to,
żeby policzyć skrót — i drugi raz, żeby go wysłać.

**Rozważone warianty.**
- **A — `crypto.subtle.digest` na całym pliku.** Zero kodu, ale szczyt zużycia
  pamięci to dwukrotność rozmiaru pliku, przy każdym pliku.
- **B — biblioteka z przyrostowym SHA-256** (np. `hash-wasm`). Działa, ale to
  zależność produkcyjna dla stu linii algorytmu opisanego w RFC.
- **C — własna implementacja przyrostowa, licząca skrót przy okazji czytania
  fragmentów, które i tak lecą na serwer.**

**Decyzja.** **C.**

**Uzasadnienie.**
1. Jedno przejście przez plik zamiast dwóch i **stała pamięć** niezależnie
   od rozmiaru pliku.
2. To nie jest „pisanie własnej kryptografii" w sensie, przed którym
   przestrzega ADR-003: SHA-256 to funkcja skrótu o publicznej specyfikacji
   (RFC 6234), używana tu do wykrycia uszkodzonego transferu — nie do
   ochrony haseł ani podpisywania czegokolwiek.
3. Reguła §8: sto linii natywnego kodu zamiast zależności.

**Konsekwencje.**
- **Implementacja MUSI być weryfikowana wektorami testowymi.** Kryptografia
  napisana „na oko" jest najgorszą możliwą rzeczą. Atrapa serwera w podglądzie
  liczy skrót niezależnie (`crypto.subtle.digest`) i odrzuca niezgodny, więc
  test w przeglądarce sprawdza naszą implementację na prawdziwym pliku.
- `digest()` zapamiętuje wynik. Drugie wywołanie zwracało wcześniej śmieci,
  a zły skrót oznacza odrzucenie poprawnie przesłanego pliku — awaria, która
  wygląda jak uszkodzony transfer i której nie da się zdiagnozować z zewnątrz.
- Jeśli kiedyś pojawi się potrzeba innego algorytmu, ta decyzja się nie skaluje
  i wtedy wraca wariant B.

---

## ADR-021 — Paginacja kursorowa po `public_id`

**Status:** Zaakceptowany · Sesja 7

**Kontekst.** Listy w panelu (galerie, klienci, zamówienia) muszą stronicować.
`OFFSET` jest domyślnym wyborem i jest zły z dwóch niezależnych powodów:
`OFFSET 50000` skanuje pięćdziesiąt tysięcy wierszy, żeby oddać dwadzieścia,
a wstawienie wiersza w trakcie przeglądania przesuwa wszystkie strony.

**Rozważone warianty.**
- **A — `OFFSET`.** Prosty, wolny i gubiący wiersze.
- **B — kursor po `id` (autoinkrement).** Szybki, ale sekwencyjny identyfikator
  wyciekałby do API, co §5 briefu wyklucza. Maskowanie go to dodatkowy kod
  i dodatkowy sekret.
- **C — kursor po `public_id` (ULID).**

**Decyzja.** **C.**

**Uzasadnienie.** ULID koduje czas utworzenia, więc sortowanie po `public_id`
daje tę samą kolejność co po `created_at`, a kolumna jest unikalna — strona
nie gubi ani nie dubluje wierszy. Jednocześnie kursor **jest już publicznym
identyfikatorem**, więc nie ma czego ukrywać ani maskować.

**Konsekwencje.**
- Metoda `findPageBy` leży w `TenantRepository`, czyli w warstwie izolacji —
  nie da się jej wywołać bez tenanta. Kursor z cudzego tenanta nie zwraca
  cudzych wierszy; pilnuje tego test.
- Tabele bez `public_id` (np. `asset_variants`) nie mogą z niej korzystać
  i kończą się wyjątkiem, a nie cichym zapytaniem bez kursora.
- Sortowanie list jest po czasie utworzenia. Sortowanie po innej kolumnie
  (np. nazwie) będzie wymagało osobnego mechanizmu — wtedy wróci ten ADR.

---

## ADR-022 — Galeria klienta renderowana przez serwer, bez frameworka

**Status:** Zaakceptowany · Sesja 8

**Kontekst.** ADR-018 dołożył Preacta do panelu. Naturalnym odruchem było użyć
go również w galerii klienta — to ten sam produkt i ten sam zespół komponentów.

**Persona rozstrzygająca.** Klientka fotografa: **telefon, 22:30, jedną ręką,
czasem słaby zasięg.** Nie zakłada konta, nie instaluje aplikacji, nie czyta
instrukcji. Klika link z SMS-a i chce zobaczyć zdjęcia.

**Rozważone warianty.**
- **A — Preact, jak w panelu.** Spójność technologiczna. Ale pierwsze zdjęcie
  pojawia się dopiero po: pobraniu dokumentu → pobraniu 10 KB modułów →
  wykonaniu ich → żądaniu do API → pobraniu zdjęcia. Pięć kroków, z czego
  cztery przed pierwszym pikselem fotografii.
- **B — serwer renderuje pierwszy ekran razem z pierwszymi kadrami, a mały
  skrypt dokłada lightbox i doczytywanie.**

**Decyzja.** **B.**

**Uzasadnienie.**
1. **Zdjęcia są w dokumencie.** Przeglądarka zaczyna je pobierać, zanim
   wykona choćby linię JavaScriptu. To jest cała różnica dla LCP na 4G.
2. **Galeria działa bez skryptu.** Zdjęcia, okładka, opis i przewijanie
   są w HTML-u. Skrypt dokłada wygodę, nie treść.
3. **Skrypt waży 3,2 KB gzip** przy budżecie 60 KB. Preact + integracja
   zjadłyby trzykrotnie tyle, żeby zrobić jeden dialog.
4. ADR-018 dotyczy panelu, w którym stanowych widoków są dziesiątki.
   Tutaj stanem jest numer otwartego zdjęcia.

**Konsekwencje.**
- Markup galerii powstaje w PHP (`Presentation\Client\GalleryMarkup`), nie w JS.
  Zmiana wyglądu kadru dotyka jednego miejsca.
- Doczytywanie kolejnych kadrów zwraca **fragment HTML-a**, nie JSON —
  przeglądarka i tak zamieniłaby ten JSON na dokładnie ten sam markup.
- Adresy wariantów i pobrania są wypisane w atrybutach, a nie wyliczane
  z adresu miniatury. Przeróbka adresu wyrażeniem regularnym psuje się
  po cichu przy pierwszej zmianie ścieżki.
- Selection Room (sesja 9) będzie miał stan (wybory, licznik dopłaty)
  i tam ta decyzja wróci do rozważenia — ale jako osobna warstwa doklejona
  do tej galerii, nie jako jej przepisanie.

---

## ADR-023 — Układ galerii: rzędy o stałej wysokości, nie kolumny

**Status:** Zaakceptowany · Sesja 8

**Kontekst.** Galeria musi pokazać kadry o różnych proporcjach — pion i poziom
wymieszane, tak jak wychodzą z sesji.

**Rozważone warianty.**
- **A — CSS `columns` (masonry kolumnowy).** Najprostszy. **Odrzucony po
  zobaczeniu w przeglądarce:** treść wypełnia kolumnę do końca, zanim przejdzie
  do następnej. Przy galerii ślubnej znaczy to, że klientka przewija w dół całą
  lewą połowę serii (kadry 1–400), a potem wraca na górę po prawą.
  **Kolejność zdjęć jest chronologiczna i ma znaczenie** — sesja to opowieść.
- **B — siatka o równych kafelkach.** Wymaga przycięcia kadru do wspólnych
  proporcji, czyli ingerencji w pracę fotografa.
- **C — rzędy o stałej wysokości, w których każdy kadr ma szerokość wynikającą
  z własnych proporcji, a rząd dociąga się do pełnej szerokości.**

**Decyzja.** **C.**

**Uzasadnienie.** Kolejność zachowana, kadr nieprzycięty (poza kilkoma
pikselami dociągnięcia rzędu), zero JavaScriptu. Proporcje wpisuje serwer —
zna wymiary każdego zdjęcia, więc układ jest gotowy w HTML-u i nic nie skacze.

**Konsekwencje.**
- Ostatni rząd wymaga elementu domykającego, inaczej pojedynczy kadr
  rozciągnąłby się na całą szerokość.
- Wysokość rzędu jest responsywna (`clamp`), a na telefonie dobrana tak,
  żeby w rzędzie mieściły się co najmniej dwa kadry — jeden na ekran gubi
  rytm serii.
- Pilnuje tego test w przeglądarce: pierwszy rząd musi zawierać kadry 1 i 2.

---

## ADR-024 — Jedno wyjście poza tenanta dla publicznego linku

**Status:** Zaakceptowany · Sesja 8

**Kontekst.** Klientka otwiera `/g/{token}`, nie będąc nikim zalogowanym.
Nie ma sesji, konta ani tenanta — przynosi wyłącznie token. Tymczasem
każde repozytorium wymaga `TenantContext` (CLAUDE.md §4.4).

**Decyzja.** Jedna klasa w `Infrastructure\Database\Platform\GalleryLookup`
zamienia hash tokenu na identyfikator tenanta i galerii. **Nic więcej.**
Od tego momentu wszystko idzie przez zwykłe repozytoria z `TenantContext`
zbudowanym z tego, co znalazł token.

**Uzasadnienie.**
1. Wyszukiwanie jest po `token_hash`, który jest UNIQUE globalnie i ma
   256 bitów entropii. Nie da się go zgadnąć ani przeszukać.
2. Klasa nie czyta ani jednego zdjęcia, klienta ani ustawienia — zasięg
   wyjścia poza tenanta jest ograniczony do jednego wiersza.
3. Leży w `Platform\`, tak jak `TenantStore`, więc **widać w imporcie**,
   że kod wychodzi poza tenanta.

**Konsekwencje.**
- Kontekst tenanta dla klientki powstaje z rolą `Member` i **pustą listą
  uprawnień** — nie jest członkiem zespołu i nie ma prawa do niczego poza
  odczytem tej jednej galerii.
- Zdjęcie musi należeć do TEJ galerii, nie tylko do tego tenanta. Inaczej
  jeden link otwierałby cały dorobek fotografa; pilnuje tego kontroler obrazka.
- Każdy powód odmowy — zły token, wygaśnięcie, unieważnienie, wycofanie
  publikacji — zwraca **identyczny komunikat**. Rozróżnianie ich mówiłoby
  zgadującemu, że trafił w istniejący link.
- Wycofanie galerii z publikacji zamyka wszystkie wydane linki naraz.
  Fotograf ma jeden przełącznik, nie dwa.

---

## ADR-025 — Ulubione i wybrane to dwa osobne stany

**Status:** Zaakceptowany · Sesja 9

**Kontekst.** Najprostszy model wyboru to jeden bit na zdjęciu: wzięte albo
nie. Tak robi większość narzędzi i tak podpowiada baza danych.

**Decyzja.** Zdjęcie ma trzy możliwe stany — `favorite`, `selected`,
`rejected` — i **tylko `selected` liczy się do pakietu i do dopłaty**
(`Domain\Selection\SelectionState::countsTowardsPackage()`).

**Uzasadnienie.** Klientka przechodzi galerię dwa razy. Za pierwszym
razem reaguje na zdjęcia — serduszkuje to, co jej się podoba, i jest ich
zwykle czterdzieści. Za drugim zawęża je do tego, co faktycznie zamawia.
Jeden bit sklejałby te przejścia i zmuszał ją do decyzji zakupowej przy
pierwszym obejrzeniu kadru. To jest dokładnie ten moment tarcia, który
produkt ma usuwać — a przy okazji ten, w którym klientka wybiera mniej,
bo boi się, że każde kliknięcie kosztuje.

**Konsekwencje.**
- Licznik dopłaty reaguje wyłącznie na „wybrane”. Serduszko jest darmowe
  i wolno go używać bez zastanowienia.
- Ulubione są widoczne dla fotografa jako osobna liczba — mówią mu,
  co klientce się podobało, nawet jeśli tego nie zamówiła.
- `rejected` jest w modelu, ale w v1.0 nie ma dla niego przycisku.
  Wejdzie, gdy pojawi się wybór w kilku rundach z komentarzami.

---

## ADR-026 — Liczby do rozliczenia liczy serwer, nigdy przeglądarka

**Status:** Zaakceptowany · Sesja 9

**Kontekst.** Licznik dopłaty musi liczyć się natychmiast przy każdym
kliknięciu, więc przeglądarka i tak zna arytmetykę. Kuszące jest przysłanie
gotowego wyniku przy zatwierdzeniu — serwer miałby mniej pracy.

**Decyzja.** `SelectionRoom::submit()` **przelicza wszystko od nowa**
z bazy i zapisuje swoje liczby. Wartości z żądania są ignorowane.
Skrzynka wyborów pokazuje potem liczby **zamrożone w chwili zatwierdzenia**
(`included_count`, `extra_count`), nie przeliczane wstecz.

**Uzasadnienie.**
1. Kwota dopłaty jest kwotą na fakturze. Nie może zależeć od tego, co
   klient wyśle w żądaniu — to jest zwykły przypadek manipulacji ceną.
2. Zamrożenie działa w drugą stronę: fotograf podniesie cenę zdjęcia ponad
   pakiet i to jest normalne. Gdyby skrzynka przeliczała stare wybory po
   nowym cenniku, jego faktura rozjechałaby się z tym, na co klientka się
   zgodziła.

**Konsekwencje.**
- Arytmetyka istnieje w dwóch miejscach (`PackageTally` na serwerze,
  licznik w przeglądarce) i musi się zgadzać. Pilnuje tego bramka:
  28 zdjęć przy pakiecie 20 i cenie 60 zł daje 480 zł — sprawdzane
  i testem PHP, i testem w przeglądarce na wyrenderowanym liczniku.
- Zatwierdzony wybór jest zamknięty (`kadr_selection_closed`).
  Ponowne otwarcie to jawna decyzja fotografa, nie efekt uboczny.

---

## ADR-027 — Żądanie zmiany kolejności opisuje zamiar, nie gotową listę

**Status:** Zaakceptowany · Sesja 9

**Kontekst.** Naturalny kształt takiego API to „oto nowa kolejność” —
tablica wszystkich identyfikatorów. Siatka w panelu jest jednak
wirtualizowana i doczytywana stronami: przeglądarka zna tylko wycinek.

**Decyzja.** `PATCH /galleries/{id}/assets/order` przyjmuje
`{ move: [...], before: id|null }` — „przenieś te kadry przed ten”.
Pełną kolejność wylicza serwer z własnej listy
(`Application\Gallery\ArrangeGallery`).

**Uzasadnienie.**
1. Gdyby przeglądarka przysyłała „całą” kolejność, kadry, do których
   jeszcze nie doszła, wypadłyby na koniec galerii. Cicha utrata układu
   ośmiuset zdjęć przy jednym przeciągnięciu.
2. Tysiąc identyfikatorów to ~30 kB na każdy ruch myszy.
3. Zamiar da się zweryfikować: kadr musi należeć do TEJ galerii.
   Gotowej listy nie da się odróżnić od przypadkowej.

**Konsekwencje.**
- `AssetRepository::reorder()` zapisuje **wyłącznie wiersze, które
  faktycznie zmieniły pozycję**. Przesunięcie kadru o dwa miejsca w
  tysiącu zdjęć to trzy zapisy, nie tysiąc.
- Przeciąganie działa w obrębie widocznego okna siatki — dalej nie ma
  elementu DOM, w który dałoby się celować. Dalekie ruchy obsługuje
  zaznaczenie plus „Na początek” / „Na koniec”, czyli ruch, którego
  fotograf naprawdę potrzebuje: wybranie otwarcia galerii.

---

## ADR-028 — Polskie formy liczby mnogiej poza `_n()`

**Status:** Zaakceptowany · Sesja 9

**Kontekst.** Polski ma trzy formy mnogie (1 · 2–4 · reszta, z wyjątkiem
nastek). `_n()` WordPressa przyjmuje dwie, a trzecią bierze z pliku `.po`
języka docelowego. Przy polskim jako **języku źródłowym** takiego pliku
nie ma — więc licznik wypisywał „Wybrałaś 4 zdjęć”.

**Decyzja.** Reguła mieszka w dwóch miejscach, po jednym na stronę:
`Presentation\Support\Plural` w PHP i `plural()` / `Plural` w
`assets/js/app/runtime.js`. Każda forma przechodzi przez `__()` osobno,
więc tłumacz nadal może je odmienić.

**Uzasadnienie.** To jest tekst, który klientka czyta w chwili wydawania
pieniędzy. Błąd gramatyczny w tym zdaniu kosztuje wiarygodność dokładnie
tam, gdzie jest najdroższa.

**Konsekwencje.**
- Galeria klienta ma własną, trzecią kopię reguły (`assets/js/gallery/`),
  bo jej budżet to 60 kB na wszystko i nie importuje modułów panelu.
  Świadome powtórzenie ośmiu linii, nie przeoczenie.
- Nowe liczniki nie mogą używać `_n()` dla polskiego tekstu źródłowego.

---

## ADR-029 — Paczka powstaje w tle i da się ją przerwać w połowie

**Status:** Zaakceptowany · Sesja 10

**Kontekst.** Wesele to 30–80 GB w 500–1500 plikach (skill
photography-workflow §7). Spakowanie tego w jednym przebiegu PHP nie ma
prawa się udać: skończy się limitem czasu wykonania, a fotograf zobaczy
błąd po dwudziestu minutach czekania.

**Decyzja.** Zadanie `PackArchive` dopisuje **porcję pięćdziesięciu zdjęć**,
zamyka archiwum i wraca do kolejki. Stan trzyma tabela `kadr_archives`,
która jest jednocześnie postępem widocznym dla fotografa („pakuję 340
z 1200"). Pakujemy **bez kompresji** (`CM_STORE`).

**Uzasadnienie.**
1. JPEG jest już skompresowany — deflate wyciska z niego ułamek procenta,
   a kosztuje pełne przejście CPU po osiemdziesięciu gigabajtach.
2. `addFile()` czyta źródło dopiero przy zamknięciu archiwum, więc szczyt
   pamięci nie zależy od rozmiaru zdjęcia.
3. Ile już spakowano, pytamy **PLIK, nie licznik w bazie**. Zapis pliku
   i zapis wiersza to dwie osobne operacje; gdy serwer padnie między nimi,
   wiarygodne jest archiwum.

**Konsekwencje.**
- Każda porcja jest dokładana do kolejki jako NOWE zadanie, więc licznik
  ponowień dotyczy jednej porcji. Inaczej wesele wyczerpałoby limit po
  dwudziestu porcjach i umarło w połowie.
- Wymagamy rozszerzenia PHP `zip`. Ostrzeżenie w `Requirements` obok
  Imagicka — bo pisanie własnego ZIP64 z CRC-32 dla produktu trzymającego
  cudze zdjęcia rodzinne byłoby złą oszczędnością.
- Gotowa paczka **wygasa** (24 h). Trzymanie osiemdziesięciu gigabajtów
  bezterminowo to koszt, którego nikt nie zamówił.
- Plik brakujący w chwili pakowania jest pomijany, nie wywraca paczki.
  Reszta sesji jest dla klientki warta więcej niż komunikat o błędzie.

---

## ADR-030 — `finals` to oryginał wydany przez token, a nie kolejny wariant

**Status:** Zaakceptowany · Sesja 10

**Kontekst.** `docs/SECURITY.md` §4 mówi dwie rzeczy, które przy pobieżnym
czytaniu wyglądają sprzecznie: „oryginały nie są serwowane nigdy" oraz
„klient dostaje `view` (1800 px), a po opłaceniu — `finals` przez token
pobrania". Trzeba było rozstrzygnąć, czym jest `finals`.

**Decyzja.** `finals` **to plik oryginalny**, osiągalny wyłącznie przez
`/d/{token}`. Nie generujemy trzeciego pełnowymiarowego wariantu.

**Uzasadnienie.** Reguła „oryginały nie są serwowane" dotyczy OGLĄDANIA:
żaden ekran — ani w panelu, ani w galerii — nie pokazuje pliku źródłowego,
bo do obejrzenia wystarczy 1800 px, a pobranie 40 MB na podgląd jest
marnotrawstwem i wyciekiem. Wydanie kupionych plików to inna czynność:
świadoma, ograniczona czasowo, unieważnialna i zapisana w dzienniku.
Dodatkowy wariant „finals" byłby kopią oryginału — podwojeniem
osiemnastu milionów plików bez żadnej korzyści.

**Konsekwencje.**
- Ścieżka paczki leży w przestrzeni `finals/`, prywatnej jak wszystko inne.
  Nazwa jest przewidywalna, więc katalog **nie może** być publiczny.
- Wydanie i użycie tokenu trafiają do dziennika (`docs/SECURITY.md` §6),
  adres wyłącznie jako hash z solą instalacji.

---

## ADR-031 — Token pobrania bez limitu użyć, za to krótki

**Status:** Zaakceptowany · Sesja 10

**Kontekst.** Odruch przy tokenie do prywatnych plików mówi: jedno użycie
i koniec. Mechanizm jest gotowy (`max_uses` w schemacie).

**Decyzja.** Token pobrania **nie ma limitu użyć**, za to żyje **24 godziny**
(link do galerii — 90 dni) i można go unieważnić pojedynczo albo hurtem
dla całej galerii.

**Uzasadnienie.** Pobranie sześćdziesięciu gigabajtów przez domowe łącze
rwie się i zaczyna od nowa; przeglądarka wznawia transfer kolejnym żądaniem
z nagłówkiem `Range`. Limit „jedno użycie" zamieniłby zwykłą niedogodność
w utratę dostępu do własnych zdjęć — i wygenerował wiadomość do fotografa
zamiast go od niej uwolnić. Ochroną jest krótkie życie i unieważnialność,
nie licznik.

**Konsekwencje.**
- Endpoint musi obsługiwać `Range` (206 i 416), inaczej wznawianie nie
  działa i argument się rozsypuje.
- Każdy powód odmowy — zły token, wygasły, unieważniony, cudzy — daje
  IDENTYCZNY ekran i status. Rozróżnianie mówiłoby zgadującemu, że trafił.

---

## ADR-032 — Wiadomość do klientki prowadzi do galerii, nie do paczki

**Status:** Zaakceptowany · Sesja 10

**Kontekst.** Wiadomość „Twoje zdjęcia są gotowe" musi zawierać jakiś link.
Naturalny odruch: wkleić link do paczki.

**Decyzja.** Wiadomość prowadzi do **galerii** (`/g/{token}`), a wysyłka
jest **jawną decyzją fotografa** (przycisk w panelu), nie efektem ubocznym
spakowania.

**Uzasadnienie.**
1. Token pobrania żyje dobę. Klientka przeczyta maila w czwartek wieczorem
   albo za tydzień — link do paczki byłby martwy, zanim ktokolwiek go
   kliknie. Z galerii wyda sobie świeży jednym kliknięciem.
2. Jawnej wartości wcześniejszego linku do galerii nie da się odtworzyć
   z bazy (leży tam sam hash), więc wysyłka wydaje **nowy** link — przy
   okazji uczciwszy, bo stary mógł wygasnąć.
3. To fotograf decyduje, kiedy klientka dostaje wiadomość, i to jego
   nazwisko jest pod nią podpisane.

**Konsekwencje.**
- Wiadomość jest zwykłym tekstem, nie HTML-em: ma wyglądać jak wiadomość
  od człowieka, a nie jak mailing. Mniej powodów, żeby wpaść do spamu.
- Temat przechodzi przez `Message::singleLine()` — tytuł galerii jest
  danymi od użytkownika, a nagłówek z nową linią to wysyłka na cudze adresy.
- Treść mówi wprost, że **paczki nie otworzy telefon**. Bez tego zdania
  połowa klientek pobiera ją na telefon i pisze, że „nie działa"
  (skill photography-workflow §3).
