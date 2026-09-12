<?php
/**
 * Sekcja „Problem”.
 *
 * @var array<string, mixed> $attributes
 * @package Kadr
 */

declare( strict_types=1 );

use Kadr\Presentation\Blocks\Render;

defined( 'ABSPATH' ) || exit;

/** @var list<array{text?: string}> $kadr_items */
$kadr_items = is_array( $attributes['items'] ?? null ) ? $attributes['items'] : array();
?>
<section <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atrybuty zabezpieczone przez rdzeń WP.
	echo Render::wrapper( 'kadr-section kadr-problem' ); ?>>
	<div class="kadr-container">
		<?php
		echo wp_kses_post(
			Render::eyebrow(
				(string) ( $attributes['eyebrow'] ?? '' ),
				(string) ( $attributes['number'] ?? '' )
			)
		);
		?>

		<div class="kadr-problem__head">
			<h2 class="kadr-problem__title"><?php echo wp_kses_post( Render::rich( (string) ( $attributes['title'] ?? '' ) ) ); ?></h2>
			<p class="kadr-problem__lead"><?php echo wp_kses_post( Render::rich( (string) ( $attributes['lead'] ?? '' ) ) ); ?></p>
		</div>

		<?php if ( array() !== $kadr_items ) : ?>
			<ol class="kadr-problem__list">
				<?php foreach ( $kadr_items as $kadr_i => $kadr_item ) : ?>
					<li class="kadr-problem__item">
						<span class="kadr-problem__step" aria-hidden="true"><?php echo esc_html( str_pad( (string) ( $kadr_i + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
						<span class="kadr-problem__text"><?php echo wp_kses_post( Render::rich( (string) ( $kadr_item['text'] ?? '' ) ) ); ?></span>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>

		<?php if ( '' !== trim( (string) ( $attributes['conclusion'] ?? '' ) ) ) : ?>
			<p class="kadr-problem__conclusion"><?php echo wp_kses_post( Render::rich( (string) $attributes['conclusion'] ) ); ?></p>
		<?php endif; ?>
	</div>
</section>
