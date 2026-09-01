<?php
/**
 * Kaynak türü — kontrollü taksonomi (Addendum A §3).
 *
 * Taxonomy'dir çünkü "bu slotta hangi kaynaklar var" sorgusu indeksli join
 * gerektirir. (Karşılaştırma: `discovered_via` post meta enum'udur; hiçbir
 * zaman ters yönlü sorgulanmaz.)
 *
 * Term'ler kod tarafından oluşturulur ve arayüzden yenisi EKLENEMEZ:
 * `edit_terms` / `delete_terms` yetenekleri bilinçli olarak `do_not_allow`.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Taxonomies;

use DLA\MedicalTrust\Capability\Capabilities;
use DLA\MedicalTrust\Domain\Enum\SourceType;
use DLA\MedicalTrust\PostTypes\SourcePostType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SourceTypeTaxonomy {

	public const SLUG = 'dla_source_type';

	public function register(): void {
		add_action( 'init', [ $this, 'register_taxonomy' ], 6 );
	}

	public function register_taxonomy(): void {
		register_taxonomy(
			self::SLUG,
			[ SourcePostType::SLUG ],
			[
				'labels'             => [
					'name'          => __( 'Kaynak Türleri', 'dla-medical-trust' ),
					'singular_name' => __( 'Kaynak Türü', 'dla-medical-trust' ),
					'all_items'     => __( 'Tüm türler', 'dla-medical-trust' ),
					'edit_item'     => __( 'Türü düzenle', 'dla-medical-trust' ),
					'menu_name'     => __( 'Kaynak Türleri', 'dla-medical-trust' ),
				],
				'hierarchical'       => false,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => false,
				'show_in_nav_menus'  => false,
				'show_in_rest'       => false,
				'show_admin_column'  => true,
				'show_tagcloud'      => false,
				'rewrite'            => false,
				'query_var'          => false,
				'meta_box_cb'        => [ $this, 'render_meta_box' ],
				'capabilities'       => [
					'manage_terms' => Capabilities::MANAGE_SOURCES,
					'edit_terms'   => 'do_not_allow',
					'delete_terms' => 'do_not_allow',
					'assign_terms' => Capabilities::MANAGE_SOURCES,
				],
			]
		);
	}

	/**
	 * Kod tarafından oluşturulan sabit term'ler. Etkinleştirmede çalışır.
	 */
	public static function ensure_terms(): void {
		foreach ( SourceType::values() as $slug ) {
			$existing = get_term_by( 'slug', $slug, self::SLUG );

			if ( $existing instanceof \WP_Term ) {
				continue;
			}

			wp_insert_term(
				SourceType::label( $slug ),
				self::SLUG,
				[
					'slug'        => $slug,
					'description' => SourceType::descriptions()[ $slug ] ?? '',
				]
			);
		}
	}

	/**
	 * Tek seçimli radyo kutusu — çoklu tür seçimi anlamsız olurdu.
	 */
	public function render_meta_box( \WP_Post $post, array $box ): void {
		$selected = '';
		$terms    = get_the_terms( $post->ID, self::SLUG );

		if ( is_array( $terms ) && ! empty( $terms ) ) {
			$selected = $terms[0]->slug;
		}

		$descriptions = SourceType::descriptions();

		wp_nonce_field( 'dla_mt_source_type', 'dla_mt_source_type_nonce' );

		echo '<div class="dla-mt-source-type">';

		foreach ( SourceType::values() as $slug ) {
			printf(
				'<p style="margin:0 0 10px"><label style="display:block;font-weight:600">
					<input type="radio" name="dla_mt_source_type" value="%1$s"%2$s> %3$s
				</label><span style="display:block;color:#646970;font-size:12px;margin:2px 0 0 24px">%4$s</span></p>',
				esc_attr( $slug ),
				checked( $selected, $slug, false ),
				esc_html( SourceType::label( $slug ) ),
				esc_html( $descriptions[ $slug ] ?? '' )
			);
		}

		echo '</div>';

		unset( $box );
	}

	/**
	 * Kayıtta tek term uygulanır.
	 */
	public static function save( int $post_id ): void {
		if ( ! isset( $_POST['dla_mt_source_type_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST['dla_mt_source_type_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'dla_mt_source_type' ) ) {
			return;
		}

		if ( ! current_user_can( Capabilities::MANAGE_SOURCES ) ) {
			return;
		}

		$value = isset( $_POST['dla_mt_source_type'] )
			? sanitize_key( wp_unslash( (string) $_POST['dla_mt_source_type'] ) )
			: '';

		if ( ! SourceType::is_valid( $value ) ) {
			wp_set_object_terms( $post_id, [], self::SLUG );

			return;
		}

		wp_set_object_terms( $post_id, [ $value ], self::SLUG, false );
	}
}
