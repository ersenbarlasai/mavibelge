/**
 * Mavi Belge — çok adımlı form (Faz 12f; tanitim-site/online-basvuru.html adım düzeni).
 *
 * İLERLEMELİ İYİLEŞTİRME: sunucu formu JS olmadan eksiksiz çizer (bütün adımlar görünür, gösterge ve ileri/geri
 * düğmeleri `hidden`). Bu betik yalnız `[data-step-form]` formlarında:
 * - göstergeyi gerçek düğmelere açar (etkin adım aria-current="step"), adımları tek tek gösterir;
 * - "Devam Et" yalnız o adımın alanlarını doğrular (checkValidity), "Geri" serbesttir; odağı yeni adımın başlığına taşır;
 * - sunucu doğrulama hatasıyla dönüldüğünde aria-invalid alanın bulunduğu adımı açar ve hata özetine odaklanır;
 *   hata özetindeki bağlantı hedef alanın adımını açar;
 * - prefers-reduced-motion: animasyonlu kaydırma kullanılmaz.
 * Gönderim, güvenlik ve doğrulama tamamen sunucudadır; bu betik hiçbir veri göndermez/saklamaz.
 */
( function () {
	'use strict';

	var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	function initStepForm( form ) {
		var steps = Array.prototype.slice.call( form.querySelectorAll( '[data-step]' ) );
		if ( steps.length < 2 ) {
			return;
		}
		var card = form.closest( '.form-card' ) || form.parentNode;
		var indicator = card.querySelector( '[data-step-indicator]' );
		var tabs = indicator ? Array.prototype.slice.call( indicator.querySelectorAll( '[data-step-goto]' ) ) : [];
		var submit = form.querySelector( '[data-step-submit]' );
		var current = 0;

		form.classList.add( 'is-stepped' );
		if ( indicator ) {
			indicator.hidden = false;
		}
		Array.prototype.forEach.call( form.querySelectorAll( '[data-step-actions]' ), function ( actions ) {
			actions.hidden = false;
		} );

		function focusTitle( step ) {
			var title = step.querySelector( '.form-step-title' );
			if ( ! title ) {
				return;
			}
			title.setAttribute( 'tabindex', '-1' );
			title.focus( { preventScroll: true } );
			step.scrollIntoView( { block: 'nearest', behavior: reduceMotion ? 'auto' : 'smooth' } );
		}

		function show( index, moveFocus ) {
			current = Math.max( 0, Math.min( index, steps.length - 1 ) );
			steps.forEach( function ( step, i ) {
				step.hidden = i !== current;
			} );
			tabs.forEach( function ( tab, i ) {
				if ( i === current ) {
					tab.setAttribute( 'aria-current', 'step' );
				} else {
					tab.removeAttribute( 'aria-current' );
				}
				tab.parentNode.classList.toggle( 'is-active', i === current );
				tab.parentNode.classList.toggle( 'is-done', i < current );
			} );
			if ( submit ) {
				submit.hidden = current !== steps.length - 1;
			}
			if ( moveFocus ) {
				focusTitle( steps[ current ] );
			}
		}

		function stepValid( step ) {
			var fields = Array.prototype.slice.call( step.querySelectorAll( 'input, select, textarea' ) );
			for ( var i = 0; i < fields.length; i++ ) {
				if ( fields[ i ].willValidate && ! fields[ i ].checkValidity() ) {
					fields[ i ].focus();
					if ( 'function' === typeof fields[ i ].reportValidity ) {
						fields[ i ].reportValidity();
					}
					return false;
				}
			}
			return true;
		}

		function stepOf( element ) {
			var step = element ? element.closest( '[data-step]' ) : null;
			return step ? steps.indexOf( step ) : -1;
		}

		form.addEventListener( 'click', function ( e ) {
			var next = e.target.closest( '[data-step-next]' );
			var prev = e.target.closest( '[data-step-prev]' );
			if ( next ) {
				e.preventDefault();
				if ( stepValid( steps[ current ] ) ) {
					show( current + 1, true );
				}
			} else if ( prev ) {
				e.preventDefault();
				show( current - 1, true );
			}
		} );

		tabs.forEach( function ( tab, i ) {
			tab.addEventListener( 'click', function () {
				// Geri serbest; ileri yalnız aradaki adımlar geçerliyse.
				for ( var k = current; k < i; k++ ) {
					if ( ! stepValid( steps[ k ] ) ) {
						show( k, false );
						return;
					}
				}
				show( i, true );
			} );
		} );

		// Hata özetindeki bağlantı: hedef alanın adımını aç, sonra alana odaklan.
		var summary = card.querySelector( '.form-error-summary' );
		if ( summary ) {
			summary.addEventListener( 'click', function ( e ) {
				var link = e.target.closest( 'a[href^="#"]' );
				var target = link ? document.getElementById( link.getAttribute( 'href' ).slice( 1 ) ) : null;
				var index = stepOf( target );
				if ( index >= 0 ) {
					e.preventDefault();
					show( index, false );
					target.focus();
				}
			} );
		}

		var invalid = form.querySelector( '[aria-invalid="true"]' );
		var start = stepOf( invalid );
		show( start >= 0 ? start : 0, false );
		if ( summary && start >= 0 ) {
			summary.focus();
		}
	}

	function init() {
		Array.prototype.forEach.call( document.querySelectorAll( 'form[data-step-form]' ), initStepForm );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
