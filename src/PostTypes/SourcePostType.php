<?php
/**
 * Küratörlü kaynak kütüphanesi (v0.1 §6).
 *
 * Kaynak durumu için ayrı bir alan YOKTUR; WordPress'in kendi post_status'u
 * kullanılır:
 *   pending → aday (Phase 2 PubMed akışı buraya düşer), seçime girmez
 *   publish → aktif, seçime girer
 *   private → emekli, kayıt korunur, seçime girmez
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\PostTypes;

use DLA\MedicalTrust\Capability\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SourcePostType {

	public const SLUG = 'dla_source';

	public function register(): void {
		add_action( 'init', [ $this, 'register_post_type' ], 5 );
	}

	public function register_post_type(): void {
		register_post_type(
			self::SLUG,
			[
				'labels'              => [
					'name'               => __( 'Kaynaklar', 'dla-medical-trust' ),
					'singular_name'      => __( 'Kaynak', 'dla-medical-trust' ),
					'add_new'            => __( 'Yeni ekle', 'dla-medical-trust' ),
					'add_new_item'       => __( 'Yeni kaynak ekle', 'dla-medical-trust' ),
					'edit_item'          => __( 'Kaynağı düzenle', 'dla-medical-trust' ),
					'new_item'           => __( 'Yeni kaynak', 'dla-medical-trust' ),
					'view_item'          => __( 'Kaynağı görüntüle', 'dla-medical-trust' ),
					'search_items'       => __( 'Kaynak ara', 'dla-medical-trust' ),
					'not_found'          => __( 'Kaynak bulunamadı.', 'dla-medical-trust' ),
					'not_found_in_trash' => __( 'Çöpte kaynak yok.', 'dla-medical-trust' ),
					'menu_name'          => __( 'Kaynaklar', 'dla-medical-trust' ),
				],
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=' . ExpertPostType::SLUG,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'hierarchical'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => [ 'title' ],
				'capability_type'     => [ 'dla_source', 'dla_sources' ],
				'capabilities'        => Capabilities::map_for( Capabilities::MANAGE_SOURCES, 'dla_source' ),
				'map_meta_cap'        => true,
			]
		);
	}
}
