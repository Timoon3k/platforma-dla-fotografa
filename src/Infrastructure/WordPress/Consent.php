<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Zarządzanie zgodami na cookies (docs/LEGAL.md §5).
 *
 * Reguły, których ta implementacja pilnuje:
 * - domyślnie zaznaczone są WYŁĄCZNIE cookies niezbędne,
 * - odmowa jest tak samo łatwa jak zgoda — jeden klik, ta sama waga wizualna,
 * - skrypty wymagające zgody nie wykonują się przed jej udzieleniem;
 *   nie „ładują się i czekają”, tylko nie są w ogóle wstawiane do dokumentu,
 * - zgoda jest wersjonowana, żeby zmiana zakresu wymusiła ponowne pytanie.
 */
final class Consent {

	public const COOKIE  = 'kadr_consent';
	public const VERSION = '1';

	/**
	 * Kategorie zgód. „necessary” jest zawsze aktywna i nie da się jej wyłączyć.
	 *
	 * @return array<string, array{label: string, description: string, locked: bool}>
	 */
	public static function categories(): array {
		return array(
			'necessary'   => array(
				'label'       => __( 'Niezbędne', 'kadr' ),
				'description' => __( 'Utrzymanie sesji, bezpieczeństwo i zapamiętanie Twojego wyboru w tym oknie. Bez nich strona nie działa.', 'kadr' ),
				'locked'      => true,
			),
			'preferences' => array(
				'label'       => __( 'Preferencje', 'kadr' ),
				'description' => __( 'Zapamiętanie ustawień, na przykład wybranego widoku czy języka.', 'kadr' ),
				'locked'      => false,
			),
			'analytics'   => array(
				'label'       => __( 'Analityka', 'kadr' ),
				'description' => __( 'Zbiorcze statystyki odwiedzin, bez profilowania i bez śledzenia Cię na innych stronach.', 'kadr' ),
				'locked'      => false,
			),
			'marketing'   => array(
				'label'       => __( 'Marketing', 'kadr' ),
				'description' => __( 'Mierzenie skuteczności kampanii. Domyślnie wyłączone.', 'kadr' ),
				'locked'      => false,
			),
		);
	}

	public function register_hooks(): void {
		add_action( 'wp_footer', array( $this, 'render' ), 5 );
	}

	/**
	 * Odczytuje udzielone zgody z ciasteczka.
	 *
	 * @return list<string>
	 */
	public static function granted(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- odczyt preferencji, nie akcja zmieniająca stan.
		$raw = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( (string) $_COOKIE[ self::COOKIE ] ) ) : '';

		if ( '' === $raw ) {
			return array( 'necessary' );
		}

		$decoded = json_decode( $raw, true );

		if ( ! is_array( $decoded ) || ( $decoded['v'] ?? '' ) !== self::VERSION ) {
			// Zmiana wersji zgody unieważnia poprzedni wybór — pytamy ponownie.
			return array( 'necessary' );
		}

		$granted = array_values(
			array_filter(
				(array) ( $decoded['c'] ?? array() ),
				static fn( $key ): bool => is_string( $key ) && array_key_exists( $key, self::categories() )
			)
		);

		return array_values( array_unique( array_merge( array( 'necessary' ), $granted ) ) );
	}

	public static function has( string $category ): bool {
		return in_array( $category, self::granted(), true );
	}

	public function render(): void {
		wp_enqueue_script( Assets::HANDLE_CONSENT );
		wp_enqueue_style( 'kadr-components' );

		wp_localize_script(
			Assets::HANDLE_CONSENT,
			'kadrConsentConfig',
			array(
				'cookie'  => self::COOKIE,
				'version' => self::VERSION,
				'days'    => 180,
			)
		);

		$categories = self::categories();
		?>
		<div
			class="kadr kadr-consent"
			id="kadr-consent"
			role="dialog"
			aria-modal="false"
			aria-labelledby="kadr-consent-title"
			aria-describedby="kadr-consent-desc"
			hidden
		>
			<div class="kadr-consent__panel">
				<h2 class="kadr-consent__title" id="kadr-consent-title"><?php esc_html_e( 'Cookies', 'kadr' ); ?></h2>
				<p class="kadr-consent__text" id="kadr-consent-desc">
					<?php esc_html_e( 'Używamy plików cookies. Niezbędne są konieczne, żeby strona działała. Pozostałe włączysz tylko, jeśli chcesz — nic nie uruchamiamy przed Twoją zgodą.', 'kadr' ); ?>
				</p>

				<div class="kadr-consent__options" hidden data-kadr-consent-options>
					<?php foreach ( $categories as $key => $category ) : ?>
						<label class="kadr-consent__option">
							<input
								type="checkbox"
								name="kadr-consent-category"
								value="<?php echo esc_attr( $key ); ?>"
								<?php checked( $category['locked'] ); ?>
								<?php disabled( $category['locked'] ); ?>
							>
							<span>
								<strong><?php echo esc_html( $category['label'] ); ?></strong>
								<span class="kadr-consent__desc"><?php echo esc_html( $category['description'] ); ?></span>
							</span>
						</label>
					<?php endforeach; ?>
				</div>

				<div class="kadr-consent__actions">
					<button type="button" class="kadr-btn kadr-btn--primary" data-kadr-consent="accept">
						<?php esc_html_e( 'Zgadzam się na wszystkie', 'kadr' ); ?>
					</button>
					<button type="button" class="kadr-btn kadr-btn--secondary" data-kadr-consent="reject">
						<?php esc_html_e( 'Tylko niezbędne', 'kadr' ); ?>
					</button>
					<button type="button" class="kadr-btn kadr-btn--ghost" data-kadr-consent="customise">
						<?php esc_html_e( 'Ustaw szczegółowo', 'kadr' ); ?>
					</button>
					<button type="button" class="kadr-btn kadr-btn--primary" data-kadr-consent="save" hidden>
						<?php esc_html_e( 'Zapisz wybór', 'kadr' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}
}
