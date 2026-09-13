=== Kadr ===
Contributors: kadr
Tags: fotografia, galeria, proofing, rezerwacje, sprzedaż
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 8.2
Stable tag: 0.8.0
License: Proprietary

Platforma dla profesjonalnych fotografów: galerie proofingowe, wybór zdjęć,
dopłaty, odbitki, rezerwacje i dostawa gotowych materiałów.

== Description ==

Kadr prowadzi całą współpracę z klientem — od zapytania i rezerwacji, przez
galerię proofingową i wybór zdjęć, po dopłatę, odbitki i dostawę plików.

Wtyczka nie wymaga Composera ani narzędzi budujących. Po wgraniu i aktywacji
działa od razu.

= Stan wersji 0.8.0 =

Gotowe:

* strona marketingowa: dziewięć bloków Gutenberga, cennik, moduł zgód na cookies
* design system w kierunku Obsidian wraz z warstwą ruchu
* warstwa danych: szesnaście tabel, migracje z wersją schematu
* izolacja tenantów wymuszona konstrukcyjnie, potwierdzona testami
* warstwa domenowa: rozliczenia planów, arytmetyka dopłaty za zdjęcia ponad pakiet
* magazyn plików, pipeline obrazów (warianty AVIF/WebP, usuwanie EXIF i GPS)
* kolejka zadań w tle, tokeny dostępu z wygaśnięciem
* wysyłanie zdjęć fragmentami z automatycznym generowaniem wariantów
* uwierzytelnianie klienta magic linkiem, bez zakładania konta
* ograniczanie liczby prób logowania i odgadywania PIN-u
* powłoka panelu fotografa i komplet komponentów interfejsu: tabela, dialogi,
  szuflada, formularze z walidacją, paleta poleceń, powiadomienia
* rejestracja studia i logowanie na własnych ekranach, poza panelem WordPressa
* panel fotografa: widok „Dzisiaj”, lista galerii, klienci
* tworzenie i edycja galerii: pakiet, cena zdjęcia ponad pakiet, termin ważności
* wysyłanie zdjęć z panelu i siatka kadrów z wirtualizacją
* galeria klienta pod własnym linkiem: trzy motywy, lightbox, ochrona PIN-em
* okładka galerii, podgląd oczami klientki, pobieranie pojedynczych zdjęć

W budowie (sesje 9–15):

* wybór zdjęć i dopłata za kadry ponad pakiet
* koszyk, płatności, abonamenty
* rezerwacje, CRM, automatyzacje, dostawa

== Installation ==

1. Wgraj katalog `kadr` do `wp-content/plugins/`.
2. Aktywuj wtyczkę w panelu WordPressa. Tabele powstaną automatycznie.
3. Utwórz stronę i wstaw wzorzec **Kadr → Strona główna — pełny układ**.

== Frequently Asked Questions ==

= Czy wtyczka wymaga Composera albo npm? =

Nie. Są potrzebne wyłącznie do narzędzi deweloperskich.

= Czy działa na współdzielonym hostingu? =

Nie jest wspierany. Wymagany VPS z rozszerzeniem Imagick — przetwarzanie zdjęć
na współdzielonym hostingu jest zbyt wolne i zbyt ograniczone limitami.

= Co dzieje się z danymi po odinstalowaniu? =

Nic. Odinstalowanie wtyczki nie usuwa danych. Usunięcie wymaga jawnej,
osobno potwierdzonej decyzji administratora.

== Changelog ==

= 0.8.0 =
* Galeria klienta pod adresem /g/{link}: pierwszy ekran renderowany przez serwer
* Trzy motywy galerii — Noir, Paper, Minimal — z audytem kontrastu w każdym
* Lightbox: klawiatura, przesunięcie palcem, pełny ekran, powrót fokusu
* Układ zachowujący kolejność zdjęć i proporcje kadru, bez przycinania
* Miniatura zastępcza wpisana w dokument — kadr ma kolor, zanim dojdzie plik
* Linki dla klientki z opcjonalnym PIN-em, limitem prób i unieważnianiem
* Okładka galerii i podgląd oczami klientki, prosto z panelu
* Pobieranie pojedynczego zdjęcia, gdy fotograf je włączył
* Skrypt galerii waży 3,2 KB gzip przy budżecie 60 KB
* 212 testów automatycznych i 89 sprawdzeń w prawdziwej przeglądarce

= 0.7.0 =
* Rejestracja studia i logowanie na własnych ekranach — fotograf nie widzi panelu WordPressa
* Panel renderowany przez wtyczkę pod adresem /app, poza motywem
* Widok „Dzisiaj” na jednym zapytaniu zagregowanym
* Lista galerii z filtrowaniem, wyszukiwaniem i stronicowaniem po stronie serwera
* Tworzenie i edycja galerii: pakiet, cena zdjęcia ponad pakiet, motyw, termin ważności
* Wysyłanie zdjęć z panelu: przeciąganie, postęp, wznawianie, wykrywanie duplikatów
* Siatka zdjęć z wirtualizacją i doczytywaniem kolejnych stron
* Miniatury serwowane przez kontrolowany endpoint, nigdy z katalogu plików
* 198 testów automatycznych i 52 sprawdzenia w prawdziwej przeglądarce

= 0.6.0 =
* Powłoka panelu fotografa: nawigacja, pasek górny, obszar treści, pełny widok na 375 px
* Komponenty: tabela z sortowaniem po stronie serwera, szkielety, puste stany
* Dialog, potwierdzenie operacji nieodwracalnej, szuflada boczna, popover
* Formularze: walidacja inline, błędy z serwera pod właściwym polem, kopia robocza
* Paleta poleceń ⌘K i powiadomienia z akcją cofnięcia
* Runtime panelu jako moduły ES bez kroku budowania — 9,9 KB gzip
* Weryfikacja panelu w prawdziwej przeglądarce: 18 sprawdzeń, zero błędów w konsoli

= 0.5.0 =
* Wysyłanie zdjęć fragmentami: wznawianie, wykrywanie duplikatów, kontrola limitu przed transferem
* Uwierzytelnianie klienta magic linkiem, sesje unieważnialne
* Ograniczanie liczby prób (logowanie, magic link, PIN galerii)
* Role i uprawnienia fotografa; panel WordPressa niedostępny dla fotografa
* Proces roboczy kolejki wyzwalany cronem
* 160 testów automatycznych

= 0.4.0 =
* Magazyn plików i pipeline obrazów z usuwaniem metadanych
* Własna kolejka zadań w tle
* Tokeny dostępu z wygaśnięciem i unieważnianiem
* 129 testów automatycznych

= 0.3.0 =
* Warstwa danych: czternaście tabel, migracje, izolacja tenantów
* Kierunek wizualny Obsidian z warstwą ruchu
* 62 testy automatyczne

= 0.2.0 =
* Design system, dziewięć bloków Gutenberga, cennik, zgody na cookies

= 0.1.0 =
* Szkielet wtyczki i dokumentacja projektu
