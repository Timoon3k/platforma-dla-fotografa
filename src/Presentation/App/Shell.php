<?php
declare( strict_types=1 );

namespace Kadr\Presentation\App;

use Kadr\Infrastructure\WordPress\Container;

defined( 'ABSPATH' ) || exit;

/**
 * Powłoka panelu fotografa — dokument, który dostaje przeglądarka na `/app`.
 *
 * Panel NIE renderuje się w motywie. Fotograf nigdy nie widzi WordPressa
 * (CLAUDE.md §4.5), więc i dokument jest nasz: własny `<head>`, własny układ,
 * żadnego `get_header()`.
 *
 * Nawigacja jest renderowana po stronie serwera, a nie przez JavaScript.
 * Przy wejściu w panel ma być od razu widoczna rama aplikacji, a nie pusty
 * ekran czekający na moduły. Widoki montują się w `#kadr-app-view`.
 *
 * Metoda `body()` jest celowo oddzielona od `render()` i nie dotyka bazy ani
 * stanu WordPressa poza funkcjami escapowania — dzięki temu ten sam markup
 * renderuje harness podglądu (`tools/preview-app.php`) i nie ma dwóch wersji
 * powłoki, które rozjeżdżają się po pierwszej zmianie.
 */
final class Shell {

	public const MOUNT = 'kadr-app-view';

	public function register_hooks(): void {
		add_action( 'kadr_render_route', array( $this, 'render' ), 10, 2 );
	}

	public function render( string $route, string $param ): void {
		if ( 'app' !== $route ) {
			return;
		}

		// Trasa nie odpowiada żadnemu wpisowi, więc WordPress zdążył ustawić
		// 404. Panel istnieje i ma odpowiedzieć dwusetką.
		status_header( 200 );
		nocache_headers();

		// Pasek WordPressa nie ma się pojawiać nad aplikacją — również
		// administratorowi platformy, który ogląda panel.
		add_filter( 'show_admin_bar', '__return_false' );
		add_filter( 'pre_get_document_title', static fn (): string => __( 'Panel — Kadr', 'kadr' ) );

		$tenant = Container::instance()->tenantForCurrentUser();
		$studio = null === $tenant ? '' : Container::instance()->studioName( $tenant->id() );

		$body = $this->body(
			self::section( $param ),
			$studio,
			wp_logout_url( home_url( '/' ) ),
			home_url( '/app/' )
		);

		?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php
		// Panel nie jest treścią do zaindeksowania — to aplikacja za logowaniem.
		wp_robots_no_robots();
		wp_head();
?>
</head>
<body class="kadr kadr-app-document">
<?php
		echo $body; // phpcs:ignore WordPress.Security.EscapingOutput -- markup zbudowany w body(), każda wartość escapowana u źródła.
		wp_footer();
?>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * Pozycje nawigacji.
	 *
	 * Liczniki przy pozycjach celowo NIE są renderowane po stronie serwera:
	 * wymagałyby kilku zapytań przy każdym wejściu w panel, a i tak
	 * dezaktualizują się po pierwszej akcji. Dokłada je widok z API.
	 *
	 * @return array<string, array{label: string, items: array<string, string>}>
	 */
	public static function navigation(): array {
		return array(
			'work'   => array(
				'label' => __( 'Praca', 'kadr' ),
				'items' => array(
					''          => __( 'Dzisiaj', 'kadr' ),
					'galerie'   => __( 'Galerie', 'kadr' ),
					'wybory'    => __( 'Wybory', 'kadr' ),
					'klienci'   => __( 'Klienci', 'kadr' ),
					'kalendarz' => __( 'Kalendarz', 'kadr' ),
				),
			),
			'sales'  => array(
				'label' => __( 'Sprzedaż', 'kadr' ),
				'items' => array(
					'zamowienia' => __( 'Zamówienia', 'kadr' ),
					'produkty'   => __( 'Produkty', 'kadr' ),
					'analityka'  => __( 'Analityka', 'kadr' ),
				),
			),
			'studio' => array(
				'label' => __( 'Studio', 'kadr' ),
				'items' => array(
					'ustawienia'  => __( 'Ustawienia', 'kadr' ),
					'rozliczenia' => __( 'Rozliczenia', 'kadr' ),
				),
			),
		);
	}

	/**
	 * Bieżąca sekcja, wyłuskana z pierwszego segmentu adresu.
	 *
	 * Wartość spoza nawigacji sprowadzamy do pustej — adres `/app/cokolwiek`
	 * nie może podświetlić niczego przypadkowego ani trafić do atrybutu.
	 */
	public static function section( string $param ): string {
		$first = strtok( trim( $param, '/' ), '/' );
		$first = false === $first ? '' : $first;

		foreach ( self::navigation() as $group ) {
			if ( array_key_exists( $first, $group['items'] ) ) {
				return $first;
			}
		}

		return '';
	}

	/**
	 * Markup powłoki.
	 *
	 * @param string $section   Podświetlona sekcja nawigacji.
	 * @param string $studio    Nazwa studia; pusta, gdy konto nie ma studia.
	 * @param string $logoutUrl Adres wylogowania.
	 * @param string $baseUrl   Bazowy adres panelu.
	 */
	public function body( string $section, string $studio, string $logoutUrl, string $baseUrl ): string {
		$base = rtrim( $baseUrl, '/' ) . '/';

		$navigation = '';

		foreach ( self::navigation() as $group ) {
			$items = '';

			foreach ( $group['items'] as $slug => $label ) {
				$items .= sprintf(
					'<a class="kadr-nav__item" href="%s"%s><span>%s</span></a>',
					esc_url( $base . $slug ),
					$slug === $section ? ' aria-current="page"' : '',
					esc_html( $label )
				);
			}

			$navigation .= sprintf(
				'<div class="kadr-nav__group"><p class="kadr-nav__label">%s</p>%s</div>',
				esc_html( $group['label'] ),
				$items
			);
		}

		$account = '' !== $studio
			? sprintf( '<span class="kadr-app__studio">%s</span>', esc_html( $studio ) )
			: sprintf(
				'<span class="kadr-app__studio kadr-app__studio--missing">%s</span>',
				esc_html__( 'Konto bez studia', 'kadr' )
			);

		return sprintf(
			'<a class="kadr-skip-link" href="#tresc">%1$s</a>
<div class="kadr-app">
	<div class="kadr-app__brand"><a class="kadr-app__brand-link" href="%2$s">Kadr</a></div>

	<header class="kadr-app__top">
		<button type="button" class="kadr-btn kadr-btn--ghost kadr-app__menu" aria-controls="kadr-nav" aria-expanded="false" aria-label="%3$s">☰</button>

		<div class="kadr-search kadr-app__search">
			<span class="kadr-search__icon" aria-hidden="true">⌕</span>
			<input class="kadr-search__input" type="search" id="kadr-app-search" placeholder="%4$s" aria-label="%5$s">
		</div>

		<span class="kadr-app__hint">
			<span class="kadr-kbd">⌘</span><span class="kadr-kbd">K</span>
			<span>%6$s</span>
		</span>

		<div class="kadr-app__account">%7$s<a class="kadr-app__logout" href="%8$s">%9$s</a></div>
	</header>

	<nav class="kadr-app__nav" id="kadr-nav" aria-label="%10$s">%11$s</nav>

	<main class="kadr-app__main" id="tresc">
		<div id="%12$s" data-section="%13$s"></div>
	</main>
</div>',
			esc_html__( 'Przejdź do treści', 'kadr' ),
			esc_url( $base ),
			esc_attr__( 'Otwórz nawigację', 'kadr' ),
			esc_attr__( 'Szukaj klientów, galerii, zamówień…', 'kadr' ),
			esc_attr__( 'Szukaj', 'kadr' ),
			esc_html__( 'paleta poleceń', 'kadr' ),
			$account,
			esc_url( $logoutUrl ),
			esc_html__( 'Wyloguj', 'kadr' ),
			esc_attr__( 'Nawigacja główna', 'kadr' ),
			$navigation,
			esc_attr( self::MOUNT ),
			esc_attr( $section )
		);
	}
}
