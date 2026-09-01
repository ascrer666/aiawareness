<?php
/** M5 non-deceptive readiness warnings; advisory only, never blocks publishing. */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Admin;

use DLA\MedicalTrust\Capability\Capabilities;
use DLA\MedicalTrust\Domain\Enum\ReviewStatus;
use DLA\MedicalTrust\Domain\Enum\ReviewValidity;
use DLA\MedicalTrust\Meta\MetaRegistry;
use DLA\MedicalTrust\Repository\TrustDataRepository;
use DLA\MedicalTrust\Review\ReviewService;
use DLA\MedicalTrust\Settings\Settings;
use DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ReadinessPanel {
	public function register(): void { add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ], 30, 2 ); }
	public function add_meta_box( string $post_type, $post ): void {
		unset( $post ); if ( ! in_array( $post_type, Settings::eligible_post_types(), true ) || ! current_user_can( Capabilities::EDIT_META ) ) { return; }
		add_meta_box( 'dla-mt-readiness', __( 'Medical Trust Hazırlık Durumu', 'dla-medical-trust' ), [ $this, 'render' ], $post_type, 'side', 'default' );
	}
	public function render( \WP_Post $post ): void {
		$blocking = []; $recommended = []; $terms = get_the_terms( $post->ID, MedicalTopicTaxonomy::SLUG );
		if ( ! is_array( $terms ) || empty( $terms ) ) { $blocking[] = __( 'Medical topic missing', 'dla-medical-trust' ); }
		if ( is_array( $terms ) && count( $terms ) > 0 && '' === (string) get_post_meta( $post->ID, MetaRegistry::PAGE_PRIMARY_TOPIC_UID, true ) ) { $blocking[] = __( 'Primary topic missing', 'dla-medical-trust' ); }
		$status = (string) get_post_meta( $post->ID, MetaRegistry::PAGE_REVIEW_STATUS, true ); $validity = (string) get_post_meta( $post->ID, MetaRegistry::PAGE_REVIEW_VALIDITY, true );
		if ( ReviewStatus::REVIEWED !== $status ) { $blocking[] = __( 'No medical review recorded', 'dla-medical-trust' ); }
		if ( ReviewValidity::SUPERSEDED === $validity ) { $blocking[] = __( 'Review superseded after content update', 'dla-medical-trust' ); }
		if ( ReviewStatus::REVIEWED === $status && null === get_post( (int) get_post_meta( $post->ID, MetaRegistry::PAGE_REVIEWER_EXPERT_ID, true ) ) ) { $blocking[] = __( 'Reviewer not assigned', 'dla-medical-trust' ); }
		if ( 'due' === ( new ReviewService() )->freshness_for_post( $post->ID ) ) { $recommended[] = __( 'Review due', 'dla-medical-trust' ); }
		$data = ( new TrustDataRepository() )->for_post( $post->ID );
		if ( null !== $data && empty( $data->sources ) ) { $recommended[] = __( 'No qualified sources', 'dla-medical-trust' ); }
		if ( '' === trim( (string) get_post_meta( $post->ID, MetaRegistry::PAGE_COMMENTARY, true ) ) ) { $recommended[] = __( 'Expert commentary missing (optional)', 'dla-medical-trust' ); }
		$this->list( __( 'Blocking integrity issues', 'dla-medical-trust' ), $blocking, 'error' ); $this->list( __( 'Recommended completeness', 'dla-medical-trust' ), $recommended, 'warning' );
		if ( empty( $blocking ) && empty( $recommended ) ) { echo '<p>' . esc_html__( 'Medical Trust information is ready.', 'dla-medical-trust' ) . '</p>'; }
	}
	/** @param string[] $items */ private function list( string $title, array $items, string $type ): void { if ( empty( $items ) ) { return; } echo '<div class="notice notice-' . esc_attr( $type ) . ' inline"><p><strong>' . esc_html( $title ) . '</strong></p><ul>'; foreach ( $items as $item ) { echo '<li>' . esc_html( $item ) . '</li>'; } echo '</ul></div>'; }
}
