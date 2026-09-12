/**
 * Kadr — warstwa ruchu (ADR-015).
 *
 * Wyłącznie natywne API: IntersectionObserver, Web Animations, właściwości
 * niestandardowe CSS. Zero zależności — GSAP kosztowałby ~70 KB gzip,
 * czyli siedmiokrotność tego pliku, za funkcje, których tu nie potrzebujemy.
 *
 * Kontrakt z CSS: dopiero ten skrypt nadaje dokumentowi klasę `kadr-motion`,
 * która włącza stany początkowe animacji. Bez JavaScriptu żadna treść nie
 * jest ukryta — strona po prostu nie animuje.
 */
( function () {
	'use strict';

	var reduced = window.matchMedia( '(prefers-reduced-motion: reduce)' );

	if ( reduced.matches ) {
		return;
	}

	document.documentElement.classList.add( 'kadr-motion' );

	/* ------------------------------------------------------------------
	 * Wejście sekcji przy scrollu
	 * ---------------------------------------------------------------- */
	function setupReveals() {
		var targets = document.querySelectorAll( '[data-kadr-reveal]' );

		if ( ! targets.length ) {
			return;
		}

		if ( ! ( 'IntersectionObserver' in window ) ) {
			// Starsza przeglądarka: pokazujemy wszystko od razu, zamiast ukrywać.
			Array.prototype.forEach.call( targets, function ( node ) {
				node.classList.add( 'is-revealed' );
			} );
			return;
		}

		var observer = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( ! entry.isIntersecting ) {
						return;
					}
					entry.target.classList.add( 'is-revealed' );
					observer.unobserve( entry.target );
				} );
			},
			{ rootMargin: '0px 0px -12% 0px', threshold: 0.12 }
		);

		// Kaskada liczona w obrębie rodzica, żeby sąsiadujące listy nie
		// dziedziczyły opóźnienia po całej stronie.
		var groups = new Map();

		Array.prototype.forEach.call( targets, function ( node ) {
			var parent = node.parentElement;
			var index = groups.get( parent ) || 0;

			node.style.setProperty( '--kadr-reveal-index', String( Math.min( index, 6 ) ) );
			groups.set( parent, index + 1 );

			observer.observe( node );
		} );
	}

	/* ------------------------------------------------------------------
	 * Liczby animowane przy wejściu w widok
	 *
	 * Wartość docelowa jest w treści elementu — bez JS użytkownik widzi
	 * gotową liczbę, a nie zero.
	 * ---------------------------------------------------------------- */
	function setupCounters() {
		var counters = document.querySelectorAll( '[data-kadr-count]' );

		if ( ! counters.length || ! ( 'IntersectionObserver' in window ) ) {
			return;
		}

		var observer = new IntersectionObserver(
			function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( ! entry.isIntersecting ) {
						return;
					}
					animateCount( entry.target );
					observer.unobserve( entry.target );
				} );
			},
			{ threshold: 0.6 }
		);

		Array.prototype.forEach.call( counters, function ( node ) {
			observer.observe( node );
		} );
	}

	function animateCount( node ) {
		var finalText = node.textContent;
		var match = finalText.match( /-?[\d  ]+/ );

		if ( ! match ) {
			return;
		}

		var target = parseInt( match[ 0 ].replace( /[^\d-]/g, '' ), 10 );

		if ( isNaN( target ) || target === 0 ) {
			return;
		}

		var prefix = finalText.slice( 0, match.index );
		var suffix = finalText.slice( match.index + match[ 0 ].length );
		var started = null;
		var duration = 900;

		function frame( timestamp ) {
			if ( started === null ) {
				started = timestamp;
			}

			var progress = Math.min( ( timestamp - started ) / duration, 1 );
			// Wyhamowanie wykładnicze — ta sama krzywa co --kadr-ease-spring.
			var eased = 1 - Math.pow( 1 - progress, 4 );
			var value = Math.round( target * eased );

			node.textContent = prefix + formatNumber( value ) + suffix;

			if ( progress < 1 ) {
				requestAnimationFrame( frame );
			} else {
				node.textContent = finalText;
			}
		}

		requestAnimationFrame( frame );
	}

	function formatNumber( value ) {
		// Separator tysięcy zgodny z polską konwencją (spacja nierozdzielająca).
		return String( value ).replace( /\B(?=(\d{3})+(?!\d))/g, ' ' );
	}

	/* ------------------------------------------------------------------
	 * Podświetlenie podążające za kursorem
	 * ---------------------------------------------------------------- */
	function setupGlow() {
		var elements = document.querySelectorAll( '.kadr-glow' );

		if ( ! elements.length || ! window.matchMedia( '(hover: hover)' ).matches ) {
			return;
		}

		Array.prototype.forEach.call( elements, function ( node ) {
			node.addEventListener( 'pointermove', function ( event ) {
				var rect = node.getBoundingClientRect();

				node.style.setProperty( '--kadr-px', ( event.clientX - rect.left ) + 'px' );
				node.style.setProperty( '--kadr-py', ( event.clientY - rect.top ) + 'px' );
			} );
		} );
	}

	function init() {
		setupReveals();
		setupCounters();
		setupGlow();
	}

	// Zmiana preferencji w trakcie sesji zatrzymuje ruch natychmiast.
	if ( reduced.addEventListener ) {
		reduced.addEventListener( 'change', function ( event ) {
			if ( event.matches ) {
				document.documentElement.classList.remove( 'kadr-motion' );
			}
		} );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
