<?php
/**
 * Sekcja hero.
 *
 * Obraz hero jest zasobem LCP — renderowany bez lazy loadingu,
 * z fetchpriority="high" (CLAUDE.md §6).
 *
 * @var array<string, mixed> $attributes
 * @package Kadr
 */

declare( strict_types=1 );

use Kadr\Presentation\Blocks\Render;

defined( 'ABSPATH' ) || exit;

$kadr_image = Render::image(
	(int) ( $attributes['imageId'] ?? 0 ),
	'(max-width: 900px) 100vw, 55vw',
	true,
	'kadr-hero__shot'
);
?>
<section <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atrybuty zabezpieczone przez rdzeń WP.
	echo Render::wrapper( 'kadr-hero' ); ?>>
	<div class="kadr-container kadr-container--wide kadr-hero__inner">
		<div class="kadr-hero__copy">
			<?php echo wp_kses_post( Render::eyebrow( (string) ( $attributes['eyebrow'] ?? '' ) ) ); ?>

			<h1 class="kadr-hero__title"><?php echo wp_kses_post( Render::rich( (string) ( $attributes['title'] ?? '' ) ) ); ?></h1>

			<p class="kadr-hero__lead"><?php echo wp_kses_post( Render::rich( (string) ( $attributes['lead'] ?? '' ) ) ); ?></p>

			<div class="kadr-hero__actions">
				<?php
				echo wp_kses_post(
					Render::cta(
						(string) ( $attributes['ctaLabel'] ?? '' ),
						(string) ( $attributes['ctaUrl'] ?? '' ),
						'primary'
					)
				);
				echo wp_kses_post(
					Render::cta(
						(string) ( $attributes['altLabel'] ?? '' ),
						(string) ( $attributes['altUrl'] ?? '' ),
						'secondary'
					)
				);
				?>
			</div>

			<?php if ( '' !== trim( (string) ( $attributes['note'] ?? '' ) ) ) : ?>
				<p class="kadr-hero__note"><?php echo esc_html( (string) $attributes['note'] ); ?></p>
			<?php endif; ?>
		</div>

		<figure class="kadr-hero__media">
			<?php if ( '' !== $kadr_image ) : ?>
				<?php echo wp_kses_post( $kadr_image ); ?>
			<?php else : ?>
				<?php
				// Świadomie pusty slot zamiast wymyślonej grafiki — hero ma pokazywać
				// prawdziwy interfejs produktu (CLAUDE.md §7).
				echo wp_kses_post( Render::slot( __( 'Miejsce na zrzut ekranu wyboru zdjęć. Dodaj obraz w ustawieniach bloku.', 'kadr' ) ) );
				?>
			<?php endif; ?>

			<?php if ( '' !== trim( (string) ( $attributes['imageCaption'] ?? '' ) ) ) : ?>
				<figcaption class="kadr-hero__caption"><?php echo esc_html( (string) $attributes['imageCaption'] ); ?></figcaption>
			<?php endif; ?>
		</figure>
	</div>
</section>
