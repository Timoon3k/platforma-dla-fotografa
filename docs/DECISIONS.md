# DECISIONS — Architecture Decision Records

Każda istotna decyzja ma tu wpis. **Decyzji nie zmienia się po cichu** — pisze się nowy ADR,
który jawnie zastępuje (`Zastąpiony przez: ADR-0XX`) poprzedni.

Szablon nowego ADR: [`adr/TEMPLATE.md`](adr/TEMPLATE.md)

| # | Decyzja | Status | Sesja |
|---|---|---|---|
| [001](#adr-001) | Nazwa produktu: Kadr | Zaakceptowany (warunkowo) | 1 |
| [002](#adr-002) | Architektura: WP jako platforma, wtyczka jako aplikacja | Zaakceptowany | 1 |
| [003](#adr-003) | Klient końcowy poza `wp_users` | Zaakceptowany | 1 |
| [004](#adr-004) | Kolejka zadań: Action Scheduler za `QueueInterface` | Zaakceptowany | 1 |
| [005](#adr-005) | Custom tables zamiast CPT dla danych transakcyjnych | Zaakceptowany | 1 |
| [006](#adr-006) | Płatności klientów: własne konto fotografa, BLIK w pierwszym adapterze | Zaakceptowany | 1 |
| [007](#adr-007) | Billing platformy: Stripe Billing | Zaakceptowany | 1 |
| [008](#adr-008) | Cennik i free tier oparty na projektach | Zaakceptowany | 1 |
| [009](#adr-009) | Kierunek wizualny: Atelier + Studio OS + motywy galerii | Zaakceptowany | 1 |
| [010](#adr-010) | Brak globalnego dark mode w v1.0 | Zaakceptowany | 1 |
| [011](#adr-011) | Storage: abstrakcja + Local/S3, cykl życia plików | Zaakceptowany | 1 |
| [012](#adr-012) | i18n od pierwszej linii, MVP po polsku | Zaakceptowany | 1 |
| [013](#adr-013) | Brak kroku budowania w warstwie marketingowej | Zaakceptowany | 2 |
| [014](#adr-014) | Testy warstwy Domain bez frameworka | Zaakceptowany | 2 |

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

**Status:** Zaakceptowany · Session 1

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

**Status:** Zaakceptowany · Session 1

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

**Status:** Zaakceptowany · Session 1

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
