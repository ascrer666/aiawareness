<?php
/** M5 safe UI over M3 ReviewService and explicit change classification. */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Admin;

use DLA\MedicalTrust\Capability\Capabilities;
use DLA\MedicalTrust\Domain\Enum\ChangeClassification;
use DLA\MedicalTrust\Domain\Enum\ReviewStatus;
use DLA\MedicalTrust\Domain\Enum\ReviewValidity;
use DLA\MedicalTrust\Meta\MetaRegistry;
use DLA\MedicalTrust\PostTypes\ExpertPostType;
use DLA\MedicalTrust\Review\ReviewRecordRequest;
use DLA\MedicalTrust\Review\ReviewService;
use DLA\MedicalTrust\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ReviewWorkflowMetaBox {
	private const NONCE_ACTION = 'dla_mt_review_workflow';
	private const NONCE_NAME = 'dla_mt_review_workflow_nonce';

	public function register(): void {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ], 15, 2 );
		foreach ( Settings::eligible_post_types() as $type ) { add_action( 'save_post_' . $type, [ $this, 'save' ], 20, 2 ); }
	}

	public function add_meta_box( string $post_type, $post ): void {
		unset( $post );
		if ( ! in_array( $post_type, Settings::eligible_post_types(), true ) || ! current_user_can( Capabilities::EDIT_META ) ) { return; }
		add_meta_box( 'dla-mt-review-workflow', __( 'Tıbbi İnceleme ve Değişiklik Kararı', 'dla-medical-trust' ), [ $this, 'render' ], $post_type, 'side', 'high' );
	}

	public function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		$status = (string) get_post_meta( $post->ID, MetaRegistry::PAGE_REVIEW_STATUS, true );
		$validity = (string) get_post_meta( $post->ID, MetaRegistry::PAGE_REVIEW_VALIDITY, true );
		$date = (string) get_post_meta( $post->ID, MetaRegistry::PAGE_REVIEW_DATE, true );
		$freshness = ( new ReviewService() )->freshness_for_post( $post->ID );
		printf( '<p><strong>%s</strong><br>%s</p>', esc_html__( 'Mevcut durum', 'dla-medical-trust' ), esc_html( $this->state_label( $status, $validity, $freshness ) ) );
		if ( $this->needs_classification( $post->ID ) ) { $this->classification_fields(); }
		if ( ! Capabilities::has_direct_review_capability( get_current_user_id() ) || ! current_user_can( Capabilities::REVIEW ) ) {
			echo '<p class="description">' . esc_html__( 'Bu kullanıcı tıbbi inceleme kaydı oluşturamaz. İnceleme yetkisi kullanıcı bazında ayrı verilmelidir.', 'dla-medical-trust' ) . '</p>'; return;
		}
		echo '<hr><p><strong>' . esc_html__( 'Yeni tıbbi inceleme kaydı', 'dla-medical-trust' ) . '</strong></p>';
		$this->expert_select( (int) get_post_meta( $post->ID, MetaRegistry::PAGE_REVIEWER_EXPERT_ID, true ) );
		printf( '<p><label>%1$s<br><input type="date" name="dla_mt_review_date" value="%2$s" max="%3$s"></label></p>', esc_html__( 'Gerçek inceleme tarihi', 'dla-medical-trust' ), esc_attr( $date ), esc_attr( current_time( 'Y-m-d' ) ) );
		if ( Settings::get( 'require_signoff_reference', true ) ) { printf( '<p><label>%1$s<br><textarea name="dla_mt_signoff_reference" rows="3" style="width:100%%"></textarea></label></p>', esc_html__( 'Onay dayanağı', 'dla-medical-trust' ) ); }
		echo '<p><label><input type="checkbox" name="dla_mt_record_review_confirm" value="1"> ' . esc_html__( 'Bu inceleme kaydının gerçek tıbbi onaya dayandığını doğruluyorum.', 'dla-medical-trust' ) . '</label></p>';
		echo '<p><button type="submit" class="button button-primary" name="dla_mt_record_review" value="1">' . esc_html__( 'Tıbbi incelemeyi kaydet', 'dla-medical-trust' ) . '</button></p>';
	}

	public function save( int $post_id, \WP_Post $post ): void {
		unset( $post );
		if ( ! isset( $_POST[self::NONCE_NAME] ) || ! current_user_can( Capabilities::EDIT_META ) ) { return; }
		$nonce = sanitize_text_field( wp_unslash( (string) $_POST[self::NONCE_NAME] ) ); if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) { return; }
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce above.
		$service = new ReviewService();
		if ( isset( $_POST['dla_mt_change_classification'] ) ) {
			$service->classify_content_change( $post_id, wp_unslash( $_POST['dla_mt_change_classification'] ) );
		}
		if ( ! isset( $_POST['dla_mt_record_review'] ) ) { return; }
		if ( ! Capabilities::has_direct_review_capability( get_current_user_id() ) || ! current_user_can( Capabilities::REVIEW ) || ! isset( $_POST['dla_mt_record_review_confirm'] ) ) { return; }
		$service->record( new ReviewRecordRequest( $post_id, absint( $_POST['dla_mt_reviewer_expert'] ?? 0 ), (string) wp_unslash( $_POST['dla_mt_review_date'] ?? '' ), (string) wp_unslash( $_POST['dla_mt_signoff_reference'] ?? '' ) ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	private function needs_classification( int $post_id ): bool {
		if ( ReviewStatus::REVIEWED !== get_post_meta( $post_id, MetaRegistry::PAGE_REVIEW_STATUS, true ) || ReviewValidity::VALID !== get_post_meta( $post_id, MetaRegistry::PAGE_REVIEW_VALIDITY, true ) ) { return false; }
		$result = ( new ReviewService() )->classify_content_change( $post_id, null );
		return ! $result->success && in_array( 'classification_required', $result->errors, true );
	}

	private function classification_fields(): void {
		echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__( 'İçerik değişti: bilinçli sınıflandırma gerekli.', 'dla-medical-trust' ) . '</strong></p><p><label><input type="radio" name="dla_mt_change_classification" value="' . esc_attr( ChangeClassification::MINOR_EDIT ) . '"> ' . esc_html__( 'Küçük düzeltme — yazım, noktalama veya tıbbi anlamı değiştirmeyen biçim düzenlemesi; inceleme korunur.', 'dla-medical-trust' ) . '</label></p><p><label><input type="radio" name="dla_mt_change_classification" value="' . esc_attr( ChangeClassification::MEDICAL_CONTENT_UPDATE ) . '"> ' . esc_html__( 'Tıbbi / içerik güncellemesi — mevcut inceleme yeni içeriğe uygulanmaz ve superseded olur.', 'dla-medical-trust' ) . '</label></p></div>';
	}

	private function expert_select( int $selected ): void {
		echo '<p><label>' . esc_html__( 'Tıbbi olarak inceleyen uzman', 'dla-medical-trust' ) . '<br><select name="dla_mt_reviewer_expert" style="width:100%"><option value="">' . esc_html__( '— uzman seçin —', 'dla-medical-trust' ) . '</option>';
		foreach ( get_posts( [ 'post_type' => ExpertPostType::SLUG, 'post_status' => 'publish', 'numberposts' => 200, 'orderby' => 'title' ] ) as $expert ) { printf( '<option value="%1$d"%2$s>%3$s</option>', absint( $expert->ID ), selected( $selected, $expert->ID, false ), esc_html( $expert->post_title ) ); }
		echo '</select></label></p>';
	}

	private function state_label( string $status, string $validity, ?string $freshness ): string {
		$parts = [ ReviewStatus::label( $status ) ]; if ( '' !== $validity ) { $parts[] = ReviewValidity::label( $validity ); } if ( null !== $freshness ) { $parts[] = 'due' === $freshness ? __( 'Yeniden inceleme zamanı geldi', 'dla-medical-trust' ) : __( 'Güncel', 'dla-medical-trust' ); }
		return implode( ' · ', array_filter( $parts ) );
	}
}
