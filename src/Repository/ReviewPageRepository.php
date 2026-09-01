<?php
/** ReviewService için WordPress okuma sınırı. */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Repository;

use DLA\MedicalTrust\Domain\Topic;
use DLA\MedicalTrust\Meta\MetaRegistry;
use DLA\MedicalTrust\PostTypes\ExpertPostType;
use DLA\MedicalTrust\Settings\Settings;
use DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ReviewPageRepository {

	/** @return array{post:\WP_Post,topic:Topic,hash:string,log:array<int,array<string,mixed>>}|null */
	public function find( int $post_id ): ?array {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, Settings::eligible_post_types(), true ) ) { return null; }
		$graph = ( new TopicRepository() )->graph();
		$terms = get_the_terms( $post_id, MedicalTopicTaxonomy::SLUG );
		$uids  = $graph->uids_for_terms( is_array( $terms ) ? array_map( static fn( \WP_Term $term ): int => $term->term_id, $terms ) : [] );
		$primary = (string) get_post_meta( $post_id, MetaRegistry::PAGE_PRIMARY_TOPIC_UID, true );
		$topic = $graph->get( in_array( $primary, $uids, true ) ? $primary : ( $uids[0] ?? '' ) );
		if ( ! $topic instanceof Topic ) { return null; }
		$log = get_post_meta( $post_id, MetaRegistry::PAGE_REVIEW_LOG, true );

		return [ 'post' => $post, 'topic' => $topic, 'hash' => \DLA\MedicalTrust\Review\ContentHasher::hash( $post->post_title, $post->post_content ), 'log' => is_array( $log ) ? $log : [] ];
	}

	public function valid_expert( int $expert_id ): bool {
		$expert = get_post( $expert_id );
		return $expert instanceof \WP_Post && ExpertPostType::SLUG === $expert->post_type && 'publish' === $expert->post_status;
	}
}
