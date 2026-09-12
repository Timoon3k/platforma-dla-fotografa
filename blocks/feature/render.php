<?php
/**
 * Sekcja funkcji: tekst + obraz.
 *
 * @var array<string, mixed> $attributes
 * @package Kadr
 */

declare( strict_types=1 );

use Kadr\Presentation\Blocks\Render;

defined( 'ABSPATH' ) || exit;

/** @var list<array{text?: string}> $kadr_bullets */
$kadr_bullets = is_array( $attributes['bullets'] ?? null ) ? $attributes['bullets'] : array();

$kadr_side  = 'left' === ( $attributes['mediaSide'] ?? 'right' ) ? 'left' : 'right';
$kadr_image = Render::image( (int) ( $attributes['imageId'] ?? 0 ), '(max-width: 900px) 100vw, 50vw' );
?>
<section <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atrybuty zabezpieczone przez rdzeń WP.
	echo Render::wrapper( 'kadr-section kadr-feature kadr-feature--media-' . $kadr_side ); ?>>
	<div class="kadr-container kadr-feature__inner">
		<div class="kadr-feature__copy" data-kadr-reveal>
			<?php
			echo wp_kses_post(
				Render::eyebrow(
					(string) ( $attributes['eyebrow'] ?? '' ),
					(string) ( $attributes['number'] ?? '' )
				)
			);
			?>

			<h2><?php echo wp_kses_post( Render::rich( (string) ( $attributes['title'] ?? '' ) ) ); ?></h2>
			<p class="kadr-feature__body"><?php echo wp_kses_post( Render::rich( (string) ( $attributes['body'] ?? '' ) ) ); ?></p>

			<?php if ( array() !== $kadr_bullets ) : ?>
				<ul class="kadr-feature__bullets">
					<?php foreach ( $kadr_bullets as $kadr_bullet ) : ?>
						<li><?php echo wp_kses_post( Render::rich( (string) ( $kadr_bullet['text'] ?? '' ) ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php
			echo wp_kses_post(
				Render::cta(
					(string) ( $attributes['linkLabel'] ?? '' ),
					(string) ( $attributes['linkUrl'] ?? '' ),
					'ghost',
					false
				)
			);
			?>
		</div>

		<div class="kadr-feature__media" data-kadr-reveal>
			<?php
			echo '' !== $kadr_image
				? wp_kses_post( $kadr_image )
				: wp_kses_post( Render::slot( __( 'Miejsce na zrzut ekranu tej funkcji.', 'kadr' ) ) );
			?>
		</div>
	</div>
</section>
