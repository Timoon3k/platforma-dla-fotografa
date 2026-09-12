<?php
/**
 * Sekcja „Wybór i dopłata”.
 *
 * Kwoty liczy warstwa domenowa (Money), nie szablon — dzięki temu strona
 * marketingowa nie może pokazać arytmetyki innej niż produkt.
 *
 * @var array<string, mixed> $attributes
 * @package Kadr
 */

declare( strict_types=1 );

use Kadr\Domain\Shared\Money;
use Kadr\Presentation\Blocks\Render;

defined( 'ABSPATH' ) || exit;

$kadr_package  = max( 0, (int) ( $attributes['packageSize'] ?? 0 ) );
$kadr_selected = max( 0, (int) ( $attributes['selected'] ?? 0 ) );
$kadr_extra    = max( 0, $kadr_selected - $kadr_package );

$kadr_unit  = Money::fromMajor( (float) ( $attributes['extraPrice'] ?? 0 ) );
$kadr_total = $kadr_unit->multiply( $kadr_extra );

/**
 * Formatowanie kwoty zgodnie z lokalizacją strony.
 */
$kadr_format = static function ( Money $money ): string {
	return sprintf(
		/* translators: %s: kwota */
		__( '%s zł', 'kadr' ),
		number_format_i18n( $money->major(), 0 )
	);
};
?>
<section <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atrybuty zabezpieczone przez rdzeń WP.
	echo Render::wrapper( 'kadr-section kadr-proof' ); ?>>
	<div class="kadr-container kadr-proof__inner">
		<div class="kadr-proof__copy">
			<?php
			echo wp_kses_post(
				Render::eyebrow(
					(string) ( $attributes['eyebrow'] ?? '' ),
					(string) ( $attributes['number'] ?? '' )
				)
			);
			?>
			<h2><?php echo wp_kses_post( Render::rich( (string) ( $attributes['title'] ?? '' ) ) ); ?></h2>
			<p class="kadr-proof__lead"><?php echo wp_kses_post( Render::rich( (string) ( $attributes['lead'] ?? '' ) ) ); ?></p>
		</div>

		<div class="kadr-proof__calc">
			<dl class="kadr-proof__rows">
				<div class="kadr-proof__row">
					<dt><?php esc_html_e( 'Pakiet obejmuje', 'kadr' ); ?></dt>
					<dd data-kadr-numeric><?php echo esc_html( sprintf( /* translators: %s: liczba zdjęć */ _n( '%s zdjęcie', '%s zdjęć', $kadr_package, 'kadr' ), number_format_i18n( $kadr_package ) ) ); ?></dd>
				</div>
				<div class="kadr-proof__row">
					<dt><?php esc_html_e( 'Klientka wybrała', 'kadr' ); ?></dt>
					<dd data-kadr-numeric><?php echo esc_html( sprintf( /* translators: %s: liczba zdjęć */ _n( '%s zdjęcie', '%s zdjęć', $kadr_selected, 'kadr' ), number_format_i18n( $kadr_selected ) ) ); ?></dd>
				</div>
				<div class="kadr-proof__row kadr-proof__row--extra">
					<dt><?php esc_html_e( 'Ponad pakiet', 'kadr' ); ?></dt>
					<dd data-kadr-numeric>
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: liczba zdjęć, 2: cena jednostkowa */
								__( '%1$s × %2$s', 'kadr' ),
								number_format_i18n( $kadr_extra ),
								$kadr_format( $kadr_unit )
							)
						);
						?>
					</dd>
				</div>
				<div class="kadr-proof__row kadr-proof__row--total">
					<dt><?php esc_html_e( 'Do dopłaty', 'kadr' ); ?></dt>
					<dd class="kadr-price" data-kadr-numeric><?php echo esc_html( $kadr_format( $kadr_total ) ); ?></dd>
				</div>
			</dl>

			<p class="kadr-proof__action" aria-hidden="true">
				<span class="kadr-btn kadr-btn--primary kadr-btn--lg kadr-proof__fake"><?php esc_html_e( 'Zapłać BLIK-iem', 'kadr' ); ?></span>
			</p>
			<p class="kadr-sr-only"><?php esc_html_e( 'Powyżej: podgląd podsumowania, które widzi klient w galerii.', 'kadr' ); ?></p>
		</div>
	</div>

	<?php if ( '' !== trim( (string) ( $attributes['footnote'] ?? '' ) ) ) : ?>
		<div class="kadr-container">
			<p class="kadr-proof__footnote"><?php echo esc_html( (string) $attributes['footnote'] ); ?></p>
		</div>
	<?php endif; ?>
</section>
