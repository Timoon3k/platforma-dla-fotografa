<?php
declare( strict_types=1 );

namespace Kadr\Presentation\Admin;

use Kadr\Domain\Setup\Finding;
use Kadr\Domain\Setup\Readiness;
use Kadr\Domain\Setup\Severity;
use Kadr\Infrastructure\WordPress\Activation;
use Kadr\Infrastructure\WordPress\Container;
use Kadr\Infrastructure\WordPress\Patterns;

defined( 'ABSPATH' ) || exit;

/**
 * Pierwsze uruchomienie — jedyny ekran Kadru w kokpicie WordPressa.
 *
 * WP Admin jest wyłącznie dla administratora platformy (CLAUDE.md §4.5).
 * Fotograf nigdy tu nie trafia, klient tym bardziej — dlatego ten ekran
 * korzysta z natywnych stylów kokpitu zamiast systemu Obsidian. Malowanie
 * kokpitu na własne barwy byłoby pracą, której nikt z docelowych użytkowników
 * nie zobaczy, a każda aktualizacja WordPressa by ją psuła.
 *
 * Powód istnienia: po wgraniu wtyczki nie dzieje się nic widocznego. Trasy
 * działają, ale strony głównej nie ma, a przy „zwykłych" odnośnikach cała
 * platforma zwraca 404 bez słowa wyjaśnienia. Ten ekran zamienia „chyba
 * nie działa" w listę kroków.
 */
final class SetupPage {

	private const SLUG   = 'kadr-setup';
	private const ACTION = 'kadr_create_landing';

	/**
	 * @param array<string, mixed>|null $facts Fakty o instalacji. `null`
	 *        oznacza „zbadaj środowisko sam" i tak działa wtyczka. Podanie
	 *        ich z zewnątrz pozwala obejrzeć ten ekran w stanach, których
	 *        nie da się wywołać na żywo — na przykład przy nieosiągalnej
	 *        bazie danych (`tools/preview-setup.php`).
	 */
	public function __construct(
		private readonly ?array $facts = null,
	) {}

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'create_landing_page' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	public function register_menu(): void {
		add_menu_page(
			__( 'Kadr', 'kadr' ),
			__( 'Kadr', 'kadr' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-camera-alt',
			3
		);
	}

	/**
	 * Powiadomienie na innych ekranach kokpitu.
	 *
	 * Pokazujemy je TYLKO wtedy, gdy coś faktycznie blokuje albo brakuje
	 * strony głównej — i nigdy na własnym ekranie, gdzie ta sama informacja
	 * jest już rozpisana. Powiadomienie widoczne zawsze przestaje być czytane.
	 */
	public function notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( null !== $screen && str_contains( (string) $screen->id, self::SLUG ) ) {
			return;
		}

		$problems = Readiness::problems( $this->findings() );

		if ( array() === $problems ) {
			return;
		}

		$blocking = array_filter( $problems, static fn( Finding $f ): bool => $f->blocks() );

		printf(
			'<div class="notice notice-%s"><p><strong>%s</strong> %s</p><p><a class="button button-primary" href="%s">%s</a></p></div>',
			array() === $blocking ? 'warning' : 'error',
			esc_html__( 'Kadr:', 'kadr' ),
			esc_html(
				array() === $blocking
					? __( 'instalacja wymaga jeszcze kilku kroków.', 'kadr' )
					: __( 'platforma nie zadziała, dopóki nie naprawisz poniższego.', 'kadr' )
			),
			esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ),
			esc_html__( 'Zobacz, co zrobić', 'kadr' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Brak uprawnień.', 'kadr' ) );
		}

		$findings = $this->findings();
		$problems = Readiness::problems( $findings );

		echo '<div class="wrap">';
		printf( '<h1>%s</h1>', esc_html__( 'Kadr — pierwsze uruchomienie', 'kadr' ) );

		if ( array() === $problems ) {
			printf(
				'<div class="notice notice-success inline"><p>%s</p></div>',
				esc_html__( 'Instalacja jest kompletna. Platforma działa.', 'kadr' )
			);
		}

		$this->renderFindings( $findings );
		$this->renderActions( $findings );
		$this->renderAddresses();

		echo '</div>';
	}

	/**
	 * @param list<Finding> $findings
	 */
	private function renderFindings( array $findings ): void {
		printf( '<h2>%s</h2>', esc_html__( 'Przegląd instalacji', 'kadr' ) );
		echo '<table class="widefat striped" style="max-width:56rem"><tbody>';

		foreach ( $findings as $finding ) {
			printf(
				'<tr><td style="width:2rem;font-size:1.2em;color:%s">%s</td><td><strong>%s</strong>%s</td></tr>',
				// Kolor jest wzmocnieniem, nie jedynym nośnikiem: znak (✕ ! ✓)
				// niesie tę samą informację, a blokady i tak są na górze listy.
				esc_attr( $this->tone( $finding->severity ) ),
				esc_html( $this->glyph( $finding->severity ) ),
				esc_html( $finding->label ),
				Severity::Ok === $finding->severity
					? ''
					: sprintf(
						'<p style="margin:.35em 0 0">%s</p><p style="margin:.35em 0 0"><em>%s</em></p>',
						esc_html( $finding->consequence ),
						esc_html( $finding->fix )
					)
			);
		}

		echo '</tbody></table>';
	}

	/**
	 * @param list<Finding> $findings
	 */
	private function renderActions( array $findings ): void {
		foreach ( $findings as $finding ) {
			if ( 'landing' !== $finding->id || Severity::Ok === $finding->severity ) {
				continue;
			}

			printf( '<h2>%s</h2>', esc_html__( 'Strona główna', 'kadr' ) );
			printf(
				'<p>%s</p>',
				esc_html__( 'Utworzymy stronę z gotowego układu sprzedażowego i ustawimy ją jako startową. Treść będzie zwykłą stroną WordPressa — możesz ją potem dowolnie edytować.', 'kadr' )
			);

			printf(
				'<form method="post" action="%s">%s<input type="hidden" name="action" value="%s" />'
				. '<button type="submit" class="button button-primary">%s</button></form>',
				esc_url( admin_url( 'admin-post.php' ) ),
				wp_nonce_field( self::ACTION, '_wpnonce', true, false ),
				esc_attr( self::ACTION ),
				esc_html__( 'Utwórz stronę główną', 'kadr' )
			);

			return;
		}
	}

	/**
	 * Adresy platformy.
	 *
	 * To nie są strony WordPressa — wtyczka renderuje je sama. Administrator
	 * nie znajdzie ich w „Stronach" i bez tej listy nie ma jak się dowiedzieć,
	 * że w ogóle istnieją.
	 */
	private function renderAddresses(): void {
		$routes = array(
			'/rejestracja' => __( 'Założenie studia przez fotografa', 'kadr' ),
			'/logowanie'   => __( 'Logowanie fotografa', 'kadr' ),
			'/app/'        => __( 'Panel fotografa — po zalogowaniu', 'kadr' ),
		);

		printf( '<h2>%s</h2>', esc_html__( 'Adresy platformy', 'kadr' ) );
		printf(
			'<p>%s</p>',
			esc_html__( 'Te adresy obsługuje wtyczka. Nie znajdziesz ich w „Stronach” i nie trzeba ich zakładać.', 'kadr' )
		);

		echo '<table class="widefat striped" style="max-width:56rem"><tbody>';

		foreach ( $routes as $path => $label ) {
			printf(
				'<tr><td style="width:14rem"><a href="%s">%s</a></td><td>%s</td></tr>',
				esc_url( home_url( $path ) ),
				esc_html( $path ),
				esc_html( $label )
			);
		}

		printf(
			'<tr><td><code>/g/{link}</code></td><td>%s</td></tr>'
			. '<tr><td><code>/d/{token}</code></td><td>%s</td></tr>',
			esc_html__( 'Galeria klientki — adres wydaje fotograf z panelu', 'kadr' ),
			esc_html__( 'Pobranie paczki — adres wydaje fotograf z panelu', 'kadr' )
		);

		echo '</tbody></table>';
	}

	/**
	 * Utworzenie strony głównej z wzorca.
	 */
	public function create_landing_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Brak uprawnień.', 'kadr' ) );
		}

		check_admin_referer( self::ACTION );

		$id = wp_insert_post(
			array(
				'post_title'   => __( 'Kadr — strona główna', 'kadr' ),
				'post_name'    => 'kadr',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => ( new Patterns() )->landing(),
			),
			true
		);

		if ( is_wp_error( $id ) ) {
			wp_safe_redirect( add_query_arg( 'kadr_setup', 'blad', admin_url( 'admin.php?page=' . self::SLUG ) ) );
			exit;
		}

		// Strona bez ustawienia jej jako startowej nie rozwiązuje problemu —
		// administrator dalej widziałby pod domeną to, co było wcześniej.
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $id );

		wp_safe_redirect( add_query_arg( 'kadr_setup', 'gotowe', admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}

	/**
	 * @return list<Finding>
	 */
	private function findings(): array {
		if ( null !== $this->facts ) {
			return Readiness::inspect( $this->facts );
		}

		/*
		 * Pytanie o schemat dotyka bazy, a ten ekran ma działać WŁAŚNIE
		 * WTEDY, gdy coś jest zepsute. Gdyby wyjątek z bazy przewrócił
		 * przegląd, administrator dostałby białą stronę zamiast informacji,
		 * że nie ma połączenia z bazą — czyli zamiast jedynej rzeczy,
		 * której w tym momencie potrzebuje.
		 */
		$reachable = true;
		$current   = true;

		try {
			$current = ! Activation::needs_migration();
		} catch ( \Throwable ) {
			$reachable = false;
			$current   = false;
		}

		return Readiness::inspect(
			array(
				'permalinks'         => (string) get_option( 'permalink_structure', '' ),
				'database_reachable' => $reachable,
				'schema_current'     => $current,
				'has_zip'            => class_exists( \ZipArchive::class ),
				'has_imagick'        => extension_loaded( 'imagick' ),
				'storage_writable'   => $this->storageWritable(),
				'has_landing_page'   => $this->hasLandingPage(),
				// Liczy się adres WITRYNY, nie kokpitu: klientka otwiera
				// galerię pod adresem publicznym, a kokpit bywa za innym
				// wejściem niż strona.
				'is_https'           => str_starts_with( (string) home_url(), 'https://' ),
			)
		);
	}

	private function storageWritable(): bool {
		$path = Container::instance()->storagePath();

		if ( is_dir( $path ) ) {
			return is_writable( $path );
		}

		// Katalog powstaje przy pierwszym zapisie, więc pytamy o rodzica.
		$parent = dirname( $path );

		return is_dir( $parent ) && is_writable( $parent );
	}

	private function hasLandingPage(): bool {
		if ( 'page' !== get_option( 'show_on_front' ) ) {
			return false;
		}

		$front = (int) get_option( 'page_on_front', 0 );

		if ( $front <= 0 ) {
			return false;
		}

		$content = (string) get_post_field( 'post_content', $front );

		// Strona startowa istnieje, ale czy to NASZA strona? Dowolna strona
		// „O nas" też spełniłaby warunek, a odwiedzający dalej nie miałby
		// jak trafić do rejestracji.
		return str_contains( $content, 'wp:kadr/' );
	}

	/**
	 * Barwy kokpitu WordPressa, nie systemu Obsidian — ten ekran ma wyglądać
	 * jak część kokpitu, w którym stoi.
	 */
	private function tone( Severity $severity ): string {
		return match ( $severity ) {
			Severity::Ok       => '#00a32a',
			Severity::Warning  => '#996800',
			Severity::Blocking => '#d63638',
		};
	}

	private function glyph( Severity $severity ): string {
		return match ( $severity ) {
			Severity::Ok       => '✓',
			Severity::Warning  => '!',
			Severity::Blocking => '✕',
		};
	}
}
