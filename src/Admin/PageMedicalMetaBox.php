<?php
/** M5 page-level Medical Content panel. */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Admin;

use DLA\MedicalTrust\Capability\Capabilities;
use DLA\MedicalTrust\Domain\Enum\AuthorMode;
use DLA\MedicalTrust\Domain\Enum\SourceType;
use DLA\MedicalTrust\Meta\MetaRegistry;
use DLA\MedicalTrust\PostTypes\ExpertPostType;
use DLA\MedicalTrust\PostTypes\SourcePostType;
use DLA\MedicalTrust\Repository\SourceRepository;
use DLA\MedicalTrust\Repository\TopicRepository;
use DLA\MedicalTrust\Settings\Settings;
use DLA\MedicalTrust\Support\Sanitizer;
use DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy;
use DLA\MedicalTrust\Taxonomies\SourceTypeTaxonomy;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class PageMedicalMetaBox {

	private const NONCE_ACTION = 'dla_mt_save_page_medical';
	private const NONCE_NAME = 'dla_mt_page_medical_nonce';

	public function register(): void {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ], 10, 2 );
		foreach ( Settings::eligible_post_types() as $type ) {
			add_action( 'save_post_' . $type, [ $this, 'save' ], 10, 2 );
		}
	}

	public function add_meta_box( string $post_type, $post ): void {
		unset( $post );
		if ( ! in_array( $post_type, Settings::eligible_post_types(), true ) || ! current_user_can( Capabilities::EDIT_META ) ) { return; }
		add_meta_box( 'dla-mt-page-medical', __( 'Tıbbi İçerik Bilgileri', 'dla-medical-trust' ), [ $this, 'render' ], $post_type, 'normal', 'high' );
	}

	public function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		$mode = AuthorMode::coerce( get_post_meta( $post->ID, MetaRegistry::PAGE_AUTHOR_MODE, true ) ) ?? AuthorMode::ORGANIZATION;
		$primary_uid = (string) get_post_meta( $post->ID, MetaRegistry::PAGE_PRIMARY_TOPIC_UID, true );
		$selected_terms = wp_get_object_terms( $post->ID, MedicalTopicTaxonomy::SLUG, [ 'fields' => 'ids' ] );
		$flags = get_post_meta( $post->ID, MetaRegistry::PAGE_DISPLAY_FLAGS, true ); $flags = is_array( $flags ) ? $flags : [];

		echo '<p class="description">' . esc_html__( 'Bu alanlar sayfanın Medical Trust verisini yönetir. Kimlik, UID veya kaynak ID girmeyin.', 'dla-medical-trust' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';
		Field::select( 'dla_mt_author_mode', __( 'İçeriği hazırlayan', 'dla-medical-trust' ), $mode, AuthorMode::labels(), __( 'Editoryal ekip seçildiğinde uzman yazar zorunlu değildir. Tıbbi inceleyen uzman ayrı bir bilgidir.', 'dla-medical-trust' ) );
		$this->expert_select( 'dla_mt_author_expert', __( 'İçeriği hazırlayan tıbbi uzman', 'dla-medical-trust' ), (int) get_post_meta( $post->ID, MetaRegistry::PAGE_EXPERT_ID, true ), __( 'Yalnızca içerik gerçekten bu uzman tarafından hazırlandıysa seçin.', 'dla-medical-trust' ) );
		Field::readonly_row( __( 'Tıbbi olarak inceleyen uzman', 'dla-medical-trust' ), $this->reviewer_label( $post->ID ), __( 'Reviewer ataması, tarihli inceleme kaydının parçasıdır. Yalnızca aşağıdaki tıbbi inceleme işleminden yetkili kullanıcı tarafından kaydedilir.', 'dla-medical-trust' ) );
		$this->topic_selects( $primary_uid, array_map( 'intval', (array) $selected_terms ) );
		Field::textarea( 'dla_mt_commentary', __( 'Uzman değerlendirmesi (isteğe bağlı)', 'dla-medical-trust' ), (string) get_post_meta( $post->ID, MetaRegistry::PAGE_COMMENTARY, true ), __( 'Bu sayfaya ve bu dile özeldir; genel uzman biyografisi değildir. İzinli sınırlı HTML kullanılabilir.', 'dla-medical-trust' ), 5 );
		$this->source_overrides( $post->ID );
		Field::checkbox( 'dla_mt_show_commentary', __( 'Görünürlük', 'dla-medical-trust' ), ! array_key_exists( 'show_commentary', $flags ) || (bool) $flags['show_commentary'], __( 'Uzman değerlendirmesini göster', 'dla-medical-trust' ) );
		Field::checkbox( 'dla_mt_show_sources', __( 'Kaynak görünürlüğü', 'dla-medical-trust' ), ! array_key_exists( 'show_sources', $flags ) || (bool) $flags['show_sources'], __( 'Seçilmiş tıbbi kaynakları göster', 'dla-medical-trust' ) );
		echo '</tbody></table>';
	}

	public function save( int $post_id, \WP_Post $post ): void {
		unset( $post );
		if ( ! Field::can_save( self::NONCE_NAME, self::NONCE_ACTION, Capabilities::EDIT_META ) ) { return; }
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce above.
		$mode = AuthorMode::coerce( wp_unslash( $_POST['dla_mt_author_mode'] ?? '' ) ) ?? AuthorMode::ORGANIZATION;
		update_post_meta( $post_id, MetaRegistry::PAGE_AUTHOR_MODE, $mode );
		$author = $this->expert_id( $_POST['dla_mt_author_expert'] ?? 0 );
		update_post_meta( $post_id, MetaRegistry::PAGE_EXPERT_ID, AuthorMode::EXPERT === $mode ? $author : 0 );
		$this->save_topics( $post_id );
		update_post_meta( $post_id, MetaRegistry::PAGE_COMMENTARY, Sanitizer::restricted_html( wp_unslash( $_POST['dla_mt_commentary'] ?? '' ) ) );
		$this->save_overrides( $post_id );
		update_post_meta( $post_id, MetaRegistry::PAGE_DISPLAY_FLAGS, [ 'show_commentary' => isset( $_POST['dla_mt_show_commentary'] ), 'show_sources' => isset( $_POST['dla_mt_show_sources'] ) ] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	private function expert_select( string $id, string $label, int $value, string $description ): void {
		$options = [];
		foreach ( get_posts( [ 'post_type' => ExpertPostType::SLUG, 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC' ] ) as $expert ) { $options[(string) $expert->ID] = $expert->post_title; }
		Field::select( $id, $label, $value > 0 ? (string) $value : '', $options, $description, __( '— seçilmedi —', 'dla-medical-trust' ) );
	}

	private function reviewer_label( int $post_id ): string {
		$reviewer = get_post( (int) get_post_meta( $post_id, MetaRegistry::PAGE_REVIEWER_EXPERT_ID, true ) );
		return $reviewer instanceof \WP_Post ? $reviewer->post_title : __( 'Henüz tarihli tıbbi inceleme kaydı yok', 'dla-medical-trust' );
	}

	/** @param int[] $selected */
	private function topic_selects( string $primary_uid, array $selected ): void {
		$options = [];
		foreach ( get_terms( [ 'taxonomy' => MedicalTopicTaxonomy::SLUG, 'hide_empty' => false ] ) as $term ) {
			$uid = (string) get_term_meta( $term->term_id, MetaRegistry::TOPIC_UID, true );
			if ( '' !== $uid ) { $options[(string) $term->term_id] = $term->name; if ( $uid === $primary_uid ) { $primary = (string) $term->term_id; } }
		}
		Field::select( 'dla_mt_primary_topic', __( 'Birincil tıbbi konu', 'dla-medical-trust' ), $primary ?? '', $options, __( 'Zorunlu karar: birden çok konu varsa otomatik seçilmez.', 'dla-medical-trust' ), __( '— birincil konu seçin —', 'dla-medical-trust' ) );
		echo '<tr><th scope="row"><label for="dla_mt_secondary_topics">' . esc_html__( 'İkincil tıbbi konular', 'dla-medical-trust' ) . '</label></th><td><select id="dla_mt_secondary_topics" name="dla_mt_secondary_topics[]" multiple size="6" style="min-width:280px">';
		foreach ( $options as $id => $name ) { printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $id ), selected( in_array( (int) $id, $selected, true ), true, false ), esc_html( $name ) ); }
		echo '</select><p class="description">' . esc_html__( 'İsteğe bağlıdır. Birincil konu ayrıca yukarıda açıkça seçilmelidir.', 'dla-medical-trust' ) . '</p></td></tr>';
	}

	private function source_overrides( int $post_id ): void {
		$current = get_post_meta( $post_id, MetaRegistry::PAGE_SOURCE_OVERRIDES, true ); $current = is_array( $current ) ? $current : [];
		echo '<tr><th scope="row">' . esc_html__( 'Kaynak modu', 'dla-medical-trust' ) . '</th><td><label><input type="radio" name="dla_mt_source_mode" value="automatic"' . checked( empty( $current ), true, false ) . '> ' . esc_html__( 'Automatic', 'dla-medical-trust' ) . '</label> &nbsp; <label><input type="radio" name="dla_mt_source_mode" value="manual"' . checked( empty( $current ), false, false ) . '> ' . esc_html__( 'Manual Overrides', 'dla-medical-trust' ) . '</label><p class="description">' . esc_html__( 'Automatic varsayılandır. Boş bırakılan slotlar manual modda da resolver tarafından otomatik doldurulur.', 'dla-medical-trust' ) . '</p></td></tr>';
		foreach ( SourceType::values() as $slot ) { $this->source_select( $slot, (int) ( $current[$slot] ?? 0 ) ); }
	}

	private function source_select( string $slot, int $current ): void {
		$options = [];
		$posts = get_posts( [ 'post_type' => SourcePostType::SLUG, 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC', 'tax_query' => [ [ 'taxonomy' => SourceTypeTaxonomy::SLUG, 'field' => 'slug', 'terms' => [ $slot ] ] ] ] );
		foreach ( $posts as $source ) { $options[(string) $source->ID] = $source->post_title; }
		Field::select( 'dla_mt_override_' . $slot, SourceType::label( $slot ), $current > 0 ? (string) $current : '', $options, __( 'Yalnızca aktif ve doğru türdeki kaynaklar listelenir.', 'dla-medical-trust' ), __( '— otomatik seç —', 'dla-medical-trust' ) );
	}

	private function save_topics( int $post_id ): void {
		$primary_id = absint( $_POST['dla_mt_primary_topic'] ?? 0 );
		$secondary = array_map( 'absint', (array) ( $_POST['dla_mt_secondary_topics'] ?? [] ) );
		$terms = array_values( array_unique( array_filter( array_merge( [ $primary_id ], $secondary ) ) ) );
		if ( empty( $terms ) ) { wp_set_object_terms( $post_id, [], MedicalTopicTaxonomy::SLUG ); delete_post_meta( $post_id, MetaRegistry::PAGE_PRIMARY_TOPIC_UID ); return; }
		wp_set_object_terms( $post_id, $terms, MedicalTopicTaxonomy::SLUG, false );
		$uid = $primary_id > 0 ? (string) get_term_meta( $primary_id, MetaRegistry::TOPIC_UID, true ) : '';
		if ( '' !== $uid ) { update_post_meta( $post_id, MetaRegistry::PAGE_PRIMARY_TOPIC_UID, $uid ); } else { delete_post_meta( $post_id, MetaRegistry::PAGE_PRIMARY_TOPIC_UID ); }
	}

	private function save_overrides( int $post_id ): void {
		if ( 'manual' !== ( $_POST['dla_mt_source_mode'] ?? '' ) ) { delete_post_meta( $post_id, MetaRegistry::PAGE_SOURCE_OVERRIDES ); return; }
		$graph = ( new TopicRepository() )->graph(); $sources = new SourceRepository(); $out = [];
		foreach ( SourceType::values() as $slot ) { $id = absint( $_POST['dla_mt_override_' . $slot] ?? 0 ); $source = $id > 0 && 'publish' === $sources->status_of( $id ) ? $sources->find( $id, $graph ) : null; if ( null !== $source && $slot === $source->type && $source->is_eligible() ) { $out[$slot] = $id; } }
		update_post_meta( $post_id, MetaRegistry::PAGE_SOURCE_OVERRIDES, $out );
	}

	private function expert_id( $value ): int { $post = get_post( absint( $value ) ); return $post instanceof \WP_Post && ExpertPostType::SLUG === $post->post_type && 'publish' === $post->post_status ? $post->ID : 0; }
}
