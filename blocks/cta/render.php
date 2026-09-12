<?php
/**
 * Wezwanie do działania zamykające stronę.
 *
 * @var array<string, mixed> $attributes
 * @package Kadr
 */

declare( strict_types=1 );

use Kadr\Presentation\Blocks\Render;

defined( 'ABSPATH' ) || exit;
?>
<section <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atrybuty zabezpieczone przez rdzeń WP.
	echo Render::wrapper( 'kadr-section kadr-section--inverse kadr-cta kadr-ambient' ); ?>>
	<div class="kadr-container kadr-cta__inner" data-kadr-reveal>
		<h2 class="kadr-cta__title"><?php echo wp_kses_post( Render::rich( (string) ( $attributes['title'] ?? '' ) ) ); ?></h2>
		<p class="kadr-cta__lead"><?php echo wp_kses_post( Render::rich( (string) ( $attributes['lead'] ?? '' ) ) ); ?></p>

		<?php
		echo wp_kses_post(
			Render::cta(
				(string) ( $attributes['ctaLabel'] ?? '' ),
				(string) ( $attributes['ctaUrl'] ?? '' ),
				'primary'
			)
		);
		?>

		<?php if ( '' !== trim( (string) ( $attributes['note'] ?? '' ) ) ) : ?>
			<p class="kadr-cta__note"><?php echo esc_html( (string) $attributes['note'] ); ?></p>
		<?php endif; ?>
	</div>
</section>
