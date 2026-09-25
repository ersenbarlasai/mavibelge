/**
 * Progressive enhancement for the price-options repeater on the
 * mb_ucret edit screen. Without this script, every row rendered by
 * PHP (existing options + a handful of blank filler rows) is already
 * a normal, editable form field, so the field keeps working with
 * JavaScript disabled — this file only adds dynamic add/remove and
 * reveals the buttons that are hidden by default in the PHP markup.
 * The amount field ("amount_try") is a plain TL-formatted text input
 * (e.g. "17.000,00"); server-side conversion to kuruş happens in
 * admin/class-meta-boxes.php::save_price_options().
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

	function nextIndex( container ) {
		var rows = container.querySelectorAll( '.mavibelge-core-price-row' );
		return rows.length;
	}

	function addRow( container ) {
		var tbody = container.querySelector( 'tbody' );
		var index = nextIndex( container );
		var tr = document.createElement( 'tr' );
		tr.className = 'mavibelge-core-price-row';
		tr.innerHTML =
			'<td><input type="text" name="_mb_price_options[' + index + '][label]" class="regular-text"></td>' +
			'<td><input type="text" name="_mb_price_options[' + index + '][units]" class="regular-text"></td>' +
			'<td><input type="text" inputmode="decimal" placeholder="17.000,00" name="_mb_price_options[' + index + '][amount_try]" class="small-text"></td>' +
			'<td><input type="number" step="1" min="0" name="_mb_price_options[' + index + '][sort_order]" value="' + index + '" class="small-text"></td>' +
			'<td><button type="button" class="button-link mavibelge-core-remove-price-row">Kaldır</button></td>';
		tbody.appendChild( tr );
	}

	onReady( function () {
		var containers = document.querySelectorAll( '.mavibelge-core-price-options' );
		containers.forEach( function ( container ) {
			var addButton = container.querySelector( '.mavibelge-core-add-price-row' );
			if ( addButton ) {
				addButton.style.display = '';
				addButton.addEventListener( 'click', function () {
					addRow( container );
				} );
			}

			container.querySelectorAll( '.mavibelge-core-remove-price-row' ).forEach( function ( btn ) {
				btn.style.display = '';
			} );

			container.addEventListener( 'click', function ( event ) {
				if ( event.target && event.target.classList.contains( 'mavibelge-core-remove-price-row' ) ) {
					var row = event.target.closest( '.mavibelge-core-price-row' );
					if ( row ) {
						row.parentNode.removeChild( row );
					}
				}
			} );
		} );
	} );
} )();
