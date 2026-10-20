/**
 * Progressive enhancement for the mb_ucret edit screen. Without this script every row rendered by PHP (existing options +
 * a handful of blank filler rows) is already a normal, editable form field and the selects work as plain <select>s, so
 * the screen keeps working with JavaScript disabled. This file only adds:
 *  - add / remove / move up / move down for price-option rows (new rows are CLONED from a server-rendered <template>;
 *    no innerHTML), with sort_order renumbered to match the visible order;
 *  - a live "Kaydedilecek" preview of how a TL amount will be read (display only — the server's
 *    MaviBelge_Core_Validator::try_lira_to_kurus() stays the single authority and rejects anything invalid);
 *  - a search box that narrows the qualification <select> by name / MYK code / level, and an informational hint when the
 *    selected qualification's MYK code differs from the "Kaynak MYK Kodu" field (that field is NEVER changed automatically).
 */
( function () {
	'use strict';

	function onReady( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	/* ---------------- TL girişi önizlemesi (sunucu kuralının görüntü amaçlı eşi) ---------------- */

	function groupThousands( digits ) {
		var out = '';
		var count = 0;
		for ( var i = digits.length - 1; i >= 0; i-- ) {
			out = digits.charAt( i ) + out;
			count++;
			if ( count % 3 === 0 && i > 0 ) {
				out = '.' + out;
			}
		}
		return out;
	}

	/** @return {string|null} "17.000,00 TL" or null when the server would reject the input. */
	function previewAmount( raw ) {
		var value = String( raw || '' ).trim();
		var lira = null;
		var kurus = '00';
		var m;
		if ( value === '' ) {
			return '';
		}
		if ( ( m = /^([0-9]{1,3}(?:[.][0-9]{3})*),([0-9]{2})$/.exec( value ) ) ) {
			lira = m[ 1 ].split( '.' ).join( '' );
			kurus = m[ 2 ];
		} else if ( ( m = /^([0-9]{1,3}(?:[.][0-9]{3})*)$/.exec( value ) ) ) {
			lira = m[ 1 ].split( '.' ).join( '' );
		} else if ( ( m = /^([0-9]+),([0-9]{2})$/.exec( value ) ) ) {
			lira = m[ 1 ];
			kurus = m[ 2 ];
		} else if ( ( m = /^([0-9]+)$/.exec( value ) ) ) {
			lira = m[ 1 ];
		} else {
			return null;
		}
		lira = lira.replace( /^0+(?=[0-9])/, '' );
		if ( lira.length > 15 ) {
			return null;
		}
		if ( /^0*$/.test( lira ) && kurus === '00' ) {
			return null; // Tutar sıfır olamaz.
		}
		return groupThousands( lira ) + ',' + kurus + ' TL';
	}

	function refreshPreview( row ) {
		var input = row ? row.querySelector( '.mb-amount-input' ) : null;
		var out = row ? row.querySelector( '.mb-amount-preview' ) : null;
		if ( ! input || ! out ) {
			return;
		}
		var text = previewAmount( input.value );
		if ( text === '' ) {
			out.textContent = '—';
			out.className = 'mb-amount-preview';
		} else if ( text === null ) {
			out.textContent = 'Geçersiz biçim (örn. 17.000 veya 17.000,00)';
			out.className = 'mb-amount-preview mb-amount-invalid';
		} else {
			out.textContent = text;
			out.className = 'mb-amount-preview';
		}
	}

	/* ---------------- fiyat seçeneği satırları ---------------- */

	function rows( container ) {
		return Array.prototype.slice.call( container.querySelectorAll( 'tbody .mavibelge-core-price-row' ) );
	}

	/** Bir sonraki benzersiz satır indeksi: mevcut en büyük indeks + 1 (silinen satırdan sonra ad çakışması olmaz). */
	function nextIndex( container ) {
		var max = -1;
		var prefix = '_mb_price_options[';
		rows( container ).forEach( function ( row ) {
			var any = row.querySelector( 'input[name^="_mb_price_options["]' );
			var name = any ? any.getAttribute( 'name' ) : '';
			var digits = name.slice( prefix.length, name.indexOf( ']', prefix.length ) );
			if ( /^[0-9]+$/.test( digits ) && Number( digits ) > max ) {
				max = Number( digits );
			}
		} );
		return max + 1;
	}

	function renumber( container ) {
		rows( container ).forEach( function ( row, position ) {
			var sort = row.querySelector( '.mb-sort-input' );
			if ( sort ) {
				sort.value = String( position );
			}
		} );
	}

	function revealRowButtons( row ) {
		Array.prototype.forEach.call( row.querySelectorAll( '.mavibelge-core-remove-price-row, .mavibelge-core-move-up, .mavibelge-core-move-down' ), function ( btn ) {
			btn.style.display = '';
		} );
	}

	function addRow( container ) {
		var template = container.querySelector( 'template.mavibelge-core-price-row-template' );
		var tbody = container.querySelector( 'tbody' );
		if ( ! template || ! tbody || ! template.content ) {
			return;
		}
		var index = String( nextIndex( container ) );
		var fragment = template.content.cloneNode( true );
		Array.prototype.forEach.call( fragment.querySelectorAll( '[name]' ), function ( field ) {
			field.setAttribute( 'name', field.getAttribute( 'name' ).split( '__INDEX__' ).join( index ) );
		} );
		var row = fragment.querySelector( '.mavibelge-core-price-row' );
		tbody.appendChild( fragment );
		if ( row ) {
			revealRowButtons( row );
			renumber( container );
			var label = row.querySelector( '.mb-label-input' );
			if ( label ) {
				label.focus();
			}
		}
	}

	function moveRow( container, row, direction ) {
		var sibling = direction < 0 ? row.previousElementSibling : row.nextElementSibling;
		if ( ! sibling ) {
			return;
		}
		if ( direction < 0 ) {
			row.parentNode.insertBefore( row, sibling );
		} else {
			row.parentNode.insertBefore( sibling, row );
		}
		renumber( container );
		var button = row.querySelector( direction < 0 ? '.mavibelge-core-move-up' : '.mavibelge-core-move-down' );
		if ( button ) {
			button.focus();
		}
	}

	function initPriceOptions( container ) {
		var addButton = container.querySelector( '.mavibelge-core-add-price-row' );
		if ( addButton ) {
			addButton.style.display = '';
			addButton.addEventListener( 'click', function () {
				addRow( container );
			} );
		}
		rows( container ).forEach( function ( row ) {
			revealRowButtons( row );
			refreshPreview( row );
		} );
		container.addEventListener( 'click', function ( event ) {
			var target = event.target;
			var row = target && target.closest ? target.closest( '.mavibelge-core-price-row' ) : null;
			if ( ! row ) {
				return;
			}
			if ( target.classList.contains( 'mavibelge-core-remove-price-row' ) ) {
				row.parentNode.removeChild( row );
				renumber( container );
			} else if ( target.classList.contains( 'mavibelge-core-move-up' ) ) {
				moveRow( container, row, -1 );
			} else if ( target.classList.contains( 'mavibelge-core-move-down' ) ) {
				moveRow( container, row, 1 );
			}
		} );
		container.addEventListener( 'input', function ( event ) {
			var target = event.target;
			if ( target && target.classList && target.classList.contains( 'mb-amount-input' ) ) {
				refreshPreview( target.closest( '.mavibelge-core-price-row' ) );
			}
		} );
	}

	/* ---------------- aranabilir yeterlilik seçimi ---------------- */

	function fold( text ) {
		return String( text || '' ).toLocaleLowerCase( 'tr-TR' );
	}

	function updateMykHint( select ) {
		var hint = select.parentNode.querySelector( '.mavibelge-core-myk-hint' );
		var source = document.getElementById( '_mb_qualification_code' );
		if ( ! hint ) {
			return;
		}
		var option = select.options[ select.selectedIndex ];
		var code = option ? option.getAttribute( 'data-myk-code' ) || '' : '';
		var sourceCode = source ? source.value.trim() : '';
		if ( ! option || option.value === '0' ) {
			hint.textContent = 'Bağlantısız: ücret listede gösterilir, detay bağlantısı üretilmez.';
		} else if ( code !== '' && sourceCode !== '' && code !== sourceCode ) {
			hint.textContent = 'Seçilen yeterliliğin MYK kodu ' + code + '; Kaynak MYK Kodu alanı (' + sourceCode + ') farklı. Kaynak kodu otomatik değiştirilmez — kontrol edin.';
		} else {
			hint.textContent = code !== '' ? 'Seçilen yeterliliğin MYK kodu: ' + code : '';
		}
	}

	function initQualificationSelect( filter ) {
		var select = document.getElementById( filter.getAttribute( 'data-filter-target' ) );
		if ( ! select ) {
			return;
		}
		var all = Array.prototype.slice.call( select.options );
		filter.style.display = '';
		filter.addEventListener( 'input', function () {
			var needle = fold( filter.value ).trim();
			var selected = select.value;
			while ( select.firstChild ) {
				select.removeChild( select.firstChild );
			}
			all.forEach( function ( option ) {
				if ( needle === '' || option.value === '0' || option.value === selected || fold( option.textContent ).indexOf( needle ) !== -1 ) {
					select.appendChild( option );
				}
			} );
			select.value = selected;
		} );
		select.addEventListener( 'change', function () {
			updateMykHint( select );
		} );
		var source = document.getElementById( '_mb_qualification_code' );
		if ( source ) {
			source.addEventListener( 'input', function () {
				updateMykHint( select );
			} );
		}
		updateMykHint( select );
	}

	onReady( function () {
		Array.prototype.forEach.call( document.querySelectorAll( '.mavibelge-core-price-options' ), initPriceOptions );
		Array.prototype.forEach.call( document.querySelectorAll( '.mavibelge-core-select-filter' ), initQualificationSelect );
	} );
} )();
