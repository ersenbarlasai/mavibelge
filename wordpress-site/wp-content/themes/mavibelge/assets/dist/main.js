/*
 * Mavi Belge tema — üretime hazır, tek dosya JS paketi.
 * Node/derleme aracı GEREKTİRMEZ; src/js/*.js dosyalarının basit
 * sırayla birleştirilmesiyle üretilmiştir (navigation, components, main).
 * Senkronizasyon yöntemi: docs/design-system.md.
 */

/**
 * Mavi Belge — desktop dropdown menu + mobile hamburger menu.
 *
 * Ported and behavior-preserved from tanitim-site/assets/js/navigation.js
 * (submenu open/close, outside-click, Escape, mobile toggle, active-page
 * marking), extended per Faz 3 brief §8/§10 with:
 * - keyboard arrow navigation inside an open submenu,
 * - focus management + body scroll lock for the mobile menu (focus
 *   moves into the menu on open, returns to the toggle button on close),
 * - reduced-motion awareness is handled in components.js (back-to-top
 *   scroll), not here.
 *
 * Vanilla JS, no dependencies. Safe with multiple menu instances (all
 * selectors are scoped per-element, nothing assumes a single global).
 */
( function () {
	'use strict';

	var BODY_LOCK_CLASS = 'mavibelge-nav-open';

	function closeAllSubmenus( except ) {
		document.querySelectorAll( '.main-nav li.is-open' ).forEach( function ( li ) {
			if ( li !== except ) {
				li.classList.remove( 'is-open' );
				var t = li.querySelector( ':scope > .nav-toggle' );
				if ( t ) {
					t.setAttribute( 'aria-expanded', 'false' );
				}
			}
		} );
	}

	function submenuLinks( li ) {
		var submenu = li.querySelector( ':scope > .submenu' );
		if ( ! submenu ) {
			return [];
		}
		return Array.prototype.slice.call( submenu.querySelectorAll( 'a' ) );
	}

	function initDropdowns() {
		document.querySelectorAll( '.main-nav .nav-toggle' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var li = btn.closest( 'li' );
				var isOpen = li.classList.contains( 'is-open' );
				closeAllSubmenus( isOpen ? null : li );
				li.classList.toggle( 'is-open', ! isOpen );
				btn.setAttribute( 'aria-expanded', String( ! isOpen ) );
			} );

			btn.addEventListener( 'keydown', function ( e ) {
				var li = btn.closest( 'li' );
				if ( e.key === 'ArrowDown' || e.key === 'Down' ) {
					e.preventDefault();
					li.classList.add( 'is-open' );
					btn.setAttribute( 'aria-expanded', 'true' );
					var links = submenuLinks( li );
					if ( links.length ) {
						links[ 0 ].focus();
					}
				}
			} );
		} );

		// Arrow/Escape navigation once inside an open submenu.
		document.querySelectorAll( '.main-nav .submenu' ).forEach( function ( submenu ) {
			submenu.addEventListener( 'keydown', function ( e ) {
				var links = Array.prototype.slice.call( submenu.querySelectorAll( 'a' ) );
				var currentIndex = links.indexOf( document.activeElement );

				if ( e.key === 'ArrowDown' || e.key === 'Down' ) {
					e.preventDefault();
					var next = links[ currentIndex + 1 ] || links[ 0 ];
					next.focus();
				} else if ( e.key === 'ArrowUp' || e.key === 'Up' ) {
					e.preventDefault();
					var prev = links[ currentIndex - 1 ] || links[ links.length - 1 ];
					prev.focus();
				}
				// Escape is intentionally NOT handled here — a single
				// authoritative document-level Escape listener lives in
				// initMobileMenu() so a submenu close and a mobile-menu
				// close can never race on the same keypress (see its
				// comment for the full precedence rule).
			} );
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( ! e.target.closest( '.main-nav' ) ) {
				closeAllSubmenus( null );
			}
		} );
	}

	function getFocusable( container ) {
		return Array.prototype.slice.call(
			container.querySelectorAll( 'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])' )
		);
	}

	/**
	 * Pure decision function for the mobile-menu focus trap: given who is
	 * currently focused, whether Shift is held, and the three boundary
	 * elements (toggle, first nav item, last nav item), returns the
	 * element that should receive focus, or null if this keypress is not
	 * a boundary crossing (normal browser Tab handling applies instead).
	 * Kept side-effect-free and separate from the keydown listener so it
	 * can be exercised directly by tests/js/mobile-focus-trap.test.js
	 * without duplicating the logic there.
	 */
	function resolveTrapFocusTarget( active, shiftKey, toggle, firstItem, lastItem ) {
		if ( ! shiftKey ) {
			if ( active === toggle ) {
				return firstItem;
			}
			if ( active === lastItem ) {
				return toggle;
			}
		} else {
			if ( active === toggle ) {
				return lastItem;
			}
			if ( active === firstItem ) {
				return toggle;
			}
		}
		return null;
	}

	var MOBILE_MQ = '(max-width: 768px)';
	var TOGGLE_LABEL_OPEN  = 'Menüyü aç';
	var TOGGLE_LABEL_CLOSE = 'Menüyü kapat';

	function initMobileMenu() {
		var toggle = document.querySelector( '.menu-toggle' );
		var nav = document.getElementById( 'main-nav' );
		if ( ! toggle || ! nav ) {
			return;
		}

		function closeMobileMenu( returnFocus ) {
			nav.classList.remove( 'is-open' );
			toggle.setAttribute( 'aria-expanded', 'false' );
			toggle.setAttribute( 'aria-label', TOGGLE_LABEL_OPEN );
			document.body.classList.remove( BODY_LOCK_CLASS );
			closeAllSubmenus( null );
			if ( returnFocus ) {
				toggle.focus();
			}
		}

		function openMobileMenu() {
			nav.classList.add( 'is-open' );
			toggle.setAttribute( 'aria-expanded', 'true' );
			toggle.setAttribute( 'aria-label', TOGGLE_LABEL_CLOSE );
			document.body.classList.add( BODY_LOCK_CLASS );
			var focusable = getFocusable( nav );
			if ( focusable.length ) {
				focusable[ 0 ].focus();
			}
		}

		toggle.addEventListener( 'click', function () {
			var isOpen = nav.classList.contains( 'is-open' );
			if ( isOpen ) {
				closeMobileMenu( false );
			} else {
				openMobileMenu();
			}
		} );

		// Single authoritative Escape listener for the whole nav (desktop
		// dropdowns, mobile submenus, and the mobile menu itself). This is
		// the ONLY document-level Escape handler in this file — earlier a
		// second listener in initDropdowns() raced with this one (both read
		// "is a submenu open" independently, and whichever ran first closed
		// it out from under the other), so first Escape closed a submenu
		// AND the mobile menu in the same keypress. Precedence, evaluated
		// once per keypress:
		//   1. an open submenu exists -> close ONLY it, focus its toggle,
		//      leave the mobile menu open;
		//   2. no open submenu but the mobile menu is open -> close it,
		//      restore body scroll, focus the hamburger toggle.
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key !== 'Escape' ) {
				return;
			}
			var openLi = document.querySelector( '.main-nav li.is-open' );
			if ( openLi ) {
				var openToggle = openLi.querySelector( ':scope > .nav-toggle' );
				closeAllSubmenus( null );
				if ( openToggle ) {
					openToggle.focus();
				}
				return;
			}
			if ( nav.classList.contains( 'is-open' ) ) {
				closeMobileMenu( true );
			}
		} );

		// Focus trap while the mobile menu is open. The logical loop is
		// toggle -> first nav item -> ... -> last nav item -> (back to)
		// toggle, in BOTH directions — but the toggle button is a real DOM
		// sibling of <nav id="main-nav">, positioned AFTER it (see
		// header.php), so the browser's own natural Tab order does not
		// match this loop at either end. Both ends are therefore handled
		// explicitly here rather than relying on natural order:
		//   - Tab on toggle           -> first nav item   (not natural: toggle is last in DOM)
		//   - Tab on last nav item    -> toggle            (not natural: toggle is last in DOM)
		//   - Shift+Tab on first item -> toggle            (not natural: toggle is last in DOM)
		//   - Shift+Tab on toggle     -> last nav item     (wrap)
		// Movement between nav items themselves is left to the browser —
		// those elements ARE contiguous in real DOM order.
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key !== 'Tab' || ! nav.classList.contains( 'is-open' ) ) {
				return;
			}
			var focusable = getFocusable( nav );
			if ( ! focusable.length ) {
				return;
			}
			var target = resolveTrapFocusTarget(
				document.activeElement,
				e.shiftKey,
				toggle,
				focusable[ 0 ],
				focusable[ focusable.length - 1 ]
			);
			if ( target ) {
				e.preventDefault();
				target.focus();
			}
		} );

		// If the viewport grows past the mobile breakpoint while the
		// mobile menu is open, .main-nav becomes visible-by-default via
		// CSS and the toggle is hidden — clear all mobile-only state so
		// nothing is left stuck (is-open, aria-expanded, body scroll lock).
		if ( 'function' === typeof window.matchMedia ) {
			var mq = window.matchMedia( MOBILE_MQ );
			var onMqChange = function ( event ) {
				if ( ! event.matches ) {
					closeMobileMenu( false );
				}
			};
			if ( 'function' === typeof mq.addEventListener ) {
				mq.addEventListener( 'change', onMqChange );
			} else if ( 'function' === typeof mq.addListener ) {
				// Safari < 14 fallback.
				mq.addListener( onMqChange );
			}
		}
	}

	function normalizePath( pathname ) {
		if ( pathname.length > 1 && '/' === pathname.charAt( pathname.length - 1 ) ) {
			return pathname.slice( 0, -1 );
		}
		return pathname;
	}

	/**
	 * Client-side "is this link the current page" marker, for links that
	 * WordPress itself does not already mark (e.g. .side-nav links built
	 * outside wp_nav_menu(), or the fallback menu before a real menu is
	 * assigned). It intentionally does NOT touch a link that already
	 * carries WordPress's own server-rendered current-menu-item/
	 * aria-current="page" (added by wp_nav_menu() + this theme's
	 * MaviBelge_Nav_Walker) — server state wins, this only fills gaps.
	 */
	function markActivePage() {
		var currentPath = normalizePath( location.pathname );
		document.querySelectorAll( '.main-nav a, .side-nav a' ).forEach( function ( a ) {
			if ( a.classList.contains( 'current-menu-item' ) || 'page' === a.getAttribute( 'aria-current' ) ) {
				return;
			}
			var href = a.getAttribute( 'href' );
			if ( ! href ) {
				return;
			}
			var linkPath;
			try {
				linkPath = normalizePath( new URL( href, location.href ).pathname );
			} catch ( err ) {
				return;
			}
			if ( linkPath === currentPath ) {
				a.classList.add( 'is-active' );
				a.setAttribute( 'aria-current', 'page' );
			}
		} );
	}

	function onReady( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	onReady( function () {
		initDropdowns();
		initMobileMenu();
		markActivePage();
	} );

	// Test-only hook: exposes pure, side-effect-free functions so
	// tests/js/mobile-focus-trap.test.js and nav-active-page.test.js can
	// exercise the REAL shipped logic (via a minimal Node `document`/
	// `window` stub, see that file) instead of a re-typed copy. No
	// production behavior depends on this; safe no-op if `window` is a
	// real browser global.
	if ( 'object' === typeof window && window ) {
		window.__mavibelgeNavTestHooks__ = {
			resolveTrapFocusTarget: resolveTrapFocusTarget,
			normalizePath: normalizePath
		};
	}
} )();

/**
 * Mavi Belge — global component behaviors: back-to-top and the
 * footer's auto-updating copyright year.
 *
 * Ported from tanitim-site/assets/js/components.js. The accordion
 * trigger behavior from that file is NOT carried over here — no
 * accordion component exists in the Faz 3 shell/catalog (SSS content
 * is a later phase); re-added when that component is actually built.
 *
 * New in this phase: reduced-motion awareness for the back-to-top
 * smooth scroll (Faz 3 brief §8 — "Reduced-motion tercihinde yumuşak
 * kaydırma/animasyon kapansın").
 */
( function () {
	'use strict';

	function prefersReducedMotion() {
		return window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	}

	function initBackToTop() {
		var backToTop = document.querySelector( '.back-to-top' );
		if ( ! backToTop ) {
			return;
		}
		window.addEventListener( 'scroll', function () {
			backToTop.classList.toggle( 'is-visible', window.scrollY > 480 );
		} );
		backToTop.addEventListener( 'click', function () {
			window.scrollTo( {
				top: 0,
				behavior: prefersReducedMotion() ? 'auto' : 'smooth'
			} );
		} );
	}

	function initYear() {
		document.querySelectorAll( '[data-year]' ).forEach( function ( el ) {
			el.textContent = String( new Date().getFullYear() );
		} );
	}

	function onReady( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	onReady( function () {
		initBackToTop();
		initYear();
	} );
} )();

/**
 * Mavi Belge — entry point note.
 *
 * Deliberately empty, same convention as
 * tanitim-site/assets/js/main.js: page/behavior scripts are kept
 * modular (navigation.js, components.js today; search/filters/forms
 * scripts arrive in later phases) and dist/main.js concatenates only
 * what this phase needs.
 */
