<?php
/**
 * Tabela cennika.
 *
 * Ceny, limity i entitlementy pochodzą z PlanRegistry (ADR-008).
 * Administrator nie może ich tutaj nadpisać — cena nie może istnieć
 * w dwóch miejscach, bo po pierwszej zmianie cennika marketing
 * rozjechałby się z produktem.
 *
 * @var array<string, mixed> $attributes
 * @package Kadr
 */

declare( strict_types=1 );

use Kadr\Domain\Billing\Plan;
use Kadr\Domain\Billing\PlanRegistry;
use Kadr\Presentation\Blocks\Render;

defined( 'ABSPATH' ) || exit;

$kadr_show_free = (bool) ( $attributes['showFree'] ?? true );
$kadr_plans     = $kadr_show_free ? PlanRegistry::all() : PlanRegistry::paid();
$kadr_cta_url   = (string) ( $attributes['ctaUrl'] ?? '' );

/**
 * Wiersze porównania — etykieta i sposób odczytania wartości z planu.
 *
 * @var array<string, callable(Plan): string> $kadr_rows
 */
$kadr_rows = array(
	__( 'Aktywne galerie', 'kadr' )       => static fn( Plan $p ): string => $p->limit( 'gallery_limit' )->isUnlimited()
		? __( 'bez limitu', 'kadr' )
		: number_format_i18n( (int) $p->limit( 'gallery_limit' )->value ),
	__( 'Miejsce na pliki', 'kadr' )      => static fn( Plan $p ): string => Render::bytes( (int) $p->value( 'storage_limit_bytes' ) ),
	__( 'Klienci', 'kadr' )               => static fn( Plan $p ): string => $p->limit( 'client_limit' )->isUnlimited()
		? __( 'bez limitu', 'kadr' )
		: number_format_i18n( (int) $p->limit( 'client_limit' )->value ),
	__( 'Użytkownicy', 'kadr' )           => static fn( Plan $p ): string => number_format_i18n( (int) $p->value( 'team_seats' ) ),
	__( 'Sprzedaż zdjęć ponad pakiet', 'kadr' ) => static fn( Plan $p ): string => $p->allows( 'sell_extra_photos' ) ? '✓' : '—',
	__( 'Odbitki i produkty', 'kadr' )    => static fn( Plan $p ): string => $p->allows( 'products_prints' ) ? '✓' : '—',
	__( 'Rezerwacje online', 'kadr' )     => static fn( Plan $p ): string => $p->allows( 'bookings' ) ? '✓' : '—',
	__( 'Motywy galerii', 'kadr' )        => static fn( Plan $p ): string => $p->limit( 'gallery_themes' )->isUnlimited()
		? __( 'wszystkie', 'kadr' )
		: number_format_i18n( (int) $p->limit( 'gallery_themes' )->value ),
	__( 'Bez naszego logo', 'kadr' )      => static fn( Plan $p ): string => $p->allows( 'hide_platform_branding' ) ? '✓' : '—',
	__( 'Własna domena', 'kadr' )         => static fn( Plan $p ): string => $p->allows( 'custom_domain' ) ? '✓' : '—',
	__( 'Dostęp do API', 'kadr' )         => static fn( Plan $p ): string => $p->allows( 'api_access' ) ? '✓' : '—',
);
?>
<section
	<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- atrybuty zabezpieczone przez rdzeń WP.
	echo Render::wrapper( 'kadr-section kadr-pricing' ); ?>
	data-wp-interactive="kadr/pricing"
	<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wyjście rdzenia WP.
	echo wp_interactivity_data_wp_context( array( 'yearly' => false ) ); ?>
>
	<div class="kadr-container kadr-container--wide">
		<?php
		echo wp_kses_post(
			Render::eyebrow(
				(string) ( $attributes['eyebrow'] ?? '' ),
				(string) ( $attributes['number'] ?? '' )
			)
		);
		?>

		<div class="kadr-pricing__head">
			<h2><?php echo wp_kses_post( Render::rich( (string) ( $attributes['title'] ?? '' ) ) ); ?></h2>
			<p class="kadr-pricing__lead"><?php echo wp_kses_post( Render::rich( (string) ( $attributes['lead'] ?? '' ) ) ); ?></p>

			<div class="kadr-switch" role="group" aria-label="<?php esc_attr_e( 'Cykl rozliczeniowy', 'kadr' ); ?>">
				<button
					type="button"
					class="kadr-switch__option"
					data-wp-on--click="actions.showMonthly"
					data-wp-bind--aria-pressed="state.isMonthly"
					aria-pressed="true"
				><?php esc_html_e( 'Miesięcznie', 'kadr' ); ?></button>
				<button
					type="button"
					class="kadr-switch__option"
					data-wp-on--click="actions.showYearly"
					data-wp-bind--aria-pressed="state.isYearly"
					aria-pressed="false"
				><?php esc_html_e( 'Rocznie — dwa miesiące gratis', 'kadr' ); ?></button>
			</div>
		</div>

		<div class="kadr-pricing__grid">
			<?php foreach ( $kadr_plans as $kadr_key => $kadr_plan ) : ?>
				<article class="kadr-pricing__plan<?php echo $kadr_plan->recommended ? ' kadr-pricing__plan--recommended' : ''; ?>">
					<?php if ( $kadr_plan->recommended ) : ?>
						<p class="kadr-badge kadr-pricing__flag"><?php esc_html_e( 'Najczęściej wybierany', 'kadr' ); ?></p>
					<?php endif; ?>

					<h3 class="kadr-pricing__name"><?php echo esc_html( $kadr_plan->name ); ?></h3>

					<?php if ( $kadr_plan->isFree() ) : ?>
						<p class="kadr-pricing__price kadr-price">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: liczba darmowych projektów */
									_n( '%s projekt gratis', '%s projekty gratis', (int) $kadr_plan->value( 'projects_free' ), 'kadr' ),
									number_format_i18n( (int) $kadr_plan->value( 'projects_free' ) )
								)
							);
							?>
						</p>
						<p class="kadr-pricing__cycle"><?php esc_html_e( 'Bezterminowo, bez karty', 'kadr' ); ?></p>
					<?php else : ?>
						<p class="kadr-pricing__price kadr-price" data-wp-bind--hidden="state.isYearly">
							<?php echo esc_html( Render::price( $kadr_plan->monthly ) ); ?>
							<span class="kadr-pricing__per"><?php esc_html_e( '/ mies.', 'kadr' ); ?></span>
						</p>
						<p class="kadr-pricing__price kadr-price" data-wp-bind--hidden="state.isMonthly" hidden>
							<?php echo esc_html( Render::price( $kadr_plan->yearlyPerMonth() ) ); ?>
							<span class="kadr-pricing__per"><?php esc_html_e( '/ mies.', 'kadr' ); ?></span>
						</p>

						<p class="kadr-pricing__cycle" data-wp-bind--hidden="state.isYearly">
							<?php esc_html_e( 'Rozliczenie miesięczne', 'kadr' ); ?>
						</p>
						<p class="kadr-pricing__cycle" data-wp-bind--hidden="state.isMonthly" hidden>
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: cena roczna, 2: oszczędność w procentach */
									__( '%1$s rocznie — oszczędzasz %2$d%%', 'kadr' ),
									Render::price( $kadr_plan->yearly ),
									$kadr_plan->yearlySavingPercent()
								)
							);
							?>
						</p>
					<?php endif; ?>

					<?php
					echo wp_kses_post(
						Render::cta(
							$kadr_plan->isFree() ? __( 'Zacznij za darmo', 'kadr' ) : __( 'Wybierz plan', 'kadr' ),
							$kadr_cta_url,
							$kadr_plan->recommended ? 'primary' : 'secondary',
							false
						)
					);
					?>
				</article>
			<?php endforeach; ?>
		</div>

		<div class="kadr-pricing__tablewrap">
			<table class="kadr-pricing__table">
				<caption class="kadr-sr-only"><?php esc_html_e( 'Porównanie planów', 'kadr' ); ?></caption>
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Zakres', 'kadr' ); ?></th>
						<?php foreach ( $kadr_plans as $kadr_plan ) : ?>
							<th scope="col"><?php echo esc_html( $kadr_plan->name ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $kadr_rows as $kadr_label => $kadr_reader ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $kadr_label ); ?></th>
							<?php foreach ( $kadr_plans as $kadr_plan ) : ?>
								<td data-kadr-numeric><?php echo esc_html( $kadr_reader( $kadr_plan ) ); ?></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php if ( (bool) ( $attributes['showAddons'] ?? true ) ) : ?>
			<div class="kadr-pricing__addons">
				<h3><?php esc_html_e( 'Dodatki', 'kadr' ); ?></h3>
				<ul class="kadr-pricing__addonlist">
					<?php foreach ( PlanRegistry::addons() as $kadr_addon ) : ?>
						<li>
							<span><?php echo esc_html( $kadr_addon['name'] ); ?></span>
							<span class="kadr-price" data-kadr-numeric>
								<?php
								echo esc_html(
									$kadr_addon['recurring']
										/* translators: %s: kwota */
										? sprintf( __( '%s / mies.', 'kadr' ), Render::price( $kadr_addon['price'] ) )
										/* translators: %s: kwota */
										: sprintf( __( '%s jednorazowo', 'kadr' ), Render::price( $kadr_addon['price'] ) )
								);
								?>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</div>
</section>
