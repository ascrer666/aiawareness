<?php
/**
 * Uzman varlığı (v0.1 §6).
 *
 * `public => false` kasıtlıdır: mevcut doktor profil sayfasıyla rekabet eden
 * ince bir arşiv sayfası üretmemek için. Kanonik profil sayfaya post ID ile
 * bağlanır, URL ile değil.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\PostTypes;

use DLA\MedicalTrust\Capability\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ExpertPostType {

	public const SLUG = 'dla_expert';

	/**
	 * Yayımlanmış, gerçek bir uzman kaydı mı?
	 *
	 * DİKKAT: get_post( 0 ) WordPress'te global post'a düşer. ID sıfırsa
	 * ÇAĞRI HİÇ YAPILMAMALIDIR — aksi halde düzenlenen sayfanın kendisi
	 * uzman sanılır.
	 */
	public static function is_valid_published_expert( int $expert_id ): bool {
		if ( $expert_id < 1 ) {
			return false;
		}

		$expert = get_post( $expert_id );

		return $expert instanceof \WP_Post
			&& self::SLUG === $expert->post_type
			&& 'publish' === $expert->post_status;
	}

	/**
	 * Unvan + ad birleşimi.
	 *
	 * Editörler unvanı çoğu zaman HEM kayıt başlığına HEM de unvan alanına
	 * yazıyor. Körlemesine birleştirmek "Op. Dr. Op. Dr. Leyla Arvas" gibi
	 * çıktılar üretir; başlık zaten unvanla başlıyorsa tekrar eklenmez.
	 *
	 * SAF: WordPress fonksiyonu çağırmaz, test edilebilir.
	 */
	public static function compose_name( string $honorific, string $title ): string {
		$honorific = trim( $honorific );
		$title     = trim( $title );

		if ( '' === $honorific ) {
			return $title;
		}

		if ( '' === $title ) {
			return $honorific;
		}

		$normalize = static function ( string $value ): string {
			$value = mb_strtolower( $value, 'UTF-8' );
			$value = str_replace( [ '.', ',' ], ' ', $value );

			return trim( (string) preg_replace( '/\s+/u', ' ', $value ) );
		};

		$normalized_honorific = $normalize( $honorific );
		$normalized_title     = $normalize( $title );

		if ( '' !== $normalized_honorific
			&& ( $normalized_title === $normalized_honorific
				|| str_starts_with( $normalized_title, $normalized_honorific . ' ' ) ) ) {
			return $title;
		}

		return $honorific . ' ' . $title;
	}

	/**
	 * Unvanla birlikte görünen ad. Geçersiz kayıtta null.
	 */
	public static function display_name( int $expert_id ): ?string {
		if ( ! self::is_valid_published_expert( $expert_id ) ) {
			return null;
		}

		$expert = get_post( $expert_id );

		return self::compose_name(
			(string) get_post_meta( $expert_id, \DLA\MedicalTrust\Meta\MetaRegistry::EXPERT_HONORIFIC, true ),
			(string) $expert->post_title
		);
	}

	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ], 5 );
		// Oncelik 999: tema kendi add_theme_support cagrisini yaptiktan SONRA
		// calisir, aksi halde tema bizim genislettigimiz listeyi ezebilir.
		add_action( 'after_setup_theme', [ $this, 'ensure_thumbnail_support' ], 999 );
	}

	/**
	 * Uzman kaydında "Öne çıkan görsel" kutusunun görünmesini garanti eder.
	 *
	 * WordPress bu kutuyu yalnızca TEMA post-thumbnails destekliyorsa gösterir.
	 * Bazı temalar desteği belirli içerik türleriyle SINIRLAR; o durumda
	 * uzman kaydında görsel alanı hiç çıkmaz ve doktor fotoğrafı eklenemez.
	 *
	 * Tema desteği zaten genelse dokunulmaz — yalnızca sınırlı listeye kendi
	 * türümüz eklenir.
	 */
	public function ensure_thumbnail_support(): void {
		if ( ! current_theme_supports( 'post-thumbnails' ) ) {
			add_theme_support( 'post-thumbnails', [ self::SLUG ] );

			return;
		}

		$support = get_theme_support( 'post-thumbnails' );

		// true => tüm türlerde açık, müdahale gerekmez.
		if ( ! is_array( $support ) || ! isset( $support[0] ) || ! is_array( $support[0] ) ) {
			return;
		}

		if ( ! in_array( self::SLUG, $support[0], true ) ) {
			add_theme_support( 'post-thumbnails', array_merge( $support[0], [ self::SLUG ] ) );
		}
	}

	public function register_post_type(): void {
		register_post_type(
			self::SLUG,
			[
				'labels'              => [
					'name'               => __( 'Tıbbi Uzmanlar', 'dla-medical-trust' ),
					'singular_name'      => __( 'Tıbbi Uzman', 'dla-medical-trust' ),
					'add_new'            => __( 'Yeni ekle', 'dla-medical-trust' ),
					'add_new_item'       => __( 'Yeni uzman ekle', 'dla-medical-trust' ),
					'edit_item'          => __( 'Uzmanı düzenle', 'dla-medical-trust' ),
					'new_item'           => __( 'Yeni uzman', 'dla-medical-trust' ),
					'view_item'          => __( 'Uzmanı görüntüle', 'dla-medical-trust' ),
					'search_items'       => __( 'Uzman ara', 'dla-medical-trust' ),
					'not_found'          => __( 'Uzman bulunamadı.', 'dla-medical-trust' ),
					'not_found_in_trash' => __( 'Çöpte uzman yok.', 'dla-medical-trust' ),
					'menu_name'          => __( 'Medical Trust', 'dla-medical-trust' ),
				],
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'menu_icon'           => 'dashicons-shield-alt',
				'menu_position'       => 26,
				'hierarchical'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => [ 'title', 'editor', 'thumbnail', 'revisions' ],
				'capability_type'     => [ 'dla_expert', 'dla_experts' ],
				'capabilities'        => Capabilities::map_for( Capabilities::MANAGE_EXPERTS, 'dla_expert' ),
				'map_meta_cap'        => true,
			]
		);
	}
}
