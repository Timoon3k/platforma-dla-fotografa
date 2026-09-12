<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Sprawdza wymagania środowiskowe przed uruchomieniem wtyczki.
 *
 * Wtyczka celowo nie startuje na niespełnionym środowisku zamiast ryzykować
 * błąd krytyczny w trakcie działania (CLAUDE.md §3).
 */
final class Requirements {

	/** @var list<string> */
	private array $errors = array();

	public function __construct(
		private readonly string $min_php,
		private readonly string $min_wp,
	) {}

	public function are_met(): bool {
		$this->errors = array();

		if ( version_compare( PHP_VERSION, $this->min_php, '<' ) ) {
			$this->errors[] = sprintf(
				/* translators: 1: wymagana wersja PHP, 2: aktualna wersja PHP */
				__( 'Kadr wymaga PHP w wersji %1$s lub nowszej. Ten serwer używa %2$s.', 'kadr' ),
				$this->min_php,
				PHP_VERSION
			);
		}

		if ( version_compare( (string) get_bloginfo( 'version' ), $this->min_wp, '<' ) ) {
			$this->errors[] = sprintf(
				/* translators: 1: wymagana wersja WordPressa, 2: aktualna wersja */
				__( 'Kadr wymaga WordPressa w wersji %1$s lub nowszej. Ta instalacja używa %2$s.', 'kadr' ),
				$this->min_wp,
				get_bloginfo( 'version' )
			);
		}

		return array() === $this->errors;
	}

	/**
	 * Ostrzeżenia miękkie — nie blokują startu, ale są widoczne dla administratora.
	 *
	 * @return list<string>
	 */
	public function warnings(): array {
		$warnings = array();

		if ( ! extension_loaded( 'imagick' ) ) {
			$warnings[] = __( 'Rozszerzenie Imagick nie jest dostępne. Kadr użyje GD, co obniża jakość i szybkość przetwarzania zdjęć.', 'kadr' );
		}

		if ( ! extension_loaded( 'sodium' ) && ! extension_loaded( 'openssl' ) ) {
			$warnings[] = __( 'Brak rozszerzenia sodium lub openssl. Szyfrowanie kluczy API nie będzie możliwe.', 'kadr' );
		}

		return $warnings;
	}

	public function show_admin_notice(): void {
		$errors = $this->errors;

		add_action(
			'admin_notices',
			static function () use ( $errors ): void {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				printf( '<div class="notice notice-error"><p><strong>%s</strong></p><ul style="list-style:disc;margin-left:1.5em">', esc_html__( 'Kadr nie został uruchomiony', 'kadr' ) );
				foreach ( $errors as $error ) {
					printf( '<li>%s</li>', esc_html( $error ) );
				}
				echo '</ul></div>';
			}
		);
	}
}
