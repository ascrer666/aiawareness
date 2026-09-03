<?php
/**
 * Tıbbi konu taksonomisi (v0.1 §6).
 *
 * Hem içerik türlerine hem `dla_source`'a bağlıdır — sorgunun her iki yönü de
 * indeksli çalışsın diye taxonomy seçildi: sayfa→konu, konu→kaynak, konu→sayfalar.
 *
 * `public => false`: ince `/tibbi-konu/rinoplasti/` arşivleri gerçek tedavi
 * sayfalarıyla keyword yamyamlığına girmesin (v0.1 §15).
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Taxonomies;

use DLA\MedicalTrust\Capability\Capabilities;
use DLA\MedicalTrust\PostTypes\SourcePostType;
use DLA\MedicalTrust\Seed\StarterLibrary;
use DLA\MedicalTrust\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MedicalTopicTaxonomy {

	public const SLUG = 'dla_medical_topic';

	public function register(): void {
		add_action( 'init', [ $this, 'register_taxonomy' ], 6 );
		add_action( 'created_' . self::SLUG, [ $this, 'synchronize_catalog_sources' ], 20, 2 );
		add_action( 'edited_' . self::SLUG, [ $this, 'synchronize_catalog_sources' ], 20, 2 );
	}

	/**
	 * A topic is an editor-confirmed medical classification. Once it exists,
	 * the built-in verified catalog may safely attach matching sources; it does
	 * not infer or assign a topic to any content page.
	 */
	public function synchronize_catalog_sources( int $term_id, int $tt_id ): void {
		unset( $tt_id );
		( new StarterLibrary() )->synchronize_and_publish( [ $term_id ] );
	}

	public function register_taxonomy(): void {
		$object_types = array_values(
			array_unique(
				array_merge( Settings::eligible_post_types(), [ SourcePostType::SLUG ] )
			)
		);

		register_taxonomy(
			self::SLUG,
			$object_types,
			[
				'labels'             => [
					'name'              => __( 'Tıbbi Konular', 'dla-medical-trust' ),
					'singular_name'     => __( 'Tıbbi Konu', 'dla-medical-trust' ),
					'search_items'      => __( 'Konu ara', 'dla-medical-trust' ),
					'all_items'         => __( 'Tüm konular', 'dla-medical-trust' ),
					'parent_item'       => __( 'Üst konu', 'dla-medical-trust' ),
					'parent_item_colon' => __( 'Üst konu:', 'dla-medical-trust' ),
					'edit_item'         => __( 'Konuyu düzenle', 'dla-medical-trust' ),
					'update_item'       => __( 'Konuyu güncelle', 'dla-medical-trust' ),
					'add_new_item'      => __( 'Yeni konu ekle', 'dla-medical-trust' ),
					'new_item_name'     => __( 'Yeni konu adı', 'dla-medical-trust' ),
					'menu_name'         => __( 'Tıbbi Konular', 'dla-medical-trust' ),
				],
				'hierarchical'       => true,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_nav_menus'  => false,
				'show_in_rest'       => false,
				'show_admin_column'  => true,
				'show_tagcloud'      => false,
				'rewrite'            => false,
				'query_var'          => false,
				'capabilities'       => [
					// Konular kütüphane düzeyi yapılandırmadır: yönetimi kaynak
					// yöneticisine, atanması içerik editörüne aittir.
					'manage_terms' => Capabilities::MANAGE_SOURCES,
					'edit_terms'   => Capabilities::MANAGE_SOURCES,
					'delete_terms' => Capabilities::MANAGE_SOURCES,
					'assign_terms' => Capabilities::EDIT_META,
				],
			]
		);
	}
}
