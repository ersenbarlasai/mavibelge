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
