<?php
/**
 * Katalog komponentów panelu.
 *
 * Każdy komponent w komplecie stanów, na jednej stronie. Służy dwóm rzeczom:
 * jest referencją przy budowaniu kolejnych widoków i miejscem, w którym widać
 * regresję wizualną, zanim trafi do panelu.
 *
 * Dane są jawnie demonstracyjne (CLAUDE.md §9).
 *
 * Użycie:  php tools/preview-components.php  →  dist/preview/components.html
 */

declare( strict_types=1 );

$root = dirname( __DIR__ );

require_once "$root/tools/preview-build.php";

kadr_preview_wp_stubs( $root );

$body = <<<'HTML'
<main class="kadr-app__main kadr-catalogue" id="tresc">

	<div class="kadr-alert kadr-alert--warning" style="margin-bottom:24px">
		<span><strong>Katalog komponentów.</strong> Dane są przykładowe i służą wyłącznie prezentacji.
		Komponenty są prawdziwe — te same trafiają do wtyczki.</span>
	</div>

	<h1 class="kadr-view__title" style="margin-bottom:32px">Komponenty panelu</h1>

	<section class="kadr-panel">
		<h2 class="kadr-panel__title">Akcje i powiadomienia</h2>
		<div style="display:flex;flex-wrap:wrap;gap:8px">
			<button type="button" class="kadr-btn kadr-btn--secondary" data-demo="toast">Powiadomienie z cofnięciem</button>
			<button type="button" class="kadr-btn kadr-btn--primary" data-demo="dialog">Dialog potwierdzenia</button>
			<button type="button" class="kadr-btn kadr-btn--danger" data-demo="destructive">Operacja nieodwracalna</button>
			<button type="button" class="kadr-btn kadr-btn--secondary" data-demo="drawer">Szuflada z formularzem</button>
		</div>
	</section>

	<section class="kadr-panel">
		<h2 class="kadr-panel__title">Tabela danych</h2>
		<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px">
			<button type="button" class="kadr-btn kadr-btn--secondary" data-demo="loading">Stan ładowania</button>
			<button type="button" class="kadr-btn kadr-btn--secondary" data-demo="empty">Stan pusty</button>
			<button type="button" class="kadr-btn kadr-btn--secondary" data-demo="data">Dane</button>
		</div>
		<div id="kadr-table-mount"></div>
	</section>

	<section class="kadr-panel">
		<h2 class="kadr-panel__title">Znaczniki stanu</h2>
		<div style="display:flex;flex-wrap:wrap;gap:8px">
			<span class="kadr-status kadr-status--draft">Szkic</span>
			<span class="kadr-status kadr-status--published">Opublikowana</span>
			<span class="kadr-status kadr-status--waiting">Czeka na klienta</span>
			<span class="kadr-status kadr-status--accent">Wybór gotowy</span>
			<span class="kadr-status kadr-status--warning">Wygasa jutro</span>
			<span class="kadr-status kadr-status--danger">Wygasła</span>
		</div>
	</section>

	<section class="kadr-panel">
		<h2 class="kadr-panel__title">Przyciski</h2>
		<div style="display:flex;flex-wrap:wrap;gap:8px">
			<button type="button" class="kadr-btn kadr-btn--primary">Główny</button>
			<button type="button" class="kadr-btn kadr-btn--secondary">Drugorzędny</button>
			<button type="button" class="kadr-btn kadr-btn--ghost">Tekstowy</button>
			<button type="button" class="kadr-btn kadr-btn--danger">Nieodwracalny</button>
			<button type="button" class="kadr-btn kadr-btn--primary" disabled>Niedostępny</button>
		</div>
	</section>

	<section class="kadr-panel">
		<h2 class="kadr-panel__title">Kafle i wskaźnik</h2>
		<div class="kadr-tiles">
			<div class="kadr-tile">
				<span class="kadr-tile__label">Wartość neutralna</span>
				<span class="kadr-tile__value">142</span>
				<span class="kadr-tile__hint">Podpis wyjaśniający</span>
			</div>
			<div class="kadr-tile">
				<span class="kadr-tile__label">Wartość istotna</span>
				<span class="kadr-tile__value kadr-tile__value--signal">3</span>
			</div>
			<div class="kadr-tile">
				<span class="kadr-tile__label">Wartość ostrzegawcza</span>
				<span class="kadr-tile__value kadr-tile__value--warning">184 GB</span>
				<div class="kadr-meter"><div class="kadr-meter__fill kadr-meter__fill--warning" style="width:74%"></div></div>
				<span class="kadr-tile__hint">z 250 GB w planie</span>
			</div>
		</div>
	</section>

	<section class="kadr-panel">
		<h2 class="kadr-panel__title">Szkielety ładowania</h2>
		<div style="max-width:28rem">
			<div class="kadr-skeleton kadr-skeleton--title" style="margin-bottom:12px"></div>
			<div class="kadr-skeleton kadr-skeleton--text"></div>
			<div class="kadr-skeleton kadr-skeleton--text" style="width:80%"></div>
			<div class="kadr-skeleton kadr-skeleton--text" style="width:60%"></div>
		</div>
	</section>

</main>
HTML;

kadr_preview_build(
	$root,
	'Kadr — katalog komponentów',
	$body,
	kadr_preview_modules(),
	array( 'demo.js' => file_get_contents( "$root/tools/preview-components-demo.js" ) ),
	'demo.js',
	"$root/dist/preview/components.html"
);
