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
 *
 * Coklu modda (--multi) sonuclar onay kutuludur. Ayni sayfanin bes ceviri
 * kopyasini tek tek tiklamak dayanilmazdi: liste toplu isaretlenir,
 * "Hepsini listele" ise hic arama yazmadan tum kayitlari getirir.
 */
( function ( document ) {
	'use strict';

	var cfg = window.dlaMtPostSearch;

	if ( ! cfg || ! cfg.ajaxUrl ) {
		return;
	}

	function setup( box ) {
		var hidden   = document.getElementById( box.getAttribute( 'data-target' ) );
		var input    = box.querySelector( '.dla-mt-postsearch__input' );
		var list     = box.querySelector( '.dla-mt-postsearch__results' );
		var current  = box.querySelector( '.dla-mt-postsearch__current' );
		var clear    = box.querySelector( '.dla-mt-postsearch__clear' );
		var chips    = box.querySelector( '.dla-mt-postsearch__chips' );
		var chipsBar = box.querySelector( '.dla-mt-postsearch__chipsbar' );
		var bulk     = box.querySelector( '.dla-mt-postsearch__bulk' );
		var browse   = box.querySelector( '.dla-mt-postsearch__browse' );
		var clearAll = box.querySelector( '.dla-mt-postsearch__clear-all' );
		var types    = box.getAttribute( 'data-types' ) || '';
		var multi    = -1 !== box.className.indexOf( 'dla-mt-postsearch--multi' ) && !! chips;
		var timer    = null;
		var seq      = 0;

		if ( ! hidden || ! input || ! list ) {
			return;
		}

		function closeResults() {
			list.hidden = true;
			list.innerHTML = '';

			if ( bulk ) {
				bulk.hidden = true;
			}
		}

		function message( text ) {
			list.innerHTML = '';
			var li = document.createElement( 'li' );
			li.className = 'dla-mt-postsearch__empty';
			li.textContent = text;
			list.appendChild( li );
			list.hidden = false;

			if ( bulk ) {
				bulk.hidden = true;
			}
		}

		/*
		 * Coklu modda tek dogru kaynak gorunur metin kutusudur; rozetler
		 * yalnizca onun okunabilir yuzudur. Boylece JS yarim kalsa bile
		 * form dogru degeri gonderir.
		 */
		function selectedIds() {
			return hidden.value.split( /[^0-9]+/ ).filter( function ( part ) {
				return '' !== part;
			} );
		}

		function writeIds( ids ) {
			hidden.value = ids.join( ', ' );
			chips.hidden = ! ids.length;

			if ( chipsBar ) {
				chipsBar.hidden = ! ids.length;
			}
		}

		function appendChip( id, label ) {
			var li = document.createElement( 'li' );
			var text = document.createElement( 'span' );
			var remove = document.createElement( 'button' );

			li.setAttribute( 'data-id', String( id ) );
			text.textContent = label;
			remove.type = 'button';
			remove.className = 'button-link dla-mt-postsearch__remove';
			remove.textContent = '×';
			remove.setAttribute( 'aria-label', cfg.removeLabel || '' );

			li.appendChild( text );
			li.appendChild( remove );
			chips.appendChild( li );
		}

		function syncChips() {
			var known = {};

			Array.prototype.forEach.call( chips.children, function ( li ) {
				var span = li.querySelector( 'span' );
				known[ li.getAttribute( 'data-id' ) ] = span ? span.textContent : '';
			} );

			chips.innerHTML = '';

			selectedIds().forEach( function ( id ) {
				appendChip( id, known[ id ] || '#' + id );
			} );

			writeIds( selectedIds() );
		}

		// Eklenen satir listede kalir ama isaretli ve pasif olur: kullanici
		// neyin zaten kapsandigini gorur, ikinci kez eklemeye calismaz.
		function markAdded( checkbox ) {
			checkbox.checked = true;
			checkbox.disabled = true;

			if ( checkbox.parentNode ) {
				checkbox.parentNode.className = 'is-added';
			}
		}

		function addChecked() {
			var ids = selectedIds();

			Array.prototype.forEach.call( list.querySelectorAll( 'input[type="checkbox"]' ), function ( checkbox ) {
				if ( ! checkbox.checked || checkbox.disabled ) {
					return;
				}

				if ( -1 === ids.indexOf( checkbox.value ) ) {
					ids.push( checkbox.value );
					appendChip( checkbox.value, checkbox.getAttribute( 'data-label' ) || ( '#' + checkbox.value ) );
				}

				markAdded( checkbox );
			} );

			writeIds( ids );
		}

		function markAll( checked ) {
			Array.prototype.forEach.call( list.querySelectorAll( 'input[type="checkbox"]' ), function ( checkbox ) {
				if ( ! checkbox.disabled ) {
					checkbox.checked = checked;
				}
			} );
		}

		function collect( id, label ) {
			var ids = selectedIds();

			if ( -1 === ids.indexOf( String( id ) ) ) {
				ids.push( String( id ) );
				appendChip( id, label );
				writeIds( ids );
			}

			input.value = '';
			closeResults();
		}

		function choose( id, label ) {
			if ( multi ) {
				collect( id, label );

				return;
			}

			hidden.value = String( id );

			if ( current ) {
				var span = current.querySelector( 'span' );

				if ( span ) {
					span.textContent = label;
				}

				current.hidden = false;
			}

			input.value = '';
			closeResults();
		}

		function renderSingle( items ) {
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
		}

		function renderMulti( items ) {
			var chosen = selectedIds();

			items.forEach( function ( item ) {
				var li = document.createElement( 'li' );
				var label = document.createElement( 'label' );
				var checkbox = document.createElement( 'input' );
				var added = -1 !== chosen.indexOf( String( item.id ) );

				checkbox.type = 'checkbox';
				checkbox.value = String( item.id );
				checkbox.setAttribute( 'data-label', item.label );
				checkbox.checked = added;
				checkbox.disabled = added;

				label.appendChild( checkbox );
				label.appendChild( document.createTextNode( ' ' + item.label ) );

				if ( added ) {
					label.className = 'is-added';
				}

				li.appendChild( label );
				list.appendChild( li );
			} );

			if ( bulk ) {
				bulk.hidden = false;
			}
		}

		function render( items ) {
			list.innerHTML = '';

			if ( ! items.length ) {
				message( cfg.noResults );

				return;
			}

			if ( multi ) {
				renderMulti( items );
			} else {
				renderSingle( items );
			}

			list.hidden = false;
		}

		function search( term, all ) {
			var mine = ++seq;
			var url = cfg.ajaxUrl
				+ '?action=' + encodeURIComponent( cfg.action )
				+ '&nonce=' + encodeURIComponent( cfg.nonce )
				+ '&types=' + encodeURIComponent( types )
				+ '&term=' + encodeURIComponent( term );

			if ( all ) {
				url += '&browse=1';
			}

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
				closeResults();

				return;
			}

			timer = window.setTimeout( function () { search( term, false ); }, 250 );
		} );

		// Enter arama kutusunda formu gondermemeli.
		input.addEventListener( 'keydown', function ( event ) {
			if ( 'Enter' === event.key ) {
				event.preventDefault();

				if ( multi ) {
					search( input.value.trim(), true );
				}
			}
		} );

		if ( browse ) {
			browse.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				window.clearTimeout( timer );
				search( input.value.trim(), true );
			} );
		}

		if ( multi ) {
			chips.addEventListener( 'click', function ( event ) {
				var button = event.target;

				if ( ! button || -1 === String( button.className ).indexOf( 'dla-mt-postsearch__remove' ) ) {
					return;
				}

				event.preventDefault();

				var removed = button.parentNode.getAttribute( 'data-id' );

				button.parentNode.remove();
				writeIds( selectedIds().filter( function ( id ) {
					return id !== removed;
				} ) );

				// Ayni kayit listede aciksa yeniden eklenebilir olsun.
				Array.prototype.forEach.call( list.querySelectorAll( 'input[type="checkbox"]' ), function ( checkbox ) {
					if ( checkbox.value === removed ) {
						checkbox.disabled = false;
						checkbox.checked = false;
						checkbox.parentNode.className = '';
					}
				} );
			} );

			// ID listesi elle de duzenlenebilir; rozetler o listeyi izler.
			hidden.addEventListener( 'change', syncChips );
		}

		if ( bulk ) {
			bulk.addEventListener( 'click', function ( event ) {
				var button = event.target;
				var name = button ? String( button.className ) : '';

				if ( -1 !== name.indexOf( 'dla-mt-postsearch__add' ) ) {
					event.preventDefault();
					addChecked();
				} else if ( -1 !== name.indexOf( 'dla-mt-postsearch__all' ) ) {
					event.preventDefault();
					markAll( true );
				} else if ( -1 !== name.indexOf( 'dla-mt-postsearch__none' ) ) {
					event.preventDefault();
					markAll( false );
				}
			} );
		}

		if ( clearAll ) {
			clearAll.addEventListener( 'click', function ( event ) {
				event.preventDefault();

				if ( cfg.confirmClear && ! window.confirm( cfg.confirmClear ) ) {
					return;
				}

				chips.innerHTML = '';
				writeIds( [] );
				closeResults();
			} );
		}

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
