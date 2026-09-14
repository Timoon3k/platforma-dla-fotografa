<?php
declare( strict_types=1 );

namespace Kadr\Infrastructure\WordPress;

defined( 'ABSPATH' ) || exit;

/**
 * Wzorce bloków — gotowe układy do wstawienia jednym kliknięciem.
 *
 * Bez tego administrator musiałby ręcznie poskładać stronę z dziewięciu bloków
 * i trafić w udokumentowaną kolejność sekcji (docs/DESIGN-SYSTEM.md §8).
 * Wzorzec jest punktem startowym — po wstawieniu treść jest w pełni edytowalna.
 */
final class Patterns {

	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register' ), 30 );
	}

	public function register(): void {
		if ( ! function_exists( 'register_block_pattern_category' ) ) {
			return;
		}

		register_block_pattern_category(
			'kadr',
			array( 'label' => __( 'Kadr', 'kadr' ) )
		);

		register_block_pattern(
			'kadr/landing',
			array(
				'title'       => __( 'Strona główna — pełny układ', 'kadr' ),
				'description' => __( 'Wszystkie sekcje strony sprzedażowej w udokumentowanej kolejności: problem przed rozwiązaniem, dowód społeczny nisko.', 'kadr' ),
				'categories'  => array( 'kadr' ),
				'content'     => $this->landing(),
			)
		);

		register_block_pattern(
			'kadr/cookie-policy',
			array(
				'title'       => __( 'Dokument — polityka cookies (szkic)', 'kadr' ),
				'description' => __( 'Szkic polityki cookies odpowiadający kategoriom zgód zaimplementowanym we wtyczce. Wymaga weryfikacji prawnej.', 'kadr' ),
				'categories'  => array( 'kadr' ),
				'postTypes'   => array( ContentTypes::DOCUMENT ),
				'content'     => $this->cookie_policy(),
			)
		);
	}

	/**
	 * Kolejność sekcji wg docs/DESIGN-SYSTEM.md §8.
	 *
	 * Bloki wstawiane bez atrybutów korzystają z wartości domyślnych
	 * zdefiniowanych w block.json — żadna treść nie jest tu duplikowana.
	 */
	/**
	 * Treść strony głównej.
	 *
	 * Publiczna, bo używa jej także kreator pierwszego uruchomienia
	 * (`Presentation\Admin\SetupPage`). Jedno źródło układu: dopisanie
	 * sekcji do wzorca automatycznie zmienia to, co dostaje administrator
	 * klikający „Utwórz stronę główną". Druga kopia rozjechałaby się
	 * przy pierwszej zmianie.
	 */
	public function landing(): string {
		$sections = array(
			'<!-- wp:kadr/hero /-->',
			'<!-- wp:kadr/problem /-->',
			'<!-- wp:kadr/journey /-->',
			'<!-- wp:kadr/feature /-->',
			'<!-- wp:kadr/proof /-->',
			sprintf(
				'<!-- wp:kadr/feature %s /-->',
				wp_json_encode(
					array(
						'eyebrow'   => __( 'Odbitki', 'kadr' ),
						'number'    => '05',
						'title'     => __( 'Odbitki zamawiane bez wychodzenia z galerii', 'kadr' ),
						'body'      => __( 'Klient widzi, jak jego kadr zmieści się w danym formacie, zanim zamówi. Zdjęcie 3:2 w formacie 13×18 zostanie przycięte — lepiej, żeby zobaczył to teraz niż po wywołaniu.', 'kadr' ),
						'mediaSide' => 'left',
						'bullets'   => array(
							array( 'text' => __( 'Własne formaty, papiery i ceny — nic nie jest zakodowane na sztywno', 'kadr' ) ),
							array( 'text' => __( 'Podgląd kadrowania dla każdego formatu', 'kadr' ) ),
							array( 'text' => __( 'Płatność trafia prosto na Twoje konto', 'kadr' ) ),
						),
					)
				)
			),
			sprintf(
				'<!-- wp:kadr/feature %s /-->',
				wp_json_encode(
					array(
						'eyebrow' => __( 'Rezerwacje', 'kadr' ),
						'number'  => '06',
						'title'   => __( 'Terminarz, który sam pilnuje zadatków', 'kadr' ),
						'body'    => __( 'Ustawiasz usługi, godziny i bufory między sesjami. Klient wybiera termin i płaci zadatek, a termin blokuje się dopiero po zaksięgowaniu wpłaty.', 'kadr' ),
						'bullets' => array(
							array( 'text' => __( 'Bufor przed i po sesji — bez dwóch plenerów pod rząd w dwóch końcach miasta', 'kadr' ) ),
							array( 'text' => __( 'Blokady na urlop i dni, w które nie pracujesz', 'kadr' ) ),
							array( 'text' => __( 'Przypomnienia na tydzień i na dzień przed sesją', 'kadr' ) ),
						),
					)
				)
			),
			'<!-- wp:kadr/pricing /-->',
			'<!-- wp:kadr/faq /-->',
			'<!-- wp:kadr/testimonials /-->',
			'<!-- wp:kadr/cta /-->',
		);

		return implode( "\n\n", $sections );
	}

	/**
	 * Szkic polityki cookies zgodny z kategoriami z klasy Consent.
	 *
	 * ⚠️ Szkic. Wymaga weryfikacji prawnej przed publikacją (docs/LEGAL.md §7).
	 */
	private function cookie_policy(): string {
		$blocks = array(
			array(
				'p',
				__( 'Dokument opisuje, z jakich plików cookies korzysta ta strona i jak możesz zmienić swój wybór. Szkic wymaga weryfikacji prawnej przed publikacją.', 'kadr' ),
			),
			array( 'h2', __( 'Czym są pliki cookies', 'kadr' ) ),
			array(
				'p',
				__( 'To niewielkie pliki zapisywane przez przeglądarkę na Twoim urządzeniu. Używamy ich w czterech zakresach opisanych niżej. Poza kategorią niezbędną nic nie uruchamia się przed Twoją zgodą — skrypty nie są wykonywane, dopóki jej nie udzielisz.', 'kadr' ),
			),
			array( 'h2', __( 'Niezbędne', 'kadr' ) ),
			array(
				'p',
				__( 'Utrzymanie sesji, bezpieczeństwo formularzy i zapamiętanie Twojego wyboru w oknie zgód. Bez nich strona nie działa poprawnie, dlatego nie można ich wyłączyć. Podstawą jest uzasadniony interes polegający na dostarczeniu żądanej usługi.', 'kadr' ),
			),
			array( 'h2', __( 'Preferencje', 'kadr' ) ),
			array(
				'p',
				__( 'Zapamiętanie ustawień interfejsu, na przykład wybranego widoku. Wyłączenie tej kategorii nie ogranicza dostępu do treści.', 'kadr' ),
			),
			array( 'h2', __( 'Analityka', 'kadr' ) ),
			array(
				'p',
				__( 'Zbiorcze statystyki odwiedzin. Adresy IP są anonimizowane, nie tworzymy profili behawioralnych i nie śledzimy Cię na innych stronach.', 'kadr' ),
			),
			array( 'h2', __( 'Marketing', 'kadr' ) ),
			array(
				'p',
				__( 'Mierzenie skuteczności kampanii. Kategoria jest domyślnie wyłączona i pozostaje wyłączona, dopóki jej nie włączysz.', 'kadr' ),
			),
			array( 'h2', __( 'Zmiana lub wycofanie zgody', 'kadr' ) ),
			array(
				'p',
				__( 'Swój wybór możesz zmienić w każdej chwili, otwierając ustawienia cookies w stopce strony. Wycofanie zgody działa na przyszłość i nie wpływa na zgodność z prawem wcześniejszego przetwarzania. Zgoda jest wersjonowana — jeśli zmienimy zakres kategorii, zapytamy ponownie.', 'kadr' ),
			),
			array( 'h2', __( 'Okres przechowywania', 'kadr' ) ),
			array(
				'p',
				__( 'Zapis Twojego wyboru przechowujemy przez 180 dni. Po tym czasie zapytamy ponownie.', 'kadr' ),
			),
		);

		$markup = array();

		foreach ( $blocks as [$tag, $text] ) {
			$markup[] = 'h2' === $tag
				? sprintf( "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">%s</h2>\n<!-- /wp:heading -->", esc_html( $text ) )
				: sprintf( "<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->", esc_html( $text ) );
		}

		return implode( "\n\n", $markup );
	}
}
