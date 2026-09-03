<?php
/**
 * Uzman düzenleme alanları.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Admin;

use DLA\MedicalTrust\Capability\Capabilities;
use DLA\MedicalTrust\Meta\MetaRegistry;
use DLA\MedicalTrust\PostTypes\ExpertPostType;
use DLA\MedicalTrust\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ExpertMetaBox {

	private const NONCE_ACTION = 'dla_mt_save_expert';
	private const NONCE_NAME   = 'dla_mt_expert_nonce';

	public function register(): void {
		add_action( 'add_meta_boxes_' . ExpertPostType::SLUG, [ $this, 'add_meta_box' ] );
		add_action( 'save_post_' . ExpertPostType::SLUG, [ $this, 'save' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Ortam kutuphanesi secicisi — yalnizca uzman duzenleme ekraninda.
	 */
	public function enqueue( string $hook ): void {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen instanceof \WP_Screen || ExpertPostType::SLUG !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();

		// Surum damgasi DOSYA ZAMANI, eklenti surumu DEGIL.
		// VERSION iki yayin arasinda sabit kalabiliyor; o durumda dosya
		// degisse bile tarayici eski kopyayi sunuyor ve yeni davranis hic
		// calismiyordu — hata gibi gorunen ama aslinda onbellek olan bir
		// sinif. AssetManager on yuz stilinde ayni deseni kullaniyor.
		$script_path = DLA_MT_DIR . 'assets/js/dla-mt-admin-media.js';

		wp_enqueue_script(
			'dla-mt-admin-media',
			DLA_MT_URL . 'assets/js/dla-mt-admin-media.js',
			[],
			is_file( $script_path ) ? (string) filemtime( $script_path ) : \DLA\MedicalTrust\VERSION,
			true
		);

		wp_localize_script(
			'dla-mt-admin-media',
			'dlaMtMedia',
			[
				'title'  => __( 'Uzman fotografini sec', 'dla-medical-trust' ),
				'button' => __( 'Bu gorseli kullan', 'dla-medical-trust' ),
			]
		);

		wp_localize_script(
			'dla-mt-admin-media',
			'dlaMtPostSearch',
			[
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'action'    => PostSearch::ACTION,
				'nonce'     => wp_create_nonce( PostSearch::ACTION ),
				'noResults' => __( 'Eslesen icerik bulunamadi.', 'dla-medical-trust' ),
				'error'     => __( 'Arama basarisiz oldu; sayfayi yenileyip tekrar deneyin.', 'dla-medical-trust' ),
			]
		);
	}

	public function add_meta_box(): void {
		add_meta_box(
			'dla-mt-expert',
			__( 'Uzman Bilgileri', 'dla-medical-trust' ),
			[ $this, 'render' ],
			ExpertPostType::SLUG,
			'normal',
			'high'
		);
	}

	public function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$uid         = (string) get_post_meta( $post->ID, MetaRegistry::EXPERT_ENTITY_UID, true );
		$honorific   = (string) get_post_meta( $post->ID, MetaRegistry::EXPERT_HONORIFIC, true );
		$job_title   = (string) get_post_meta( $post->ID, MetaRegistry::EXPERT_JOB_TITLE, true );
		$credentials = (array) get_post_meta( $post->ID, MetaRegistry::EXPERT_CREDENTIALS, true );
		$sameas      = (array) get_post_meta( $post->ID, MetaRegistry::EXPERT_SAMEAS, true );
		$bio         = (string) get_post_meta( $post->ID, MetaRegistry::EXPERT_SHORT_BIO, true );
		$profile_id  = (int) get_post_meta( $post->ID, MetaRegistry::EXPERT_PROFILE_PAGE, true );

		echo '<table class="form-table" role="presentation"><tbody>';

		Field::text(
			'dla_mt_honorific',
			__( 'Unvan', 'dla-medical-trust' ),
			$honorific,
			__( 'Örn. "Op. Dr." — dile göre çevrilir.', 'dla-medical-trust' )
		);

		Field::text(
			'dla_mt_job_title',
			__( 'Uzmanlık başlığı', 'dla-medical-trust' ),
			$job_title,
			__( 'Örn. "Plastik, Rekonstrüktif ve Estetik Cerrahi Uzmanı".', 'dla-medical-trust' )
		);

		Field::textarea(
			'dla_mt_credentials',
			__( 'Üyelikler ve unvanlar', 'dla-medical-trust' ),
			implode( "\n", array_map( 'strval', $credentials ) ),
			__( 'Her satıra bir tane. Örn. TPRECD, EURAPS, ISAPS, ASPS.', 'dla-medical-trust' ),
			4
		);

		Field::post_search(
			'dla_mt_profile_page_id',
			__( 'Kanonik profil sayfası', 'dla-medical-trust' ),
			$profile_id,
			__( 'Sayfa, yazı, portfolyo, hizmet — arayüzü olan HER içerik türü aranır; Ayarlardaki kapsam listesiyle sınırlı değildir. URL değil post ID saklanır, slug değişirse bağlantı kırılmaz. Seçilen kayıt yayımlanmamışsa ön yüzde bağlantı çıkmaz.', 'dla-medical-trust' )
		);

		Field::textarea(
			'dla_mt_sameas',
			__( 'Doğrulanabilir dış profiller', 'dla-medical-trust' ),
			implode( "\n", array_map( 'strval', $sameas ) ),
			__( 'Her satıra bir URL. Politikayı geçemeyen adresler kayıtta sessizce elenir.', 'dla-medical-trust' ),
			4
		);

		Field::textarea(
			'dla_mt_short_bio',
			__( 'Kısa biyografi', 'dla-medical-trust' ),
			$bio,
			__( 'Trust Box içinde uzmanlık başlığının altında gösterilir. BOŞ BIRAKIRSANIZ yukarıdaki "Kanonik profil sayfası"nın ilk 40 kelimesi otomatik kullanılır — kendi metninizi yazmak isterseniz buraya girin. İzin verilen etiketler: p, br, strong, em, ul, ol, li, a.', 'dla-medical-trust' ),
			4
		);

		$this->render_photo_field( $post->ID );

		Field::readonly_row(
			__( 'Varlık kimliği', 'dla-medical-trust' ),
			'' !== $uid ? $uid : __( 'İlk kayıtta üretilecek', 'dla-medical-trust' ),
			__( 'Dil-nötr kalıcı kimlik. Bir kez üretilir, asla değişmez.', 'dla-medical-trust' )
		);

		echo '</tbody></table>';
	}


	/**
	 * Uzman fotografi.
	 *
	 * WordPress'in "One cikan gorsel" kutusu YALNIZCA tema post-thumbnails
	 * destekliyorsa gorunur; bazi temalar destegi belirli icerik turleriyle
	 * sinirlar ve o kutu uzman ekraninda hic cikmaz. Bu alan ayni meta'yi
	 * (_thumbnail_id) yazar, dolayisiyla iki arayuz birbiriyle senkron kalir
	 * ve fotograf tema ne yaparsa yapsin eklenebilir.
	 */
	private function render_photo_field( int $post_id ): void {
		$thumb_id    = (int) get_post_meta( $post_id, '_thumbnail_id', true );
		$empty_label = __( 'Fotograf secilmedi — kutuda portre gorunmez.', 'dla-medical-trust' );

		printf( '<tr><th scope="row">%s</th><td>', esc_html__( 'Uzman fotografi', 'dla-medical-trust' ) );

		printf(
			'<p><button type="button" class="button" id="dla-mt-photo-select">%1$s</button> '
			. '<button type="button" class="button-link" id="dla-mt-photo-clear"%2$s>%3$s</button></p>',
			esc_html__( 'Gorsel sec', 'dla-medical-trust' ),
			$thumb_id > 0 ? '' : ' hidden',
			esc_html__( 'Kaldir', 'dla-medical-trust' )
		);

		printf(
			'<div id="dla-mt-photo-preview" data-empty-label="%s" style="margin:6px 0">',
			esc_attr( $empty_label )
		);

		if ( $thumb_id > 0 && wp_attachment_is_image( $thumb_id ) ) {
			// Kucuk boy uretilmemis olabilir; bu durumda tam boy adres kullanilir
			// ki onizleme her ekte calissin.
			$src = wp_get_attachment_image_url( $thumb_id, 'thumbnail' );
			$src = is_string( $src ) && '' !== $src ? $src : (string) wp_get_attachment_url( $thumb_id );

			if ( '' !== $src ) {
				printf(
					'<img src="%s" alt="" style="width:80px;height:80px;object-fit:cover;border-radius:50%%;border:1px solid #dcdcde">',
					esc_url( $src )
				);
			}
		} elseif ( $thumb_id > 0 ) {
			printf(
				'<span style="color:#8e3a44">%s</span>',
				esc_html__( 'Kayitli ek bir gorsel degil; kaydedince temizlenecek.', 'dla-medical-trust' )
			);
		} else {
			printf( '<em>%s</em>', esc_html( $empty_label ) );
		}

		echo '</div>';

		// Sayi alani gorunur kalir: JS calismadigi durumda (eski tarayici,
		// baska bir eklentinin JS hatasi) fotograf yine de atanabilsin.
		printf(
			'<p><label for="dla_mt_photo_id">%1$s</label> '
			. '<input type="number" id="dla_mt_photo_id" name="dla_mt_photo_id" value="%2$s" min="0" step="1" class="small-text"></p>'
			. '<p class="description">%3$s</p>',
			esc_html__( 'Ek ID:', 'dla-medical-trust' ),
			esc_attr( (string) $thumb_id ),
			esc_html__( 'Trust Box icindeki portre BURADAN gelir. "Gorsel sec" dugmesi bu alani kendisi doldurur. Ayarlardaki "Kurum logosu" alani DEGILDIR.', 'dla-medical-trust' )
		);

		echo '</td></tr>';
	}

	public function save( int $post_id, \WP_Post $post ): void {
		unset( $post );

		if ( ! Field::can_save( self::NONCE_NAME, self::NONCE_ACTION, Capabilities::MANAGE_EXPERTS ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Field::can_save doğruladı.
		$map = [
			MetaRegistry::EXPERT_HONORIFIC    => 'dla_mt_honorific',
			MetaRegistry::EXPERT_JOB_TITLE    => 'dla_mt_job_title',
			MetaRegistry::EXPERT_CREDENTIALS  => 'dla_mt_credentials',
			MetaRegistry::EXPERT_SAMEAS       => 'dla_mt_sameas',
			MetaRegistry::EXPERT_SHORT_BIO    => 'dla_mt_short_bio',
			MetaRegistry::EXPERT_PROFILE_PAGE => 'dla_mt_profile_page_id',
		];

		if ( isset( $_POST['dla_mt_photo_id'] ) ) {
			$photo_id = absint( wp_unslash( $_POST['dla_mt_photo_id'] ) );

			// set_post_thumbnail() KULLANILMIYOR. Cekirdek o fonksiyonda
			// wp_get_attachment_image() ciktisini kontrol eder ve bos donerse
			// _thumbnail_id'yi SESSIZCE SILER. Kucuk boy uretilmemis, boyutlari
			// offload/optimizasyon eklentisiyle degismis veya metadata'si eksik
			// eklerde bu bos donebiliyor; sonuc olarak kullanici gorseli seciyor,
			// kaydediyor ve alan bir daha bos doniyordu — hicbir hata mesaji
			// olmadan. Ekin gercekten gorsel oldugunu MIME turunden dogrulayip
			// meta'yi dogrudan yaziyoruz; cekirdegin sablon fonksiyonlari
			// (get_post_thumbnail_id) yalnizca bu meta'yi okur.
			if ( $photo_id > 0 && wp_attachment_is_image( $photo_id ) ) {
				update_post_meta( $post_id, '_thumbnail_id', $photo_id );
			} else {
				delete_post_meta( $post_id, '_thumbnail_id' );
			}
		}

		foreach ( $map as $meta_key => $field ) {
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}

			// update_post_meta, register_post_meta'daki sanitize_callback'i çalıştırır.
			update_post_meta( $post_id, $meta_key, wp_unslash( $_POST[ $field ] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
