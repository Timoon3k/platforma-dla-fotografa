<?php
/**
 * Statyczna część podglądu panelu — powłoka aplikacji.
 *
 * Zwraca HTML powłoki: nawigację, pasek górny i widok „Dzisiaj”.
 * Interaktywne komponenty montuje `preview-app-demo.js`.
 */

declare( strict_types=1 );

return <<<'HTML'
<div class="kadr-app">

	<div class="kadr-app__brand">Kadr</div>

	<header class="kadr-app__top">
		<button type="button" class="kadr-btn kadr-btn--ghost kadr-app__menu" data-demo="menu" aria-label="Otwórz nawigację">☰</button>
		<div class="kadr-search" style="max-width:26rem">
			<span class="kadr-search__icon" aria-hidden="true">⌕</span>
			<input class="kadr-search__input" type="search" placeholder="Szukaj klientów, galerii, zamówień…" aria-label="Szukaj">
		</div>
		<span class="kadr-app__hint">
			<span class="kadr-kbd">⌘</span><span class="kadr-kbd">K</span>
			<span>paleta poleceń</span>
		</span>
	</header>

	<nav class="kadr-app__nav" aria-label="Nawigacja główna">
		<div class="kadr-nav__group">
			<p class="kadr-nav__label">Praca</p>
			<a class="kadr-nav__item" href="#" aria-current="page"><span>Dzisiaj</span></a>
			<a class="kadr-nav__item" href="#"><span>Galerie</span><span class="kadr-nav__count">18</span></a>
			<a class="kadr-nav__item" href="#"><span>Wybory</span><span class="kadr-nav__count kadr-nav__count--accent">3</span></a>
			<a class="kadr-nav__item" href="#"><span>Klienci</span><span class="kadr-nav__count">142</span></a>
			<a class="kadr-nav__item" href="#"><span>Kalendarz</span></a>
		</div>
		<div class="kadr-nav__group">
			<p class="kadr-nav__label">Sprzedaż</p>
			<a class="kadr-nav__item" href="#"><span>Zamówienia</span><span class="kadr-nav__count">7</span></a>
			<a class="kadr-nav__item" href="#"><span>Produkty</span></a>
			<a class="kadr-nav__item" href="#"><span>Analityka</span></a>
		</div>
		<div class="kadr-nav__group">
			<p class="kadr-nav__label">Studio</p>
			<a class="kadr-nav__item" href="#"><span>Automatyzacje</span></a>
			<a class="kadr-nav__item" href="#"><span>Ustawienia</span></a>
			<a class="kadr-nav__item" href="#"><span>Rozliczenia</span></a>
		</div>
	</nav>

	<main class="kadr-app__main" id="tresc">

		<div class="kadr-alert kadr-alert--warning" style="margin-bottom:24px">
			<span><strong>Podgląd.</strong> Dane są przykładowe i służą wyłącznie prezentacji komponentów.
			Panel działa naprawdę: spróbuj <span class="kadr-kbd">⌘</span>/<span class="kadr-kbd">Ctrl</span>
			+ <span class="kadr-kbd">K</span>, posortuj tabelę, otwórz dialog.</span>
		</div>

		<div class="kadr-view__head">
			<div>
				<h1 class="kadr-view__title">Dzisiaj</h1>
				<p class="kadr-view__subtitle">Czwartek, 12 września</p>
			</div>
			<div class="kadr-view__actions">
				<button type="button" class="kadr-btn kadr-btn--secondary" data-demo="toast">Pokaż powiadomienie</button>
				<button type="button" class="kadr-btn kadr-btn--primary" data-demo="new-gallery">Nowa galeria</button>
				<button type="button" class="kadr-btn kadr-btn--secondary" data-demo="drawer">Edytuj w szufladzie</button>
			</div>
		</div>

		<section class="kadr-onboarding" aria-labelledby="onboarding-title">
			<div class="kadr-onboarding__head">
				<h2 id="onboarding-title" style="margin:0;font-size:1rem">Dokończ konfigurację</h2>
				<span class="kadr-onboarding__progress">6 z 10</span>
			</div>
			<ul class="kadr-onboarding__list">
				<li><a class="kadr-onboarding__step kadr-onboarding__step--done" href="#"><span class="kadr-onboarding__mark">✓</span><span class="kadr-onboarding__text">Utwórz profil studia</span></a></li>
				<li><a class="kadr-onboarding__step kadr-onboarding__step--done" href="#"><span class="kadr-onboarding__mark">✓</span><span class="kadr-onboarding__text">Dodaj logo</span></a></li>
				<li><a class="kadr-onboarding__step kadr-onboarding__step--done" href="#"><span class="kadr-onboarding__mark">✓</span><span class="kadr-onboarding__text">Ustaw branding galerii</span></a></li>
				<li><a class="kadr-onboarding__step kadr-onboarding__step--done" href="#"><span class="kadr-onboarding__mark">✓</span><span class="kadr-onboarding__text">Utwórz pierwszą galerię</span></a></li>
				<li><a class="kadr-onboarding__step kadr-onboarding__step--done" href="#"><span class="kadr-onboarding__mark">✓</span><span class="kadr-onboarding__text">Dodaj zdjęcia</span></a></li>
				<li><a class="kadr-onboarding__step kadr-onboarding__step--done" href="#"><span class="kadr-onboarding__mark">✓</span><span class="kadr-onboarding__text">Dodaj klienta</span></a></li>
				<li><a class="kadr-onboarding__step kadr-onboarding__step--next" href="#"><span class="kadr-onboarding__mark"></span><span class="kadr-onboarding__text">Ustaw cenę dodatkowego zdjęcia</span></a></li>
				<li><a class="kadr-onboarding__step" href="#"><span class="kadr-onboarding__mark"></span><span class="kadr-onboarding__text">Dodaj produkty i odbitki</span></a></li>
				<li><a class="kadr-onboarding__step" href="#"><span class="kadr-onboarding__mark"></span><span class="kadr-onboarding__text">Skonfiguruj płatności</span></a></li>
				<li><a class="kadr-onboarding__step" href="#"><span class="kadr-onboarding__mark"></span><span class="kadr-onboarding__text">Wyślij galerię klientowi</span></a></li>
			</ul>
		</section>

		<div class="kadr-tiles">
			<div class="kadr-tile">
				<span class="kadr-tile__label">Nowe wybory</span>
				<span class="kadr-tile__value kadr-tile__value--signal">3</span>
				<span class="kadr-tile__hint">Najstarszy czeka 2 dni</span>
			</div>
			<div class="kadr-tile">
				<span class="kadr-tile__label">Do dopłaty</span>
				<span class="kadr-tile__value">1 240 zł</span>
				<span class="kadr-tile__hint">4 nieopłacone zamówienia</span>
			</div>
			<div class="kadr-tile">
				<span class="kadr-tile__label">Galerie w obróbce</span>
				<span class="kadr-tile__value">5</span>
				<span class="kadr-tile__hint">2 z terminem w tym tygodniu</span>
			</div>
			<div class="kadr-tile">
				<span class="kadr-tile__label">Miejsce na pliki</span>
				<span class="kadr-tile__value kadr-tile__value--warning">184 GB</span>
				<div class="kadr-meter"><div class="kadr-meter__fill kadr-meter__fill--warning" style="width:74%"></div></div>
				<span class="kadr-tile__hint">z 250 GB w planie Studio</span>
			</div>
		</div>

		<div class="kadr-view__head" style="margin-bottom:16px">
			<h2 style="margin:0;font-size:1.125rem">Galerie wymagające uwagi</h2>
			<div class="kadr-view__actions">
				<button type="button" class="kadr-btn kadr-btn--secondary" data-demo="loading">Pokaż ładowanie</button>
				<button type="button" class="kadr-btn kadr-btn--secondary" data-demo="empty">Pokaż pusty stan</button>
				<button type="button" class="kadr-btn kadr-btn--secondary" data-demo="data">Pokaż dane</button>
			</div>
		</div>

		<div id="kadr-table-mount"></div>

		<h2 style="margin:48px 0 16px;font-size:1.125rem">Komponenty</h2>

		<div class="kadr-table__wrap" style="padding:24px">
			<p style="margin-bottom:12px;font-size:13px;color:var(--kadr-ink-subtle)">Znaczniki stanu</p>
			<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:28px">
				<span class="kadr-status kadr-status--draft">Szkic</span>
				<span class="kadr-status kadr-status--published">Opublikowana</span>
				<span class="kadr-status kadr-status--waiting">Czeka na klienta</span>
				<span class="kadr-status kadr-status--accent">Wybór gotowy</span>
				<span class="kadr-status kadr-status--danger">Wygasa jutro</span>
			</div>

			<p style="margin-bottom:12px;font-size:13px;color:var(--kadr-ink-subtle)">Przyciski</p>
			<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:28px">
				<button type="button" class="kadr-btn kadr-btn--primary">Główny</button>
				<button type="button" class="kadr-btn kadr-btn--secondary">Drugorzędny</button>
				<button type="button" class="kadr-btn kadr-btn--ghost">Tekstowy</button>
				<button type="button" class="kadr-btn kadr-btn--danger" data-demo="destructive">Usuń galerię</button>
				<button type="button" class="kadr-btn kadr-btn--primary" disabled>Niedostępny</button>
			</div>

			<p style="margin-bottom:12px;font-size:13px;color:var(--kadr-ink-subtle)">Pole formularza</p>
			<div style="max-width:22rem;margin-bottom:28px">
				<label class="kadr-field">
					<span class="kadr-field__label">Cena dodatkowego zdjęcia</span>
					<input class="kadr-field__input" type="text" value="60 zł">
				</label>
				<label class="kadr-field">
					<span class="kadr-field__label">Adres e-mail klientki</span>
					<input class="kadr-field__input" type="email" value="niepoprawny" aria-invalid="true" aria-describedby="email-error">
					<span class="kadr-field__error" id="email-error">Podaj poprawny adres e-mail.</span>
				</label>
			</div>

			<p style="margin-bottom:12px;font-size:13px;color:var(--kadr-ink-subtle)">Szkielet ładowania</p>
			<div style="max-width:28rem">
				<div class="kadr-skeleton kadr-skeleton--title" style="margin-bottom:12px"></div>
				<div class="kadr-skeleton kadr-skeleton--text"></div>
				<div class="kadr-skeleton kadr-skeleton--text" style="width:80%"></div>
				<div class="kadr-skeleton kadr-skeleton--text" style="width:60%"></div>
			</div>
		</div>

	</main>
</div>
HTML;
