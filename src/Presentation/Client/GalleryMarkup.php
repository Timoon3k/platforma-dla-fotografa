<?php
declare( strict_types=1 );

namespace Kadr\Presentation\Client;

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
	public function page( array $gallery, array $photos, array $studio, bool $hasMore ): string {
		return sprintf(
			'<a class="kadr-g-skip" href="#zdjecia">%s</a>
%s
<main class="kadr-g-grid" id="zdjecia" data-has-more="%s">%s</main>
%s
%s',
			esc_html__( 'Przejdź do zdjęć', 'kadr' ),
			$this->cover( $gallery, $studio ),
			$hasMore ? 'true' : 'false',
			$this->items( $photos ),
			$hasMore ? $this->more() : '',
			$this->footer( $studio )
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
	public function unavailable(): string {
		return sprintf(
			'<main class="kadr-g-gate" id="tresc">
	<h1 class="kadr-g-gate__title">%s</h1>
	<p class="kadr-g-gate__text">%s</p>
</main>',
			esc_html__( 'Ten link już nie działa', 'kadr' ),
			esc_html__( 'Galeria mogła wygasnąć albo zostać zamknięta. Napisz do fotografa — przyśle nowy link.', 'kadr' )
		);
	}

	/**
	 * @param array{title: string, intro: string, theme: string, count: int, cover: ?array<string, mixed>} $gallery
	 * @param array{name: string, logo: ?string, footer: string} $studio
	 */
	private function cover( array $gallery, array $studio ): string {
		$cover = $gallery['cover'];

		$meta = sprintf(
			/* translators: %s: liczba zdjęć w galerii */
			_n( '%s zdjęcie', '%s zdjęć', $gallery['count'], 'kadr' ),
			number_format_i18n( $gallery['count'] )
		);

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
	public function items( array $photos, int $startIndex = 0 ): string {
		$markup = '';

		foreach ( array_values( $photos ) as $index => $photo ) {
			$markup .= $this->item( $photo, $startIndex + $index );
		}

		return $markup;
	}

	/**
	 * @param array<string, mixed> $photo
	 */
	private function item( array $photo, int $index ): string {
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

		return sprintf(
			'<figure class="kadr-g-item" style="%s">
	<button class="kadr-g-item__button" type="button" data-index="%d" aria-label="%s">
		<span class="kadr-g-item__frame" style="%s">
			<img class="kadr-g-item__image" src="%s" alt="%s" width="%d" height="%d" loading="%s" decoding="async"%s>
		</span>
	</button>
</figure>',
			esc_attr( $itemStyle ),
			$index,
			esc_attr(
				sprintf(
					/* translators: %d: numer zdjęcia w galerii */
					__( 'Otwórz zdjęcie %d', 'kadr' ),
					$index + 1
				)
			),
			esc_attr( $frameStyle ),
			esc_url( (string) $photo['src'] ),
			esc_attr( (string) ( $photo['alt'] ?? '' ) ),
			$width,
			$height,
			$priority ? 'eager' : 'lazy',
			$priority ? ' fetchpriority="high"' : ''
		);
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
