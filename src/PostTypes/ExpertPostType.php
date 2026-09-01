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

	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ], 5 );
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
				'capabilities'        => Capabilities::map_for( Capabilities::MANAGE_EXPERTS ),
				'map_meta_cap'        => true,
			]
		);
	}
}
