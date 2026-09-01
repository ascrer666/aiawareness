<?php
/**
 * Sayfa düzeyi tıbbi veri deposu.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Repository;

use DLA\MedicalTrust\Domain\Enum\SourceType;
use DLA\MedicalTrust\Domain\PageMedicalData;
use DLA\MedicalTrust\Domain\TopicGraph;
use DLA\MedicalTrust\Meta\MetaRegistry;
use DLA\MedicalTrust\Settings\Settings;
use DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PageDataRepository {

	public function for_post( int $post_id, TopicGraph $graph ): ?PageMedicalData {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return null;
		}

		if ( ! in_array( $post->post_type, Settings::eligible_post_types(), true ) ) {
			return null;
		}

		$terms    = get_the_terms( $post_id, MedicalTopicTaxonomy::SLUG );
		$term_ids = is_array( $terms )
			? array_map( static fn( \WP_Term $t ): int => $t->term_id, $terms )
			: [];

		$topic_uids = $graph->uids_for_terms( $term_ids );

		$primary = (string) get_post_meta( $post_id, MetaRegistry::PAGE_PRIMARY_TOPIC_UID, true );

		$overrides_raw = get_post_meta( $post_id, MetaRegistry::PAGE_SOURCE_OVERRIDES, true );
		$overrides     = [];

		if ( is_array( $overrides_raw ) ) {
			foreach ( SourceType::values() as $slot ) {
				$id = (int) ( $overrides_raw[ $slot ] ?? 0 );

				if ( $id > 0 ) {
					$overrides[ $slot ] = $id;
				}
			}
		}

		return new PageMedicalData(
			$post_id,
			(string) get_post_meta( $post_id, MetaRegistry::PAGE_GROUP_UID, true ),
			$topic_uids,
			'' !== $primary ? $primary : null,
			$overrides
		);
	}
}
