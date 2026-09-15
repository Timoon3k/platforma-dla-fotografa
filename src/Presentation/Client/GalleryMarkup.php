<?php
declare( strict_types=1 );

namespace Kadr\Presentation\Client;

use Kadr\Presentation\Support\Plural;

defined( 'ABSPATH' ) || exit;

/**
 * Markup galerii klienta.
 *
 * Pierwszy ekran jest renderowany po stronie serwera i pierwsze kadry są
 * W DOKUMENCIE, a nie za żądaniem do API. To jest cała różnica między
 * LCP poniżej 2,5 s a galerią, która na 4G przez dwie sekundy jest pusta
 * (docs/PERFORMANCE.md §3).
 *
 * Klasa nie dotyka bazy ani stanu WordPressa — bierze gotowe dane i zwraca
 * łańcuch. Dzięki temu ten sam markup renderuje harness podglądu i nie ma
 * dwóch wersji galerii, które się rozjadą.
 */
final class GalleryMarkup {

	/** Ile kadrów wchodzi do dokumentu. Reszta dochodzi przy przewijaniu. */
	public const EAGER = 24;

	/**
	 * Ile pierwszych kadrów ładuje się bez odkładania.
	 *
	 * `loading="lazy"` na zdjęciu widocznym od razu opóźnia LCP — przeglądarka
	 * odkłada je do momentu, w którym i tak jest w oknie (CLAUDE.md §6).
	 */
	private const PRIORITY = 4;

	/**
	 * @param array{title: string, intro: string, theme: string, count: int, cover: ?array<string, mixed>} $gallery
	 * @param list<array<string, mixed>> $photos
	 * @param array{name: string, logo: ?string, footer: string} $studio
	 */
	/**
	 * @param array<string, mixed>|null $selection Stan wyboru albo `null`,
	 *                                             gdy galeria nie jest
	 *                                             galerią proofingową.
	 */
	/**
	 * Pierwszy ekran galerii.
	 *
	 * WOŁAJ PRZEZ ARGUMENTY NAZWANE. Przy siedmiu parametrach, z czego trzy
	 * to `bool`, pomyłka w kolejności nie jest błędem składni — jest galerią,
	 * która po cichu pozwala pobierać pliki komuś, kto nie powinien.
	 * To ta sama pułapka, co wyszukiwanie tabel po pozycji w tablicy (ADR-029
	 * w opisie sesji 10): sygnatura, którą da się źle wywołać, kiedyś zostanie
	 * źle wywołana.
	 */
	public function page(
		array $gallery,
		array $photos,
		array $studio,
		bool $hasMore,
		bool $allowDownload = false,
		?array $selection = null,
		bool $archiveReady = false,
		?array $journey = null
	): string {
		$states = $selection['states'] ?? array();

		return sprintf(
			'<a class="kadr-g-skip" href="#zdjecia">%s</a>
%s
<main class="kadr-g-grid%s" id="zdjecia" data-has-more="%s" data-download="%s"%s>%s</main>
%s
%s
%s',
			esc_html__( 'Przejdź do zdjęć', 'kadr' ),
			$this->cover( $gallery, $studio ) . $this->journey( $journey ),
			null === $selection ? '' : ' kadr-g-grid--choosing',
			$hasMore ? 'true' : 'false',
			$allowDownload ? 'true' : 'false',
			null === $selection ? '' : ' data-selection="1"',
			$this->items( $photos, 0, $states, null !== $selection ),
			$hasMore ? $this->more() : '',
			null === $selection ? $this->delivery( $archiveReady, $allowDownload ) : $this->counter( $selection ),
			$this->footer( $studio )
		);
	}

	/**
	 * Oś procesu oczami klientki.
	 *
	 * ODPOWIADA NA PYTANIE „KIEDY BĘDĄ ZDJĘCIA?", ZANIM ZDĄŻY JE ZADAĆ.
	 * Fotograf dostaje to pytanie kilka razy przy każdej sesji i za każdym
	 * razem odpowiada ręcznie — to jedno z dwudziestu przerwań, z których
	 * składają się 2–4 godziny administracji przy jednej sesji.
	 *
	 * Bez JavaScriptu i bez ruchu: pasek, który się animuje, odciąga wzrok
	 * od zdjęć, a po to właśnie klientka tu przyszła. Kropki mówią, gdzie
	 * jesteśmy; zdanie mówi, co dalej.
	 *
	 * @param array{steps: list<array{label: string, state: string}>, current: string, next: string}|null $journey
	 */
	private function journey( ?array $journey ): string {
		if ( null === $journey || array() === $journey['steps'] ) {
			return '';
		}

		$dots = '';

		foreach ( $journey['steps'] as $step ) {
			$dots .= sprintf(
				'<li class="kadr-g-journey__step kadr-g-journey__step--%s">%s<span>%s</span></li>',
				esc_attr( (string) $step['state'] ),
				// Stan niesie nie tylko kolor: „zrobione" ma znak, a etap
				// bieżący jest opisany słowem dla czytnika ekranu.
				'done' === $step['state'] ? '<span aria-hidden="true">✓</span>' : '<span aria-hidden="true">•</span>',
				esc_html( (string) $step['label'] )
			);
		}

		return sprintf(
			'<aside class="kadr-g-journey" aria-label="%s">
	<div class="kadr-g-journey__inner">
		<p class="kadr-g-journey__now"><strong>%s</strong> %s</p>
		<ol class="kadr-g-journey__steps">%s</ol>
	</div>
</aside>',
			esc_attr__( 'Na jakim etapie jest Twoja sesja', 'kadr' ),
			esc_html( (string) $journey['current'] ),
			esc_html( (string) $journey['next'] ),
			$dots
		);
	}

	/**
	 * Print Room — co klientka może zamówić z tego kadru.
	 *
	 * ETAP ④ Z CLAUDE.md §1: dziś odbitek nie sprzedaje się wcale. Klientka
	 * dostaje pliki i współpraca się kończy, choć połowa z nich chętnie
	 * powiesiłaby coś na ścianie — po prostu nikt nie zaproponował tego
	 * w momencie, w którym patrzy na swoje zdjęcia.
	 *
	 * Zdjęcie jest bohaterem, rama jest ramą: podgląd zajmuje górę ekranu,
	 * a wybór formatu jest listą pod spodem. Nie trzy równe karty —
	 * klientka porównuje formaty w kolumnie, czytając cenę pod ceną.
	 *
	 * Cała arytmetyka przyszła z serwera i siedzi w atrybutach `data-`.
	 * Skrypt przestawia dwie zmienne CSS i nic więcej: ramka podglądu ma
	 * pokazywać, CO ZNIKNIE, a nie animować się dla efektu.
	 *
	 * @param array<string, mixed> $photo
	 * @param list<array<string, mixed>> $products
	 */
	public function printRoom( array $photo, array $products, string $imageUrl ): string {
		if ( array() === $products ) {
			return sprintf(
				'<div class="kadr-g-print__empty"><p>%s</p></div>',
				esc_html__( 'Fotograf nie oferuje jeszcze odbitek z tej sesji.', 'kadr' )
			);
		}

		$first   = $products[0]['variants'][0] ?? null;
		$initial = $first['crop'] ?? null;

		$sections = '';

		foreach ( $products as $product ) {
			$sections .= $this->printProduct( $product );
		}

		return sprintf(
			'<div class="kadr-g-print" data-print>
	<figure class="kadr-g-print__preview">
		<div class="kadr-g-crop" style="--kept-w:%s;--kept-h:%s;--ratio:%s">
			<img src="%s" alt="" width="%d" height="%d" />
			<span class="kadr-g-crop__frame" aria-hidden="true"></span>
		</div>
		<figcaption class="kadr-g-print__caption" id="kadr-print-note" role="status" aria-live="polite">%s</figcaption>
	</figure>
	%s
</div>',
			esc_attr( (string) ( $initial['kept_width'] ?? 1 ) ),
			esc_attr( (string) ( $initial['kept_height'] ?? 1 ) ),
			esc_attr( number_format( max( 1, (int) $photo['width'] ) / max( 1, (int) $photo['height'] ), 4, '.', '' ) ),
			esc_url( $imageUrl ),
			(int) $photo['width'],
			(int) $photo['height'],
			esc_html( $this->printNote( $initial ) ),
			$sections
		);
	}

	/**
	 * @param array<string, mixed> $product
	 */
	private function printProduct( array $product ): string {
		$rows = '';

		foreach ( $product['variants'] as $index => $variant ) {
			$rows .= $this->printVariant( $variant, 0 === $index );
		}

		return sprintf(
			'<section class="kadr-g-print__group">
	<h3 class="kadr-g-print__title">%s</h3>
	%s
	<ul class="kadr-g-print__list">%s</ul>
</section>',
			esc_html( (string) $product['name'] ),
			'' === (string) $product['description']
				? ''
				: sprintf( '<p class="kadr-g-print__lead">%s</p>', esc_html( (string) $product['description'] ) ),
			$rows
		);
	}

	/**
	 * @param array<string, mixed> $variant
	 */
	private function printVariant( array $variant, bool $active ): string {
		$crop = $variant['crop'];

		// Wariant, którego nie da się wydrukować w akceptowalnej jakości,
		// zostaje na liście — ale wyłączony i z powodem. Ciche ukrycie
		// kazałoby klientce szukać formatu, który „gdzieś był".
		$blocked = null !== $crop && false === $crop['printable'];

		return sprintf(
			'<li class="kadr-g-print__row">
	<button
		type="button"
		class="kadr-g-print__option"
		aria-pressed="%s"
		%s
		data-kept-w="%s"
		data-kept-h="%s"
		data-note="%s"
	>
		<span class="kadr-g-print__label">%s</span>
		%s
		<span class="kadr-g-print__price">%s</span>
	</button>
	%s
</li>',
			$active ? 'true' : 'false',
			$blocked ? 'disabled' : '',
			esc_attr( (string) ( $crop['kept_width'] ?? 1 ) ),
			esc_attr( (string) ( $crop['kept_height'] ?? 1 ) ),
			esc_attr( $this->printNote( $crop ) ),
			esc_html( (string) $variant['label'] ),
			null === $variant['paper']
				? ''
				: sprintf( '<span class="kadr-g-print__paper">%s</span>', esc_html( (string) $variant['paper'] ) ),
			esc_html( $this->money( (int) $variant['price'] ) ),
			$this->printFlag( $crop )
		);
	}

	/**
	 * Ostrzeżenie pod pozycją — pojawia się TYLKO wtedy, gdy coś znaczy.
	 *
	 * @param array<string, mixed>|null $crop
	 */
	private function printFlag( ?array $crop ): string {
		if ( null === $crop ) {
			return '';
		}

		if ( false === $crop['printable'] ) {
			return sprintf(
				'<p class="kadr-g-print__flag kadr-g-print__flag--blocked">%s</p>',
				esc_html( (string) $crop['quality_text'] )
			);
		}

		if ( true === $crop['warn'] ) {
			return sprintf(
				'<p class="kadr-g-print__flag">%s</p>',
				esc_html(
					sprintf(
						/* translators: %d: procent powierzchni zdjęcia, który zniknie */
						__( 'Zniknie około %d%% zdjęcia.', 'kadr' ),
						(int) $crop['lost_percent']
					)
				)
			);
		}

		return '';
	}

	/**
	 * Zdanie pod podglądem — mówi, co się dzieje z kadrem i czy plik
	 * wystarczy. Dwa najczęstsze powody reklamacji, oba wyprzedzone.
	 *
	 * @param array<string, mixed>|null $crop
	 */
	private function printNote( ?array $crop ): string {
		if ( null === $crop ) {
			return __( 'Ten produkt nie kadruje zdjęcia.', 'kadr' );
		}

		$note = (string) $crop['trim_text'];

		if ( 'good' !== (string) $crop['quality'] ) {
			$note .= ' ' . (string) $crop['quality_text'];
		}

		return $note;
	}

	/**
	 * Pobranie plików.
	 *
	 * DWIE DROGI, BO KLIENTKA MA DWA URZĄDZENIA I RÓŻNE ZWYCZAJE.
	 *
	 * Na telefonie nie otworzy ZIP-a — to nie jest przypuszczenie, tylko
	 * obserwacja z pracy fotografów (skill photography-workflow §3). Ekran
	 * mówi więc wprost, że paczka jest do komputera, a na telefonie zdjęcia
	 * zapisuje się pojedynczo, przytrzymując kadr. Bez tego zdania połowa
	 * klientek pobiera paczkę na telefon i pisze, że „nie działa".
	 */
	private function delivery( bool $archiveReady, bool $allowDownload ): string {
		if ( ! $archiveReady ) {
			return '';
		}

		return sprintf(
			'<aside class="kadr-g-delivery">
	<div class="kadr-g-delivery__inner">
		<p class="kadr-g-delivery__title">%s</p>
		<a class="kadr-g-btn kadr-g-btn--solid" href="pobierz" download>%s</a>
		<p class="kadr-g-delivery__hint">%s</p>
	</div>
</aside>',
			esc_html__( 'Twoje zdjęcia są gotowe', 'kadr' ),
			esc_html__( 'Pobierz wszystkie', 'kadr' ),
			$allowDownload
				? esc_html__( 'Paczka jest do pobrania na komputerze. Na telefonie zapisz zdjęcie, przytrzymując je palcem.', 'kadr' )
				: esc_html__( 'Paczkę najlepiej pobrać na komputerze — telefon nie otworzy jej sam.', 'kadr' )
		);
	}

	/**
	 * Licznik pakietu — element, dla którego istnieje cały ten ekran.
	 *
	 * Jest widoczny PRZEZ CAŁY CZAS i liczy się przy każdej zmianie. Klientka
	 * ma wiedzieć, ile kosztuje dwudzieste pierwsze zdjęcie, ZANIM je kliknie:
	 * wtedy dopłata jest jej decyzją, a nie niespodzianką w wiadomości
	 * od fotografa. Dziś, bez tego, fotograf zwykle dorzuca gratis, bo prosić
	 * o dopłatę jest niezręcznie — i tu wyparowuje przychód.
	 *
	 * @param array<string, mixed> $selection
	 */
	private function counter( array $selection ): string {
		$tally = $selection['tally'];
		$sent  = 'submitted' === (string) $selection['status'];

		$lines = sprintf(
			'<span class="kadr-g-count__main">%s</span>',
			esc_html( Plural::chosen( (int) $tally['selected'] ) )
		);

		if ( null !== $tally['package_limit'] ) {
			$lines .= sprintf(
				'<span class="kadr-g-count__detail">%s</span>',
				esc_html( $this->packageLine( $tally ) )
			);
		}

		return sprintf(
			'<aside class="kadr-g-count%s" id="kadr-g-count" role="status" aria-live="polite" data-status="%s">
	<div class="kadr-g-count__inner">
		<p class="kadr-g-count__text">%s</p>
		%s
	</div>
</aside>',
			$sent ? ' kadr-g-count--sent' : '',
			esc_attr( (string) $selection['status'] ),
			$lines,
			$sent
				? sprintf( '<p class="kadr-g-count__sent">%s</p>', esc_html__( 'Wybór wysłany do fotografa', 'kadr' ) )
				: sprintf(
					'<button class="kadr-g-btn kadr-g-btn--solid" type="button" id="kadr-g-submit">%s</button>',
					esc_html__( 'Wyślij wybór', 'kadr' )
				)
		);
	}

	/**
	 * Druga linia licznika: rozbicie na pakiet i dopłatę.
	 *
	 * @param array<string, mixed> $tally
	 */
	private function packageLine( array $tally ): string {
		if ( (int) $tally['extra'] < 1 ) {
			$remaining = (int) $tally['remaining'];

			return 0 === $remaining
				? sprintf(
					/* translators: %s: liczba zdjęć w pakiecie */
					__( 'Pakiet obejmuje %s — kolejne będą dodatkowo płatne', 'kadr' ),
					$this->photoCount( (int) $tally['package_limit'] )
				)
				: sprintf(
					/* translators: 1: liczba zdjęć w pakiecie, 2: ile jeszcze zostało */
					__( 'Pakiet obejmuje %1$s · zostało %2$s', 'kadr' ),
					$this->photoCount( (int) $tally['package_limit'] ),
					$this->photoCount( $remaining )
				);
		}

		return sprintf(
			/* translators: 1: liczba w pakiecie, 2: liczba dodatkowych, 3: cena za sztukę, 4: kwota dopłaty */
			__( '%1$s w pakiecie · %2$s dodatkowo × %3$s = %4$s', 'kadr' ),
			$this->photoCount( (int) $tally['included'] ),
			$this->photoCount( (int) $tally['extra'] ),
			$this->money( (int) $tally['unit_price'] ),
			$this->money( (int) $tally['total'] )
		);
	}

	private function photoCount( int $count ): string {
		return Plural::photos( $count );
	}

	/**
	 * Kwota z groszy. Nigdy nie liczymy pieniędzy na liczbach
	 * zmiennoprzecinkowych — dzielimy dopiero przy wyświetleniu.
	 */
	private function money( int $minor ): string {
		return sprintf(
			'%s zł',
			number_format_i18n( $minor / 100, 0 === $minor % 100 ? 0 : 2 )
		);
	}

	/**
	 * Ekran PIN-u.
	 *
	 * Nie pokazuje ani jednego kadru — nawet rozmytego. Podgląd „na zachętę"
	 * przed podaniem PIN-u przeczy temu, po co ten PIN istnieje.
	 *
	 * @param array{name: string, logo: ?string, footer: string} $studio
	 */
	public function gate( array $studio, string $error = '' ): string {
		return sprintf(
			'<main class="kadr-g-gate" id="tresc">
	%s
	<h1 class="kadr-g-gate__title">%s</h1>
	<p class="kadr-g-gate__text">%s</p>
	%s
	<form class="kadr-g-gate__form" method="post">
		<label class="kadr-sr-only" for="kadr-g-pin">%s</label>
		<input class="kadr-g-pin" id="kadr-g-pin" name="pin" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="4" autocomplete="off" autofocus required>
		<button class="kadr-g-btn kadr-g-btn--solid" type="submit">%s</button>
	</form>
</main>',
			$this->studioMark( $studio ),
			esc_html__( 'Ta galeria jest chroniona', 'kadr' ),
			esc_html__( 'Podaj czterocyfrowy kod, który dostałaś od fotografa razem z linkiem.', 'kadr' ),
			'' === $error ? '' : sprintf( '<p class="kadr-g-gate__error" role="alert">%s</p>', esc_html( $error ) ),
			esc_html__( 'Kod dostępu', 'kadr' ),
			esc_html__( 'Otwórz galerię', 'kadr' )
		);
	}

	/**
	 * Komunikat o nieczynnym linku.
	 *
	 * Jeden komunikat na wszystkie powody — zły token, wygaśnięcie,
	 * unieważnienie. Rozróżnianie ich mówiłoby zgadującemu, że trafił.
	 */
	public function unavailable( ?string $title = null, ?string $text = null ): string {
		return sprintf(
			'<main class="kadr-g-gate" id="tresc">
	<h1 class="kadr-g-gate__title">%s</h1>
	<p class="kadr-g-gate__text">%s</p>
</main>',
			esc_html( $title ?? __( 'Ten link już nie działa', 'kadr' ) ),
			esc_html( $text ?? __( 'Galeria mogła wygasnąć albo zostać zamknięta. Napisz do fotografa — przyśle nowy link.', 'kadr' ) )
		);
	}

	/**
	 * @param array{title: string, intro: string, theme: string, count: int, cover: ?array<string, mixed>} $gallery
	 * @param array{name: string, logo: ?string, footer: string} $studio
	 */
	private function cover( array $gallery, array $studio ): string {
		$cover = $gallery['cover'];

		$meta = Plural::photos( (int) $gallery['count'] );

		$inner = sprintf(
			'<div class="kadr-g-cover__inner">%s<h1 class="kadr-g-title">%s</h1>%s<p class="kadr-g-meta">%s</p></div>',
			$this->studioMark( $studio ),
			esc_html( $gallery['title'] ),
			'' === $gallery['intro']
				? ''
				: sprintf( '<p class="kadr-g-intro">%s</p>', esc_html( $gallery['intro'] ) ),
			esc_html( $meta )
		);

		if ( null === $cover ) {
			// Bez okładki nagłówek nie udaje zdjęcia — jest nagłówkiem.
			return sprintf( '<header class="kadr-g-cover kadr-g-cover--plain">%s</header>', $inner );
		}

		return sprintf(
			'<header class="kadr-g-cover">
	<img class="kadr-g-cover__image" src="%s" alt="" width="%d" height="%d" fetchpriority="high" decoding="async">
	<div class="kadr-g-cover__scrim"></div>
	%s
</header>',
			esc_url( (string) $cover['src'] ),
			(int) $cover['width'],
			(int) $cover['height'],
			$inner
		);
	}

	/**
	 * Same kadry, bez powłoki — odpowiedź na doczytanie kolejnej strony.
	 *
	 * Fragment HTML-a zamiast JSON-a, bo klient i tak zamieniłby ten JSON
	 * na dokładnie ten sam markup. Mniej kodu w przeglądarce i jedno źródło
	 * prawdy dla wyglądu kadru.
	 *
	 * @param list<array<string, mixed>> $photos
	 */
	public function items( array $photos, int $startIndex = 0, array $states = array(), bool $choosing = true ): string {
		$markup = '';

		foreach ( array_values( $photos ) as $index => $photo ) {
			$markup .= $this->item(
				$photo,
				$startIndex + $index,
				(string) ( $states[ (string) $photo['id'] ] ?? '' ),
				$choosing
			);
		}

		return $markup;
	}

	/**
	 * @param array<string, mixed> $photo
	 */
	private function item( array $photo, int $index, string $state = '', bool $choosing = true ): string {
		$width  = max( 1, (int) $photo['width'] );
		$height = max( 1, (int) $photo['height'] );

		// Proporcje kadru decydują o jego szerokości w rzędzie. Serwer je zna,
		// więc układ jest gotowy w HTML-u i nic nie przeskakuje po wczytaniu
		// zdjęcia (CLAUDE.md §6, zero CLS).
		$itemStyle  = sprintf( '--ratio:%s', number_format( $width / $height, 4, '.', '' ) );
		$frameStyle = '';

		if ( '' !== (string) ( $photo['lqip'] ?? '' ) ) {
			$frameStyle = sprintf( 'background-image:url(%s)', esc_url( (string) $photo['lqip'] ) );
		}

		$priority = $index < self::PRIORITY;

		// Adresy wariantu pełnego i pobrania są WYPISANE, a nie wyliczane
		// w przeglądarce z adresu miniatury. Przepisywanie adresu wyrażeniem
		// regularnym działa dopóty, dopóki ktoś nie zmieni ścieżki — a wtedy
		// psuje się po cichu.
		return sprintf(
			'<figure class="kadr-g-item" style="%s">
	<button class="kadr-g-item__button" type="button" data-index="%d" data-full="%s" data-file="%s" aria-label="%s" data-state="%s">
		<span class="kadr-g-item__frame" style="%s">
			<img class="kadr-g-item__image" src="%s" alt="%s" width="%d" height="%d" loading="%s" decoding="async"%s>
		</span>
	</button>%s
</figure>',
			esc_attr( $itemStyle ),
			$index,
			esc_url( (string) ( $photo['full'] ?? $photo['src'] ) ),
			esc_url( (string) ( $photo['download'] ?? '' ) ),
			esc_attr(
				sprintf(
					/* translators: %d: numer zdjęcia w galerii */
					__( 'Otwórz zdjęcie %d', 'kadr' ),
					$index + 1
				)
			),
			esc_attr( $state ),
			esc_attr( $frameStyle ),
			esc_url( (string) $photo['src'] ),
			esc_attr( (string) ( $photo['alt'] ?? '' ) ),
			$width,
			$height,
			$priority ? 'eager' : 'lazy',
			$priority ? ' fetchpriority="high"' : '',
			$choosing ? $this->choices( (string) $photo['id'], $state ) : ''
		);
	}

	/**
	 * Przyciski wyboru przy kadrze.
	 *
	 * Rysujemy je WYŁĄCZNIE wtedy, gdy galeria jest w trybie wyboru. Skrypt
	 * podpina je tylko przy `data-selection="1"`, więc w zwykłej galerii
	 * klientka dostawała dwa przyciski, które nic nie robią po kliknięciu.
	 * Martwa kontrolka uczy, że interfejsowi nie warto ufać — a to jest
	 * ekran, na którym za chwilę prosimy o pieniądze.
	 *
	 * Serduszko i „wybieram" to DWIE RÓŻNE rzeczy. Klientka najpierw przechodzi
	 * galerię i serduszkuje kadry, które jej się podobają, a dopiero potem
	 * zawęża je do finalnego wyboru. Sklejenie tych stanów zmuszałoby ją do
	 * decyzji zakupowej przy pierwszym przejrzeniu — czyli do dokładnie tego
	 * tarcia, które ten produkt ma usuwać.
	 *
	 * Pola dotyku mają 44 px, bo galeria jest oglądana jedną ręką na telefonie.
	 */
	private function choices( string $assetId, string $state ): string {
		$buttons = '';

		$actions = array(
			'favorite' => array( '♥', __( 'Ulubione', 'kadr' ) ),
			'selected' => array( '✓', __( 'Wybieram to zdjęcie', 'kadr' ) ),
		);

		foreach ( $actions as $value => [$glyph, $label] ) {
			$active = $state === $value;

			$buttons .= sprintf(
				'<button class="kadr-g-choice kadr-g-choice--%1$s" type="button" data-choice="%1$s" data-asset="%2$s" aria-pressed="%3$s" aria-label="%4$s"><span aria-hidden="true">%5$s</span></button>',
				esc_attr( $value ),
				esc_attr( $assetId ),
				$active ? 'true' : 'false',
				esc_attr( $label ),
				$glyph
			);
		}

		return sprintf( '<div class="kadr-g-choices">%s</div>', $buttons );
	}

	private function more(): string {
		return sprintf(
			'<div class="kadr-g-more"><button class="kadr-g-btn" type="button" id="kadr-g-more">%s</button></div>',
			esc_html__( 'Pokaż więcej zdjęć', 'kadr' )
		);
	}

	/**
	 * @param array{name: string, logo: ?string, footer: string} $studio
	 */
	private function studioMark( array $studio ): string {
		$logo = null === $studio['logo'] || '' === $studio['logo']
			? ''
			: sprintf(
				'<img class="kadr-g-studio__logo" src="%s" alt="%s">',
				esc_url( $studio['logo'] ),
				esc_attr( $studio['name'] )
			);

		return sprintf(
			'<p class="kadr-g-studio">%s<span>%s</span></p>',
			$logo,
			esc_html( $studio['name'] )
		);
	}

	/**
	 * @param array{name: string, logo: ?string, footer: string} $studio
	 */
	private function footer( array $studio ): string {
		return sprintf(
			'<footer class="kadr-g-footer">%s</footer>',
			'' === $studio['footer']
				? esc_html( $studio['name'] )
				: esc_html( $studio['footer'] )
		);
	}
}
