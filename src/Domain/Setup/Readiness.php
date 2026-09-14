<?php
declare( strict_types=1 );

namespace Kadr\Domain\Setup;

/**
 * Przegląd instalacji: czy ta platforma w ogóle może działać.
 *
 * DLACZEGO TO ISTNIEJE. Po wgraniu wtyczki nie dzieje się nic widocznego.
 * Trasy działają, ale strony głównej nie ma, a przy „zwykłych" odnośnikach
 * WordPressa cała platforma zwraca 404 — bez jednego słowa wyjaśnienia.
 * Administrator ma wtedy produkt, który wygląda na zepsuty, i zero tropu.
 *
 * Klasa należy do warstwy Domain i nie zna WordPressa: dostaje gotowe fakty
 * i orzeka, co z nich wynika. Dzięki temu reguły „co znaczy działająca
 * instalacja" da się przetestować bez WordPressa — a to jest jedyna warstwa
 * tego projektu, której w tym środowisku nie ma (kwestia O9).
 */
final readonly class Readiness {

	/**
	 * @param array{
	 *     permalinks: string,
	 *     database_reachable: bool,
	 *     schema_current: bool,
	 *     has_zip: bool,
	 *     has_imagick: bool,
	 *     storage_writable: bool,
	 *     has_landing_page: bool,
	 *     is_https: bool
	 * } $facts
	 * @return list<Finding>
	 */
	public static function inspect( array $facts ): array {
		return self::mostUrgentFirst(
			array(
				self::permalinks( (string) $facts['permalinks'] ),
				self::database( (bool) ( $facts['database_reachable'] ?? true ), (bool) $facts['schema_current'] ),
				self::storage( (bool) $facts['storage_writable'] ),
				self::zip( (bool) $facts['has_zip'] ),
				self::landingPage( (bool) $facts['has_landing_page'] ),
				self::imagick( (bool) $facts['has_imagick'] ),
				self::https( (bool) $facts['is_https'] ),
			)
		);
	}

	/**
	 * Blokady na górę, potem ostrzeżenia, na końcu to, co działa.
	 *
	 * Administrator ma zobaczyć, co go zatrzymuje, bez czytania całej listy.
	 * Kolejność wewnątrz grupy zostaje ta z definicji — `usort` w PHP 8 jest
	 * stabilny, więc „odnośniki" pozostają przed „bazą".
	 *
	 * @param list<Finding> $findings
	 * @return list<Finding>
	 */
	private static function mostUrgentFirst( array $findings ): array {
		usort(
			$findings,
			static function ( Finding $a, Finding $b ): int {
				$rank = static fn( Finding $f ): int => match ( $f->severity ) {
					Severity::Blocking => 0,
					Severity::Warning  => 1,
					Severity::Ok       => 2,
				};

				return $rank( $a ) <=> $rank( $b );
			}
		);

		return $findings;
	}

	/**
	 * Czy instalacja nadaje się do użycia.
	 *
	 * @param list<Finding> $findings
	 */
	public static function isUsable( array $findings ): bool {
		foreach ( $findings as $finding ) {
			if ( $finding->blocks() ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param list<Finding> $findings
	 * @return list<Finding>
	 */
	public static function problems( array $findings ): array {
		return array_values(
			array_filter( $findings, static fn( Finding $f ): bool => Severity::Ok !== $f->severity )
		);
	}

	/**
	 * Pułapka numer jeden.
	 *
	 * Przy odnośnikach „zwykłych" (`?p=123`) WordPress nie przetwarza reguł
	 * przepisywania, więc `/app`, `/logowanie`, `/g/{link}` i wszystko inne
	 * zwraca 404 — bez żadnego komunikatu. To jest pierwsza rzecz, która
	 * psuje się przy instalacji, i ostatnia, na którą ktokolwiek wpada.
	 */
	private static function permalinks( string $structure ): Finding {
		if ( '' !== trim( $structure ) ) {
			return Finding::ok( 'permalinks', 'Bezpośrednie odnośniki' );
		}

		return Finding::blocking(
			'permalinks',
			'Bezpośrednie odnośniki są ustawione na „zwykłe”',
			'Cała platforma zwraca 404: panel fotografa, logowanie, galeria klientki i pobieranie plików. Nic z tego nie zadziała.',
			'Ustawienia → Bezpośrednie odnośniki → wybierz cokolwiek poza „Zwykłe” (np. „Nazwa wpisu”) i zapisz.'
		);
	}

	/**
	 * Baza: najpierw czy w ogóle odpowiada, dopiero potem czy jest aktualna.
	 *
	 * Rozróżnienie jest istotne, bo instrukcja naprawy jest zupełnie inna.
	 * „Wyłącz i włącz wtyczkę" przy zerwanym połączeniu z bazą to rada,
	 * która wysyła administratora w złą stronę na godzinę.
	 */
	private static function database( bool $reachable, bool $current ): Finding {
		if ( ! $reachable ) {
			return Finding::blocking(
				'schema',
				'Brak połączenia z bazą danych',
				'Nie da się odczytać ani zapisać niczego — ani galerii, ani wyborów, ani zamówień.',
				'Sprawdź dane dostępowe w wp-config.php i czy serwer bazy działa. Dopóki to nie zadziała, reszty przeglądu nie da się wykonać.'
			);
		}

		if ( $current ) {
			return Finding::ok( 'schema', 'Schemat bazy danych' );
		}

		return Finding::blocking(
			'schema',
			'Baza danych czeka na migrację',
			'Zapis galerii, wyborów i zamówień może się nie powieść albo trafić do niepełnej tabeli.',
			'Wejdź w kokpit WordPressa — migracja wykona się sama. Jeśli to nie pomoże, wyłącz i włącz wtyczkę.'
		);
	}

	private static function storage( bool $writable ): Finding {
		if ( $writable ) {
			return Finding::ok( 'storage', 'Magazyn plików' );
		}

		return Finding::blocking(
			'storage',
			'Katalog magazynu nie jest zapisywalny',
			'Wysyłanie zdjęć nie powiedzie się. Zdjęcia są przechowywane POZA katalogiem uploads, żeby nie leżały pod publicznym adresem.',
			'Nadaj prawo zapisu do katalogu magazynu użytkownikowi, na którym działa PHP (zwykle www-data).'
		);
	}

	private static function zip( bool $available ): Finding {
		if ( $available ) {
			return Finding::ok( 'zip', 'Rozszerzenie PHP „zip”' );
		}

		return Finding::blocking(
			'zip',
			'Brak rozszerzenia PHP „zip”',
			'Nie da się przygotować paczki ze zdjęciami do pobrania — czyli odpada cały etap dostawy plików.',
			'Poproś hosting o włączenie rozszerzenia „zip”. Na własnym VPS-ie: apt install php-zip i restart PHP-FPM.'
		);
	}

	/**
	 * Strona główna nie powstaje sama.
	 *
	 * Wtyczka rejestruje trasy i bloki, ale nie tworzy treści — i to jest
	 * poprawne (nie chcemy zakładać stron za administratora przy każdej
	 * aktywacji). Trzeba mu jednak POWIEDZIEĆ, że ten krok istnieje,
	 * bo inaczej wgra wtyczkę, wejdzie na swoją domenę i zobaczy stary motyw.
	 */
	private static function landingPage( bool $exists ): Finding {
		if ( $exists ) {
			return Finding::ok( 'landing', 'Strona główna' );
		}

		return Finding::warning(
			'landing',
			'Nie ma jeszcze strony głównej platformy',
			'Pod adresem domeny widać to, co było wcześniej. Odwiedzający nie ma jak trafić do rejestracji.',
			'Kliknij „Utwórz stronę główną” poniżej — założymy ją z gotowego układu i ustawimy jako stronę startową.'
		);
	}

	private static function imagick( bool $available ): Finding {
		if ( $available ) {
			return Finding::ok( 'imagick', 'Imagick' );
		}

		return Finding::warning(
			'imagick',
			'Brak rozszerzenia Imagick',
			'Zdjęcia będą przetwarzane przez GD: wolniej i z gorszą jakością podglądów.',
			'Poproś hosting o włączenie Imagicka. Platforma działa bez niego, ale nie tak, jak powinna.'
		);
	}

	/**
	 * Bez HTTPS ciasteczka sesji i tokeny lecą otwartym tekstem.
	 */
	private static function https( bool $secure ): Finding {
		if ( $secure ) {
			return Finding::ok( 'https', 'Połączenie szyfrowane' );
		}

		return Finding::warning(
			'https',
			'Witryna nie działa po HTTPS',
			'Linki do prywatnych galerii i tokeny pobrania podróżują otwartym tekstem. Schowek w przeglądarce też nie zadziała bez HTTPS.',
			'Włącz certyfikat (Let’s Encrypt jest darmowy) i ustaw adres witryny na https://.'
		);
	}
}
