/*
 * Uzman fotografi secici.
 *
 * ID yazdirmak hataya cok acikti: kullanici numarayi yanlis alana ya da
 * yanlis kayda girebiliyordu. Burada WordPress'in kendi ortam kutuphanesi
 * penceresi acilir ve secilen ekin ID'si gizli alana yazilir.
 *
 * Bagimlilik: wp.media (wp_enqueue_media ile yuklenir).
 */
( function ( wp, document ) {
	'use strict';

	if ( ! wp || ! wp.media ) {
		return;
	}

	var frame = null;

	function el( id ) {
		return document.getElementById( id );
	}

	function apply( attachment ) {
		var input   = el( 'dla_mt_photo_id' );
		var preview = el( 'dla-mt-photo-preview' );
		var clear   = el( 'dla-mt-photo-clear' );

		if ( ! input ) {
			return;
		}

		input.value = attachment ? String( attachment.id ) : '';

		if ( preview ) {
			if ( attachment ) {
				// Kucuk boy yoksa tam boya duser; boylece onizleme her ekte calisir.
				var sizes = attachment.sizes || {};
				var src   = ( sizes.thumbnail && sizes.thumbnail.url ) || attachment.url || '';

				preview.innerHTML = '';

				if ( src ) {
					var img = document.createElement( 'img' );
					img.src = src;
					img.alt = '';
					img.style.cssText =
						'width:80px;height:80px;object-fit:cover;border-radius:50%;border:1px solid #dcdcde';
					preview.appendChild( img );
				}
			} else {
				preview.textContent = preview.getAttribute( 'data-empty-label' ) || '';
			}
		}

		if ( clear ) {
			clear.hidden = ! attachment;
		}
	}

	function open() {
		if ( frame ) {
			frame.open();

			return;
		}

		frame = wp.media( {
			title: ( window.dlaMtMedia && window.dlaMtMedia.title ) || '',
			button: { text: ( window.dlaMtMedia && window.dlaMtMedia.button ) || '' },
			library: { type: 'image' },
			multiple: false
		} );

		frame.on( 'select', function () {
			apply( frame.state().get( 'selection' ).first().toJSON() );
		} );

		frame.open();
	}

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;

		if ( ! target || ! target.id ) {
			return;
		}

		if ( 'dla-mt-photo-select' === target.id ) {
			event.preventDefault();
			open();

			return;
		}

		if ( 'dla-mt-photo-clear' === target.id ) {
			event.preventDefault();
			apply( null );
		}
	} );
}( window.wp, document ) );
