<?php
/**
 * Oś ścieżki klienta.
 *
 * Renderowana jako lista uporządkowana — kolejność etapów jest treścią,
 * nie dekoracją, więc musi być czytelna dla czytnika ekranu.
 *
 * @var array<string, mixed> $attributes
 * @package Kadr
 */

declare( strict_types=1 );

use Kadr\Presentation\Blocks\Render;

defined( 'ABSPATH' ) || exit;

/** @var list<array{label?: string, detail?: string, actor?: string}> $kadr_steps */
$kadr_steps = is_array( $attributes['steps'] ?? null ) ? $attributes['steps'] : array();

$kadr_actors = array(
	'client'       => __( 'Widzi klient', 'kadr' ),
	'photographer' => __( 'Robisz Ty', 'kadr' ),
	'auto'         => __( 'Dzieje się samo', 'kadr' ),
);
?>
<section <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atrybuty zabezpieczone przez rdzeń WP.
	echo Render::wrapper( 'kadr-section kadr-journey' ); ?>>
	<div class="kadr-container kadr-container--wide">
		<?php
		echo wp_kses_post(
			Render::eyebrow(
				(string) ( $attributes['eyebrow'] ?? '' ),
				(string) ( $attributes['number'] ?? '' )
			)
		);
		?>

		<div class="kadr-journey__head">
			<h2><?php echo wp_kses_post( Render::rich( (string) ( $attributes['title'] ?? '' ) ) ); ?></h2>
			<p class="kadr-journey__lead"><?php echo wp_kses_post( Render::rich( (string) ( $attributes['lead'] ?? '' ) ) ); ?></p>
		</div>

		<?php if ( array() !== $kadr_steps ) : ?>
			<ol class="kadr-journey__track">
				<?php foreach ( $kadr_steps as $kadr_i => $kadr_step ) : ?>
					<?php
					$kadr_actor = (string) ( $kadr_step['actor'] ?? 'auto' );
					$kadr_actor = array_key_exists( $kadr_actor, $kadr_actors ) ? $kadr_actor : 'auto';
					?>
					<li class="kadr-journey__step" data-kadr-actor="<?php echo esc_attr( $kadr_actor ); ?>">
						<span class="kadr-journey__marker" aria-hidden="true"><?php echo esc_html( (string) ( $kadr_i + 1 ) ); ?></span>
						<span class="kadr-journey__actor"><?php echo esc_html( $kadr_actors[ $kadr_actor ] ); ?></span>
						<h3 class="kadr-journey__label"><?php echo esc_html( (string) ( $kadr_step['label'] ?? '' ) ); ?></h3>
						<p class="kadr-journey__detail"><?php echo wp_kses_post( Render::rich( (string) ( $kadr_step['detail'] ?? '' ) ) ); ?></p>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
	</div>
</section>
