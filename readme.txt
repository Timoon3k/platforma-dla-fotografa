=== Kadr ===
Contributors: kadr
Tags: fotografia, galeria, proofing, rezerwacje, sprzedaż
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 8.2
Stable tag: 0.3.0
License: Proprietary

Platforma dla profesjonalnych fotografów: galerie proofingowe, wybór zdjęć,
dopłaty, odbitki, rezerwacje i dostawa gotowych materiałów.

== Description ==

Kadr prowadzi całą współpracę z klientem — od zapytania i rezerwacji, przez
galerię proofingową i wybór zdjęć, po dopłatę, odbitki i dostawę plików.

Wtyczka nie wymaga Composera ani narzędzi budujących. Po wgraniu i aktywacji
działa od razu.

= Stan wersji 0.3.0 =

Gotowe:

* strona marketingowa: dziewięć bloków Gutenberga, cennik, moduł zgód na cookies
* design system w kierunku Obsidian wraz z warstwą ruchu
* warstwa danych: czternaście tabel, migracje z wersją schematu
* izolacja tenantów wymuszona konstrukcyjnie, potwierdzona testami
* warstwa domenowa: rozliczenia planów, arytmetyka dopłaty za zdjęcia ponad pakiet

W budowie (sesje 4–15):

* wysyłanie zdjęć i pipeline obrazów
* panel fotografa, galeria klienta, Selection Room
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

= 0.3.0 =
* Warstwa danych: czternaście tabel, migracje, izolacja tenantów
* Kierunek wizualny Obsidian z warstwą ruchu
* 62 testy automatyczne

= 0.2.0 =
* Design system, dziewięć bloków Gutenberga, cennik, zgody na cookies

= 0.1.0 =
* Szkielet wtyczki i dokumentacja projektu
