<?php
/**
 * Sekcja opinii.
 *
 * Dopóki nie ma prawdziwych wypowiedzi, renderujemy jawnie oznaczone puste
 * sloty. Nigdy nie generujemy przykładowych opinii, ocen ani logotypów
 * klientów (CLAUDE.md §7).
 *
 * @var array<string, mixed> $attributes
 * @package Kadr
 */

declare( strict_types=1 );

use Kadr\Presentation\Blocks\Render;

defined( 'ABSPATH' ) || exit;

/** @var list<array{quote?: string, author?: string, role?: string}> $kadr_items */
$kadr_items = is_array( $attributes['items'] ?? null ) ? $attributes['items'] : array();

$kadr_items = array_values(
	array_filter(
		$kadr_items,
		static fn( $item ): bool => is_array( $item ) && '' !== trim( (string) ( $item['quote'] ?? '' ) )
	)
);

$kadr_slots = max( 0, (int) ( $attributes['slots'] ?? 0 ) - count( $kadr_items ) );
?>
<section <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atrybuty zabezpieczone przez rdzeń WP.
	echo Render::wrapper( 'kadr-section kadr-testimonials' ); ?>>
	<div class="kadr-container">
		<?php
		echo wp_kses_post(
			Render::eyebrow(
				(string) ( $attributes['eyebrow'] ?? '' ),
				(string) ( $attributes['number'] ?? '' )
			)
		);
		?>
		<h2><?php echo wp_kses_post( Render::rich( (string) ( $attributes['title'] ?? '' ) ) ); ?></h2>

		<?php if ( '' !== trim( (string) ( $attributes['lead'] ?? '' ) ) ) : ?>
			<p class="kadr-testimonials__lead"><?php echo wp_kses_post( Render::rich( (string) $attributes['lead'] ) ); ?></p>
		<?php endif; ?>

		<div class="kadr-testimonials__grid">
			<?php foreach ( $kadr_items as $kadr_item ) : ?>
				<figure class="kadr-card kadr-testimonials__item kadr-lift" data-kadr-reveal>
					<blockquote><p><?php echo wp_kses_post( Render::rich( (string) $kadr_item['quote'] ) ); ?></p></blockquote>
					<figcaption>
						<span class="kadr-testimonials__author"><?php echo esc_html( (string) ( $kadr_item['author'] ?? '' ) ); ?></span>
						<?php if ( '' !== trim( (string) ( $kadr_item['role'] ?? '' ) ) ) : ?>
							<span class="kadr-testimonials__role"><?php echo esc_html( (string) $kadr_item['role'] ); ?></span>
						<?php endif; ?>
					</figcaption>
				</figure>
			<?php endforeach; ?>

			<?php for ( $kadr_i = 0; $kadr_i < $kadr_slots; $kadr_i++ ) : ?>
				<?php echo wp_kses_post( Render::slot( __( 'Miejsce na prawdziwą opinię', 'kadr' ) ) ); ?>
			<?php endfor; ?>
		</div>
	</div>
</section>
