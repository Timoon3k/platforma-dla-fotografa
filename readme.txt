=== Kadr ===
Contributors: kadr
Tags: fotografia, galeria, proofing, rezerwacje, sprzedaż
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 8.2
Stable tag: 0.11.0
License: Proprietary

Platforma dla profesjonalnych fotografów: galerie proofingowe, wybór zdjęć,
dopłaty, odbitki, rezerwacje i dostawa gotowych materiałów.

== Description ==

Kadr prowadzi całą współpracę z klientem — od zapytania i rezerwacji, przez
galerię proofingową i wybór zdjęć, po dopłatę, odbitki i dostawę plików.

Wtyczka nie wymaga Composera ani narzędzi budujących. Po wgraniu i aktywacji
działa od razu.

= Stan wersji 0.11.0 =

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
* wybór zdjęć przez klientkę: ulubione, wybrane, licznik pakietu na żywo
* kwota dopłaty widoczna, zanim klientka cokolwiek zatwierdzi
* zatwierdzenie wyboru i ponowne otwarcie go przez fotografa
* skrzynka wyborów w panelu: kto już wybrał, kto jeszcze wybiera, ile czeka dopłat
* zaznaczanie wielu kadrów i układanie kolejności zdjęć w galerii
* paczka ZIP przygotowywana w tle, z możliwością przerwania i wznowienia
* pobieranie przez kontrolowany adres z wznawianiem przerwanego transferu
* linki do plików z dobowym wygaśnięciem i unieważnianiem
* wiadomość do klientki „Twoje zdjęcia są gotowe"
* dziennik zdarzeń: kto wydał link do plików i kto ich użył
* kreator pierwszego uruchomienia: przegląd instalacji i strona główna jednym kliknięciem
* oś procesu odpowiadająca na „kiedy będą zdjęcia?”, zanim padnie pytanie
* powiadomienie fotografa o zatwierdzonym wyborze, z kwotą dopłaty w temacie
* automatyczne sprzątanie wygasłych paczek

W budowie (sesje 12–16):

* portal klienta, tablica produkcji, historia komunikacji
* koszyk, płatności, abonamenty
* rezerwacje, CRM, automatyzacje, odbitki

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

= 0.11.0 =
* Kreator pierwszego uruchomienia — przegląd instalacji mówiący, CO naprawić i JAK
* Wykrywanie „zwykłych” odnośników, przez które cała platforma zwracała 404 bez wyjaśnienia
* Strona główna zakładana jednym kliknięciem, z ustawieniem jej jako startowej
* Lista adresów platformy — nie są stronami, więc nie widać ich w „Stronach”
* Oś procesu w galerii klientki: gdzie jesteśmy i co się stanie dalej
* Oś procesu w panelu fotografa, w jego języku
* Powiadomienie fotografa o zatwierdzonym wyborze — kwota dopłaty w temacie
* Sprzątanie wygasłych paczek: najpierw unieważnienie tokenów, potem plik
* Ekran pomocy działa także wtedy, gdy nie ma połączenia z bazą danych

= 0.10.0 =
* Paczka ZIP przygotowywana w tle przez kolejkę — pakowanie da się przerwać i wznowić
* Pakowanie bez kompresji: zdjęcia są już skompresowane, a 80 GB przez deflate to stracony czas
* Pobieranie przez `/d/{token}` z obsługą wznawiania przerwanego transferu
* Tokeny pobrania: hash w bazie, wygaśnięcie po dobie, unieważnianie pojedynczo i hurtem
* Panel: postęp pakowania „340 z 1200”, wydanie linku, powiadomienie klientki
* Galeria klientki: sekcja pobierania, mówiąca wprost, że paczki nie otworzy telefon
* Wiadomość „Twoje zdjęcia są gotowe” z linkiem do galerii, nie do wygasającej paczki
* Dziennik zdarzeń z zanonimizowanym adresem — kto wydał link i kto go użył
* Poprawka: w galerii bez trybu wyboru nie ma już martwych przycisków wyboru
* Poprawka: wyszukiwanie tabel po nazwie zamiast po pozycji w tablicy

= 0.9.0 =
* Wybór zdjęć w galerii klientki: ulubione i wybrane jako dwa osobne stany
* Licznik pakietu widoczny przez cały czas — kwota dopłaty liczona na żywo
* Zatwierdzenie wyboru zamyka go; fotograf może otworzyć go ponownie
* Liczby do rozliczenia liczone po stronie serwera, nigdy przysyłane przez przeglądarkę
* Panel wyboru w widoku galerii: wybrane, ulubione, ponad pakiet, do dopłaty
* Skrzynka „Wybory”: wszystkie wybory klientek w jednym miejscu, z sumą dopłat
* Zaznaczanie wielu kadrów (Shift zaznacza zakres) i zbiorcze usuwanie
* Układanie kolejności zdjęć przeciąganiem oraz „Na początek” / „Na koniec”
* Poprawne formy liczby mnogiej po polsku — trzy formy, nie dwie

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
