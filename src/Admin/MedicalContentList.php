<?php
/** M5 operational columns and index-friendly filters for eligible content. */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Admin;

use DLA\MedicalTrust\Domain\Enum\ReviewStatus;
use DLA\MedicalTrust\Domain\Enum\ReviewValidity;
use DLA\MedicalTrust\Meta\MetaRegistry;
use DLA\MedicalTrust\Repository\TrustDataRepository;
use DLA\MedicalTrust\Settings\Settings;
use DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MedicalContentList {
	public function register(): void {
		foreach ( Settings::eligible_post_types() as $type ) {
			add_filter( 'manage_' . $type . '_posts_columns', [ $this, 'columns' ] );
			add_action( 'manage_' . $type . '_posts_custom_column', [ $this, 'column' ], 10, 2 );
		}
		add_action( 'restrict_manage_posts', [ $this, 'filters' ] );
		add_action( 'pre_get_posts', [ $this, 'apply_filters' ] );
	}

	public function columns( array $columns ): array {
		$columns['dla_mt_topic'] = __( 'Medical Topic', 'dla-medical-trust' );
		$columns['dla_mt_reviewer'] = __( 'Medical Reviewer', 'dla-medical-trust' );
		$columns['dla_mt_review_date'] = __( 'Review Date', 'dla-medical-trust' );
		$columns['dla_mt_review_state'] = __( 'Review State', 'dla-medical-trust' );
		$columns['dla_mt_source_coverage'] = __( 'Source Coverage', 'dla-medical-trust' );
		return $columns;
	}

	public function column( string $column, int $post_id ): void {
		if ( 'dla_mt_topic' === $column ) { $terms = get_the_terms( $post_id, MedicalTopicTaxonomy::SLUG ); echo esc_html( is_array( $terms ) ? implode( ', ', wp_list_pluck( $terms, 'name' ) ) : '—' ); return; }
		if ( 'dla_mt_reviewer' === $column ) { $expert = get_post( (int) get_post_meta( $post_id, MetaRegistry::PAGE_REVIEWER_EXPERT_ID, true ) ); echo esc_html( $expert instanceof \WP_Post ? $expert->post_title : '—' ); return; }
		if ( 'dla_mt_review_date' === $column ) { echo esc_html( (string) get_post_meta( $post_id, MetaRegistry::PAGE_REVIEW_DATE, true ) ?: '—' ); return; }
		if ( 'dla_mt_review_state' === $column ) { $status = (string) get_post_meta( $post_id, MetaRegistry::PAGE_REVIEW_STATUS, true ); $validity = (string) get_post_meta( $post_id, MetaRegistry::PAGE_REVIEW_VALIDITY, true ); echo esc_html( ReviewStatus::label( $status ) . ( '' !== $validity ? ' · ' . ReviewValidity::label( $validity ) : '' ) ); return; }
		if ( 'dla_mt_source_coverage' === $column ) { $data = ( new TrustDataRepository() )->for_post( $post_id ); echo esc_html( null === $data ? '—' : sprintf( '%d / 3', count( $data->sources ) ) ); }
	}

	public function filters( string $post_type ): void {
		if ( ! in_array( $post_type, Settings::eligible_post_types(), true ) ) { return; }
		$value = sanitize_key( $_GET['dla_mt_filter'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$options = [ '' => __( 'Medical Trust: all', 'dla-medical-trust' ), 'no_topic' => __( 'No topic', 'dla-medical-trust' ), 'no_reviewer' => __( 'No reviewer', 'dla-medical-trust' ), 'never_reviewed' => __( 'Never reviewed', 'dla-medical-trust' ), 'superseded' => __( 'Superseded', 'dla-medical-trust' ) ];
		echo '<select name="dla_mt_filter">'; foreach ( $options as $key => $label ) { printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $key ), selected( $value, $key, false ), esc_html( $label ) ); } echo '</select>';
	}

	public function apply_filters( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || ! in_array( (string) $query->get( 'post_type' ), Settings::eligible_post_types(), true ) ) { return; }
		$value = sanitize_key( $_GET['dla_mt_filter'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'no_topic' === $value ) { $query->set( 'tax_query', [ [ 'taxonomy' => MedicalTopicTaxonomy::SLUG, 'operator' => 'NOT EXISTS' ] ] ); }
		if ( 'no_reviewer' === $value ) { $query->set( 'meta_query', [ 'relation' => 'OR', [ 'key' => MetaRegistry::PAGE_REVIEWER_EXPERT_ID, 'compare' => 'NOT EXISTS' ], [ 'key' => MetaRegistry::PAGE_REVIEWER_EXPERT_ID, 'value' => 0, 'compare' => '=' ] ] ); }
		if ( 'never_reviewed' === $value ) { $query->set( 'meta_query', [ 'relation' => 'OR', [ 'key' => MetaRegistry::PAGE_REVIEW_STATUS, 'compare' => 'NOT EXISTS' ], [ 'key' => MetaRegistry::PAGE_REVIEW_STATUS, 'value' => ReviewStatus::REVIEWED, 'compare' => '!=' ] ] ); }
		if ( 'superseded' === $value ) { $query->set( 'meta_query', [ [ 'key' => MetaRegistry::PAGE_REVIEW_VALIDITY, 'value' => ReviewValidity::SUPERSEDED ] ] ); }
	}
}
