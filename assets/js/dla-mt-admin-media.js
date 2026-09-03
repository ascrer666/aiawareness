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

/*
 * Aramali icerik secici.
 *
 * Sabit <select> hem kapsam disi turleri (portfolio, hizmet) gizliyordu
 * hem de 300 kayittan sonrasini erisilemez kiliyordu. Burada yazdikca
 * sunucuda aranir; tur kisiti yoktur.
 */
( function ( document ) {
	'use strict';

	var cfg = window.dlaMtPostSearch;

	if ( ! cfg || ! cfg.ajaxUrl ) {
		return;
	}

	function setup( box ) {
		var hidden  = document.getElementById( box.getAttribute( 'data-target' ) );
		var input   = box.querySelector( '.dla-mt-postsearch__input' );
		var list    = box.querySelector( '.dla-mt-postsearch__results' );
		var current = box.querySelector( '.dla-mt-postsearch__current' );
		var clear   = box.querySelector( '.dla-mt-postsearch__clear' );
		var timer   = null;
		var seq     = 0;

		if ( ! hidden || ! input || ! list ) {
			return;
		}

		function message( text ) {
			list.innerHTML = '';
			var li = document.createElement( 'li' );
			li.className = 'dla-mt-postsearch__empty';
			li.textContent = text;
			list.appendChild( li );
			list.hidden = false;
		}

		function choose( id, label ) {
			hidden.value = String( id );

			if ( current ) {
				var span = current.querySelector( 'span' );

				if ( span ) {
					span.textContent = label;
				}

				current.hidden = false;
			}

			input.value = '';
			list.hidden = true;
			list.innerHTML = '';
		}

		function render( items ) {
			list.innerHTML = '';

			if ( ! items.length ) {
				message( cfg.noResults );

				return;
			}

			items.forEach( function ( item ) {
				var li = document.createElement( 'li' );
				var btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'button-link';
				btn.textContent = item.label;
				btn.addEventListener( 'click', function () {
					choose( item.id, item.label );
				} );
				li.appendChild( btn );
				list.appendChild( li );
			} );

			list.hidden = false;
		}

		function search( term ) {
			var mine = ++seq;
			var url = cfg.ajaxUrl
				+ '?action=' + encodeURIComponent( cfg.action )
				+ '&nonce=' + encodeURIComponent( cfg.nonce )
				+ '&term=' + encodeURIComponent( term );

			fetch( url, { credentials: 'same-origin' } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( payload ) {
					// Gec donen eski istek yeni sonucu ezmesin.
					if ( mine !== seq ) {
						return;
					}

					render( payload && payload.success && payload.data ? payload.data : [] );
				} )
				.catch( function () {
					if ( mine === seq ) {
						message( cfg.error );
					}
				} );
		}

		input.addEventListener( 'input', function () {
			var term = input.value.trim();

			window.clearTimeout( timer );

			if ( term.length < 2 ) {
				list.hidden = true;
				list.innerHTML = '';

				return;
			}

			timer = window.setTimeout( function () { search( term ); }, 250 );
		} );

		// Enter arama kutusunda formu gondermemeli.
		input.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key ) {
				event.preventDefault();
			}
		} );

		if ( clear ) {
			clear.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				hidden.value = '';

				if ( current ) {
					current.hidden = true;
				}
			} );
		}
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		Array.prototype.forEach.call(
			document.querySelectorAll( '.dla-mt-postsearch' ),
			setup
		);
	} );
}( document ) );
