<?php
declare( strict_types=1 );

namespace Kadr\Presentation\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Pomocniki renderowania bloków.
 *
 * Cel: jedno miejsce, w którym decydujemy o escapingu i o strukturze sekcji,
 * żeby dwanaście plików render.php nie powtarzało tych samych decyzji
 * — i nie pomyliło się w żadnym z nich.
 */
final class Render {

	/**
	 * Treść bogata pochodząca od administratora (RichText).
	 *
	 * Zawężona lista tagów — administrator formatuje tekst, ale nie wstawia
	 * dowolnego HTML-u (docs/SECURITY.md §9).
	 */
	public static function rich( string $html ): string {
		return wp_kses(
			$html,
			array(
				'strong' => array(),
				'em'     => array(),
				'br'     => array(),
				'a'      => array(
					'href'   => array(),
					'rel'    => array(),
					'target' => array(),
				),
				'span'   => array( 'class' => array() ),
			)
		);
	}

	/**
	 * Etykieta sekcji z numerem, jak w katalogu wystawy.
	 */
	public static function eyebrow( string $text, string $number = '' ): string {
		if ( '' === trim( $text ) ) {
			return '';
		}

		$label = '' !== $number
			? sprintf( '<span class="kadr-eyebrow__num">%s</span> %s', esc_html( $number ), esc_html( $text ) )
			: esc_html( $text );

		return sprintf( '<p class="kadr-eyebrow">%s</p>', $label );
	}

	/**
	 * Atrybuty opakowania bloku wraz z klasami wariantu.
	 *
	 * Wynik `get_block_wrapper_attributes()` jest już zabezpieczony przez rdzeń
	 * WordPressa (klasy przez esc_attr, styl przez safecss_filter_attr).
	 * NIE wolno przepuszczać go przez wp_kses_data() — to zniekształca
	 * cudzysłowy w atrybutach. Wypisujemy bezpośrednio.
	 *
	 * @param array<string, string> $extra
	 */
	public static function wrapper( string $class, array $extra = array() ): string {
		return get_block_wrapper_attributes( array_merge( array( 'class' => $class ), $extra ) );
	}

	/**
	 * Obraz responsywny z poprawnymi wymiarami (zero CLS).
	 *
	 * @param int    $id       ID załącznika.
	 * @param string $sizes    Atrybut sizes.
	 * @param bool   $priority Czy to zasób LCP — wtedy bez lazy i z wysokim priorytetem.
	 */
	public static function image( int $id, string $sizes, bool $priority = false, string $class = '' ): string {
		if ( $id <= 0 ) {
			return '';
		}

		$attr = array(
			'sizes'    => $sizes,
			'decoding' => 'async',
			'class'    => $class,
		);

		if ( $priority ) {
			// Obraz LCP: nigdy lazy, zawsze wysoki priorytet (CLAUDE.md §6).
			$attr['loading']       = 'eager';
			$attr['fetchpriority'] = 'high';
		} else {
			$attr['loading'] = 'lazy';
		}

		return wp_get_attachment_image( $id, 'full', false, $attr );
	}

	/**
	 * Przycisk wezwania do działania. Pusty URL albo pusta etykieta = brak przycisku.
	 */
	public static function cta( string $label, string $url, string $variant = 'primary', bool $large = true ): string {
		$label = trim( $label );
		$url   = trim( $url );

		if ( '' === $label || '' === $url ) {
			return '';
		}

		return sprintf(
			'<a class="kadr-btn kadr-btn--%1$s%2$s" href="%3$s">%4$s</a>',
			esc_attr( $variant ),
			$large ? ' kadr-btn--lg' : '',
			esc_url( $url ),
			esc_html( $label )
		);
	}

	/**
	 * Miejsce na dysku w formie czytelnej dla człowieka.
	 *
	 * Fotograf myśli w gigabajtach, nie w bajtach (docs/BILLING.md §9).
	 */
	public static function bytes( int $bytes ): string {
		$tb = 1099511627776;
		$gb = 1073741824;

		if ( $bytes >= $tb ) {
			return sprintf(
				/* translators: %s: liczba terabajtów */
				__( '%s TB', 'kadr' ),
				number_format_i18n( $bytes / $tb, 0 )
			);
		}

		return sprintf(
			/* translators: %s: liczba gigabajtów */
			__( '%s GB', 'kadr' ),
			number_format_i18n( $bytes / $gb, 0 )
		);
	}

	/**
	 * Kwota w złotych, bez końcówki groszowej gdy jest zerowa.
	 */
	public static function price( \Kadr\Domain\Shared\Money $money ): string {
		$decimals = 0 === $money->minor % 100 ? 0 : 2;

		return sprintf(
			/* translators: %s: kwota */
			__( '%s zł', 'kadr' ),
			number_format_i18n( $money->major(), $decimals )
		);
	}

	/**
	 * Slot na treść, której jeszcze nie mamy.
	 *
	 * Nigdy nie wypełniamy go wymyśloną opinią, oceną ani logotypem (CLAUDE.md §7).
	 */
	public static function slot( string $text ): string {
		return sprintf( '<div class="kadr-slot">%s</div>', esc_html( $text ) );
	}
}
