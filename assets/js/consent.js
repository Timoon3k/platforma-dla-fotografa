/**
 * Kadr — preferencje cookies.
 *
 * Skrypty wymagające zgody NIE są ładowane i czekające — są wstawiane do
 * dokumentu dopiero po udzieleniu zgody. Do tego czasu leżą na stronie jako
 * <script type="text/plain" data-kadr-consent="analytics">.
 *
 * Publiczne API dla innych modułów:
 *   window.kadrConsent.has( 'analytics' )
 *   window.kadrConsent.onChange( callback )
 *   window.kadrConsent.open()          // ponowne otwarcie ustawień
 */
( function () {
	'use strict';

	var config = window.kadrConsentConfig || { cookie: 'kadr_consent', version: '1', days: 180 };
	var LOCKED = [ 'necessary' ];
	var listeners = [];

	function read() {
		var match = document.cookie.match( new RegExp( '(?:^|; )' + config.cookie + '=([^;]*)' ) );
		if ( ! match ) {
			return null;
		}
		try {
			var parsed = JSON.parse( decodeURIComponent( match[ 1 ] ) );
			if ( ! parsed || parsed.v !== config.version || ! Array.isArray( parsed.c ) ) {
				return null;
			}
			return parsed.c;
		} catch ( e ) {
			return null;
		}
	}

	function write( categories ) {
		var value = encodeURIComponent( JSON.stringify( { v: config.version, c: categories } ) );
		var expires = new Date( Date.now() + config.days * 864e5 ).toUTCString();
		var secure = 'https:' === window.location.protocol ? '; Secure' : '';

		document.cookie = config.cookie + '=' + value + '; Path=/; Expires=' + expires + '; SameSite=Lax' + secure;
	}

	function granted() {
		return read() || LOCKED.slice();
	}

	function has( category ) {
		return granted().indexOf( category ) !== -1;
	}

	/**
	 * Aktywuje skrypty, na które użytkownik właśnie wyraził zgodę.
	 * Element <script type="text/plain"> nie jest wykonywany przez przeglądarkę,
	 * więc do momentu zgody kod nie ma żadnej możliwości uruchomienia się.
	 */
	function activate() {
		var pending = document.querySelectorAll( 'script[type="text/plain"][data-kadr-consent]' );

		Array.prototype.forEach.call( pending, function ( node ) {
			if ( ! has( node.getAttribute( 'data-kadr-consent' ) ) ) {
				return;
			}

			var script = document.createElement( 'script' );

			Array.prototype.forEach.call( node.attributes, function ( attr ) {
				if ( 'type' !== attr.name && 'data-kadr-consent' !== attr.name ) {
					script.setAttribute( attr.name, attr.value );
				}
			} );

			script.text = node.text;
			node.parentNode.replaceChild( script, node );
		} );
	}

	function notify() {
		activate();
		listeners.forEach( function ( fn ) {
			try {
				fn( granted() );
			} catch ( e ) {
				/* Błąd w cudzym callbacku nie może zepsuć strony. */
			}
		} );
	}

	function init() {
		var root = document.getElementById( 'kadr-consent' );

		if ( ! root ) {
			notify();
			return;
		}

		var options = root.querySelector( '[data-kadr-consent-options]' );
		var saveBtn = root.querySelector( '[data-kadr-consent="save"]' );
		var moreBtn = root.querySelector( '[data-kadr-consent="customise"]' );
		var previousFocus = null;

		function close() {
			root.hidden = true;
			if ( previousFocus && previousFocus.focus ) {
				previousFocus.focus();
			}
		}

		function open() {
			previousFocus = document.activeElement;
			root.hidden = false;
			var first = root.querySelector( 'button' );
			if ( first ) {
				first.focus();
			}
		}

		function decide( categories ) {
			write( categories );
			notify();
			close();
		}

		root.addEventListener( 'click', function ( event ) {
			var action = event.target.getAttribute && event.target.getAttribute( 'data-kadr-consent' );

			if ( 'accept' === action ) {
				decide( [ 'necessary', 'preferences', 'analytics', 'marketing' ] );
			} else if ( 'reject' === action ) {
				decide( LOCKED.slice() );
			} else if ( 'customise' === action ) {
				options.hidden = false;
				saveBtn.hidden = false;
				moreBtn.hidden = true;
			} else if ( 'save' === action ) {
				var checked = options.querySelectorAll( 'input[type="checkbox"]:checked' );
				decide(
					Array.prototype.map.call( checked, function ( input ) {
						return input.value;
					} )
				);
			}
		} );

		root.addEventListener( 'keydown', function ( event ) {
			if ( 'Escape' === event.key ) {
				// Esc nie jest zgodą — traktujemy jak odmowę wszystkiego opcjonalnego.
				decide( LOCKED.slice() );
			}
		} );

		window.kadrConsent.open = open;

		if ( null === read() ) {
			open();
		}

		notify();
	}

	window.kadrConsent = {
		has: has,
		granted: granted,
		open: function () {},
		onChange: function ( fn ) {
			if ( 'function' === typeof fn ) {
				listeners.push( fn );
			}
		},
	};

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
