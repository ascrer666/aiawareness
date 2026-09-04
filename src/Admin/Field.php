<?php
/**
 * Admin alan yardımcıları.
 *
 * Kural: bu dosyada `echo $degisken` deseni bulunmaz — her değer çıkış
 * noktasında escape edilir (v0.1 §13).
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Field {

	/**
	 * Nonce + yetki + autosave kontrolü. Kaydetmeden önce zorunlu.
	 */
	public static function can_save( string $nonce_name, string $nonce_action, string $capability ): bool {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		if ( ! isset( $_POST[ $nonce_name ] ) ) {
			return false;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST[ $nonce_name ] ) );
		if ( ! wp_verify_nonce( $nonce, $nonce_action ) ) {
			return false;
		}

		return current_user_can( $capability );
	}

	private static function open_row( string $id, string $label ): void {
		printf(
			'<tr><th scope="row"><label for="%1$s">%2$s</label></th><td>',
			esc_attr( $id ),
			esc_html( $label )
		);
	}

	private static function close_row( string $description ): void {
		if ( '' !== $description ) {
			printf( '<p class="description">%s</p>', esc_html( $description ) );
		}
		echo '</td></tr>';
	}

	public static function text( string $id, string $label, string $value, string $description = '' ): void {
		self::open_row( $id, $label );
		printf(
			'<input type="text" class="regular-text" id="%1$s" name="%1$s" value="%2$s">',
			esc_attr( $id ),
			esc_attr( $value )
		);
		self::close_row( $description );
	}

	public static function url( string $id, string $label, string $value, string $description = '' ): void {
		self::open_row( $id, $label );
		printf(
			'<input type="url" class="large-text code" id="%1$s" name="%1$s" value="%2$s" placeholder="https://">',
			esc_attr( $id ),
			esc_attr( $value )
		);
		self::close_row( $description );
	}

	/**
	 * Renk alani: renk secici + hex metin kutusu birlikte.
	 * Bos birakilirsa stil sayfasindaki varsayilan kullanilir.
	 */
	public static function color( string $id, string $label, string $value, string $description = '' ): void {
		self::open_row( $id, $label );

		// Inline JS yok: renk kutusu sunucu tarafinda cizilir. Bos deger
		// "varsayilani kullan" anlamina geldigi icin type="color" tek basina
		// yetmez (o alan her zaman bir deger tasir).
		printf(
			'<span style="display:inline-block;width:26px;height:26px;vertical-align:middle;margin-inline-end:8px;'
			. 'background:%1$s;border:1px solid #8c8f94;border-radius:3px"></span>'
			. '<input type="text" id="%2$s" name="%2$s" value="%3$s" class="regular-text code" '
			. 'placeholder="#f86011" pattern="#[0-9a-fA-F]{6}" style="max-width:140px">',
			esc_attr( '' !== $value ? $value : '#176a62' ),
			esc_attr( $id ),
			esc_attr( $value )
		);

		self::close_row( $description );
	}

	public static function number( string $id, string $label, $value, int $min, int $max, string $description = '' ): void {
		self::open_row( $id, $label );
		printf(
			'<input type="number" class="small-text" id="%1$s" name="%1$s" value="%2$s" min="%3$d" max="%4$d" step="1">',
			esc_attr( $id ),
			esc_attr( (string) $value ),
			esc_attr( (string) $min ),
			esc_attr( (string) $max )
		);
		self::close_row( $description );
	}

	public static function textarea( string $id, string $label, string $value, string $description = '', int $rows = 3 ): void {
		self::open_row( $id, $label );
		printf(
			'<textarea class="large-text" id="%1$s" name="%1$s" rows="%2$d">%3$s</textarea>',
			esc_attr( $id ),
			esc_attr( (string) $rows ),
			esc_textarea( $value )
		);
		self::close_row( $description );
	}

	public static function checkbox( string $id, string $label, bool $checked, string $inline_label, string $description = '' ): void {
		self::open_row( $id, $label );
		printf(
			'<label><input type="checkbox" id="%1$s" name="%1$s" value="1"%2$s> %3$s</label>',
			esc_attr( $id ),
			checked( $checked, true, false ),
			esc_html( $inline_label )
		);
		self::close_row( $description );
	}

	/**
	 * @param array<string,string> $options
	 */
	public static function select( string $id, string $label, string $value, array $options, string $description = '', string $empty_label = '' ): void {
		self::open_row( $id, $label );
		printf( '<select id="%1$s" name="%1$s">', esc_attr( $id ) );

		if ( '' !== $empty_label ) {
			printf( '<option value=""%1$s>%2$s</option>', selected( $value, '', false ), esc_html( $empty_label ) );
		}

		foreach ( $options as $key => $option_label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( (string) $key ),
				selected( $value, (string) $key, false ),
				esc_html( (string) $option_label )
			);
		}

		echo '</select>';
		self::close_row( $description );
	}

	/**
	 * İçerik sayfası seçici. Liste 300 kayıtla sınırlı tutulur — M1'de
	 * arama arayüzü kapsam dışı.
	 *
	 * @param string[] $post_types
	 */
	public static function post_select( string $id, string $label, int $value, array $post_types, string $description = '' ): void {
		$options = [];

		if ( ! empty( $post_types ) ) {
			$posts = get_posts(
				[
					'post_type'              => $post_types,
					'post_status'            => [ 'publish', 'draft', 'private' ],
					'numberposts'            => 300,
					'orderby'                => 'title',
					'order'                  => 'ASC',
					'suppress_filters'       => false,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				]
			);

			foreach ( $posts as $post ) {
				$options[ (string) $post->ID ] = $post->post_title . ' (#' . $post->ID . ')';
			}
		}

		// Seçili kayıt listede yoksa (300 sınırı) yine de korunur.
		if ( $value > 0 && ! isset( $options[ (string) $value ] ) ) {
			$selected_post = get_post( $value );
			if ( $selected_post instanceof \WP_Post ) {
				$options[ (string) $value ] = $selected_post->post_title . ' (#' . $value . ')';
			}
		}

		self::select(
			$id,
			$label,
			$value > 0 ? (string) $value : '',
			$options,
			$description,
			__( '— seçilmedi —', 'dla-medical-trust' )
		);
	}

	/**
	 * Aramali icerik secici.
	 *
	 * post_select() ile arasindaki fark: liste onceden basilmaz, yazdikca
	 * AJAX ile aranir. Boylece binlerce kayitli sitelerde de hedef bulunur
	 * ve sayfa sisirilmez.
	 */
	public static function post_search( string $id, string $label, int $value, string $description = '' ): void {
		self::open_row( $id, $label );

		$current = PostSearch::label_for( $value );

		self::post_search_styles();


		printf(
			'<div class="dla-mt-postsearch" data-target="%1$s">'
			. '<p class="dla-mt-postsearch__current"%3$s><strong>%4$s</strong> <span>%5$s</span> '
			. '<button type="button" class="button-link dla-mt-postsearch__clear">%6$s</button></p>'
			. '<input type="search" class="regular-text dla-mt-postsearch__input" autocomplete="off" placeholder="%7$s">'
			. '<ul class="dla-mt-postsearch__results" hidden></ul>'
			// Kimlik alani GORUNUR bir sayi kutusu: JS calismazsa (onbellekte
			// kalmis eski dosya, baska bir eklentinin JS hatasi) alan sessizce
			// olu kalmasin, ID elle de girilebilsin.
			. '<p class="dla-mt-postsearch__manual"><label for="%1$s">%8$s</label> '
			. '<input type="number" id="%1$s" name="%1$s" value="%2$s" min="0" step="1" class="small-text"></p>'
			. '</div>',
			esc_attr( $id ),
			esc_attr( $value > 0 ? (string) $value : '' ),
			'' !== $current ? '' : ' hidden',
			esc_html__( 'Seçili:', 'dla-medical-trust' ),
			esc_html( $current ),
			esc_html__( 'kaldır', 'dla-medical-trust' ),
			esc_attr__( 'Sayfa adının bir kısmını yazın (en az 2 harf)…', 'dla-medical-trust' ),
			esc_html__( 'veya post ID:', 'dla-medical-trust' )
		);

		self::close_row( $description );
	}

	/**
	 * Aramali secicinin stili.
	 *
	 * Tek seferlik ve yerinde: bu alan icin ayri bir CSS dosyasi yuklemek,
	 * kazanci kadar bakim maliyeti getirirdi.
	 */
	private static function post_search_styles(): void {
		static $styled = false;

		if ( $styled ) {
			return;
		}

		$styled = true;

		echo '<style>'
			. '.dla-mt-postsearch__results{margin:6px 0 0;padding:0;list-style:none;max-height:260px;overflow-y:auto;'
			. 'border:1px solid #c3c4c7;border-radius:4px;background:#fff;max-width:32rem}'
			. '.dla-mt-postsearch__results li{margin:0;border-bottom:1px solid #f0f0f1}'
			. '.dla-mt-postsearch__results li:last-child{border-bottom:0}'
			. '.dla-mt-postsearch__results button{display:block;width:100%;padding:7px 10px;text-align:start;text-decoration:none}'
			. '.dla-mt-postsearch__results button:hover{background:#f6f7f7}'
			. '.dla-mt-postsearch__empty{padding:7px 10px;color:#646970;font-style:italic}'
			. '.dla-mt-postsearch__current{margin:0 0 6px}'
			. '.dla-mt-postsearch__chips{margin:0 0 8px;padding:0;list-style:none;display:flex;flex-wrap:wrap;gap:6px;max-width:32rem}'
			. '.dla-mt-postsearch__chips li{margin:0;display:inline-flex;align-items:center;gap:6px;background:#f0f0f1;'
			. 'border:1px solid #c3c4c7;border-radius:12px;padding:3px 6px 3px 10px;font-size:12px;line-height:1.6}'
			. '.dla-mt-postsearch__chips button{color:#8e3a44;text-decoration:none;font-size:14px;line-height:1}'
			. '.dla-mt-postsearch__manual{margin:8px 0 0}'
			. '.dla-mt-postsearch__results label{display:block;padding:7px 10px;cursor:pointer}'
			. '.dla-mt-postsearch__results label:hover{background:#f6f7f7}'
			. '.dla-mt-postsearch__results input{margin:0 8px 0 0}'
			. '.dla-mt-postsearch__results .is-added{color:#646970;cursor:default}'
			. '.dla-mt-postsearch__search{margin:0;display:flex;gap:6px;align-items:center;max-width:32rem}'
			. '.dla-mt-postsearch__search .dla-mt-postsearch__input{flex:1 1 auto}'
			. '.dla-mt-postsearch__bulk{margin:8px 0 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap}'
			. '.dla-mt-postsearch__chipsbar{margin:0 0 8px}'
			. '</style>';
	}

	/**
	 * Coklu icerik secici.
	 *
	 * post_search() ile ayni AJAX kaynagini kullanir; farki secilenlerin
	 * birikmesi ve TOPLU isaretlenebilmesidir. Tek tek eklemek, ayni sayfanin
	 * bes ceviri kopyasi soz konusu oldugunda dayanilmaz hale geliyordu:
	 * sonuc listesi onay kutuludur, "tumunu isaretle" ve terim yazmadan
	 * "hepsini listele" tek hamlede doldurur.
	 *
	 * Tek dogru kaynak yine GORUNUR metin kutusudur: JS calismasa bile
	 * liste elle duzenlenebilir ve form dogru kaydeder.
	 *
	 * @param int[]    $values
	 * @param string[] $browse_types Listeleme bu turlerle sinirlanir.
	 */
	public static function post_search_multi( string $id, string $label, array $values, string $description = '', array $browse_types = [] ): void {
		self::open_row( $id, $label );
		self::post_search_styles();

		$values = array_values( array_unique( array_filter( array_map( 'absint', $values ) ) ) );
		$empty  = [] === $values;

		printf(
			'<div class="dla-mt-postsearch dla-mt-postsearch--multi" data-target="%1$s" data-types="%2$s">'
			. '<ul class="dla-mt-postsearch__chips"%3$s>',
			esc_attr( $id ),
			esc_attr( implode( ',', array_map( 'sanitize_key', $browse_types ) ) ),
			$empty ? ' hidden' : ''
		);

		foreach ( $values as $value ) {
			$chip_label = PostSearch::label_for( $value );

			printf(
				'<li data-id="%1$d"><span>%2$s</span>'
				. '<button type="button" class="button-link dla-mt-postsearch__remove" aria-label="%3$s">&times;</button></li>',
				(int) $value,
				esc_html( '' !== $chip_label ? $chip_label : '#' . $value ),
				esc_attr__( 'listeden çıkar', 'dla-medical-trust' )
			);
		}

		printf(
			'</ul>'
			. '<p class="dla-mt-postsearch__chipsbar"%1$s>'
			. '<button type="button" class="button-link dla-mt-postsearch__clear-all">%2$s</button></p>'
			. '<p class="dla-mt-postsearch__search">'
			. '<input type="search" class="regular-text dla-mt-postsearch__input" autocomplete="off" placeholder="%3$s">'
			. '<button type="button" class="button dla-mt-postsearch__browse">%4$s</button></p>'
			. '<ul class="dla-mt-postsearch__results" hidden></ul>'
			. '<p class="dla-mt-postsearch__bulk" hidden>'
			. '<button type="button" class="button button-secondary dla-mt-postsearch__add">%5$s</button>'
			. '<button type="button" class="button-link dla-mt-postsearch__all">%6$s</button>'
			. '<button type="button" class="button-link dla-mt-postsearch__none">%7$s</button></p>'
			// Kimlik alani GORUNUR bir metin kutusu: JS calismazsa alan sessizce
			// olu kalmasin, liste elle de yazilabilsin veya toplu yapistirilsin.
			. '<p class="dla-mt-postsearch__manual"><label for="%8$s">%9$s</label> '
			. '<input type="text" id="%8$s" name="%8$s" value="%10$s" class="large-text code"></p>'
			. '</div>',
			$empty ? ' hidden' : '',
			esc_html__( 'listeyi tamamen boşalt', 'dla-medical-trust' ),
			esc_attr__( 'Sayfa adının bir kısmını yazın (en az 2 harf)…', 'dla-medical-trust' ),
			esc_html__( 'Hepsini listele', 'dla-medical-trust' ),
			esc_html__( 'İşaretlenenleri ekle', 'dla-medical-trust' ),
			esc_html__( 'tümünü işaretle', 'dla-medical-trust' ),
			esc_html__( 'işaretleri kaldır', 'dla-medical-trust' ),
			esc_attr( $id ),
			esc_html__( 'Seçili post ID’leri:', 'dla-medical-trust' ),
			esc_attr( implode( ', ', array_map( 'strval', $values ) ) )
		);

		self::close_row( $description );
	}
	public static function readonly_row( string $label, string $value, string $description = '' ): void {
		printf( '<tr><th scope="row">%s</th><td>', esc_html( $label ) );
		printf( '<code>%s</code>', esc_html( $value ) );
		self::close_row( $description );
	}

	/**
	 * Salt okunur bağlantı satırı (kanonik hedef önizlemesi).
	 */
	public static function readonly_link_row( string $label, ?string $url, string $description = '' ): void {
		printf( '<tr><th scope="row">%s</th><td>', esc_html( $label ) );

		if ( null === $url || '' === $url ) {
			printf( '<em>%s</em>', esc_html__( 'Henüz belirlenemiyor', 'dla-medical-trust' ) );
		} else {
			printf(
				'<a href="%1$s" target="_blank" rel="noopener"><code>%2$s</code></a>',
				esc_url( $url ),
				esc_html( $url )
			);
		}

		self::close_row( $description );
	}

	public static function section( string $title ): void {
		printf(
			'<tr><th scope="row" colspan="2" style="padding-bottom:0"><h3 style="margin:14px 0 0">%s</h3></th></tr>',
			esc_html( $title )
		);
	}
}
