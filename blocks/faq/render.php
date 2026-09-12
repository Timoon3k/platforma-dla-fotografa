<?php
/**
 * Sekcja pytań.
 *
 * Natywne details/summary — zero JavaScriptu, pełna obsługa klawiatury
 * i czytników ekranu bez ani jednego atrybutu ARIA.
 *
 * @var array<string, mixed> $attributes
 * @package Kadr
 */

declare( strict_types=1 );

use Kadr\Presentation\Blocks\Render;

defined( 'ABSPATH' ) || exit;

/** @var list<array{question?: string, answer?: string}> $kadr_items */
$kadr_items = is_array( $attributes['items'] ?? null ) ? $attributes['items'] : array();
?>
<section <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atrybuty zabezpieczone przez rdzeń WP.
	echo Render::wrapper( 'kadr-section kadr-faq' ); ?>>
	<div class="kadr-container kadr-faq__inner">
		<div class="kadr-faq__head">
			<?php
			echo wp_kses_post(
				Render::eyebrow(
					(string) ( $attributes['eyebrow'] ?? '' ),
					(string) ( $attributes['number'] ?? '' )
				)
			);
			?>
			<h2><?php echo wp_kses_post( Render::rich( (string) ( $attributes['title'] ?? '' ) ) ); ?></h2>
		</div>

		<div class="kadr-faq__list">
			<?php foreach ( $kadr_items as $kadr_item ) : ?>
				<?php if ( '' === trim( (string) ( $kadr_item['question'] ?? '' ) ) ) : ?>
					<?php continue; ?>
				<?php endif; ?>
				<details class="kadr-faq__item">
					<summary class="kadr-faq__question"><?php echo esc_html( (string) $kadr_item['question'] ); ?></summary>
					<div class="kadr-faq__answer">
						<p><?php echo wp_kses_post( Render::rich( (string) ( $kadr_item['answer'] ?? '' ) ) ); ?></p>
					</div>
				</details>
			<?php endforeach; ?>
		</div>
	</div>
</section>
