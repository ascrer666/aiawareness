<?php
/**
 * Kaynak deposu.
 *
 * `WP_Query` ve `get_post_meta`'ya dokunan tek yer. Domain nesnesi döndürür.
 *
 * DİKKAT: `_dla_discovered_via` burada BİLİNÇLİ olarak okunmaz. Domain
 * nesnesine girmediği için skorlamaya ulaşması imkânsızdır (Addendum A §3).
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Repository;

use DLA\MedicalTrust\Domain\Enum\SourceHealth;
use DLA\MedicalTrust\Domain\Source;
use DLA\MedicalTrust\Domain\TopicGraph;
use DLA\MedicalTrust\Meta\MetaRegistry;
use DLA\MedicalTrust\PostTypes\SourcePostType;
use DLA\MedicalTrust\Support\UrlPolicy;
use DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy;
use DLA\MedicalTrust\Taxonomies\SourceTypeTaxonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SourceRepository {

	/**
	 * Tek sorguda çekilecek azami aday. Sınırsız sorgu ön yüz yolunda yasak
	 * (v0.1 §14).
	 */
	private const MAX_CANDIDATES = 200;

	/**
	 * Bir slot ve konu kümesi için aday kaynaklar.
	 *
	 * Yalnızca `publish` yüklenir: `pending` (aday), `private` (emekli) ve
	 * `trash` seçime hiç girmez — Addendum A §2, 1. katı kısıt.
	 *
	 * @param int[] $term_ids
	 * @return Source[]
	 */
	public function find_candidates( array $term_ids, string $slot, TopicGraph $graph ): array {
		if ( empty( $term_ids ) ) {
			return [];
		}

		$query = new \WP_Query(
			[
				'post_type'              => SourcePostType::SLUG,
				'post_status'            => 'publish',
				'posts_per_page'         => self::MAX_CANDIDATES,
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => true,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'tax_query'              => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
					'relation' => 'AND',
					[
						'taxonomy' => SourceTypeTaxonomy::SLUG,
						'field'    => 'slug',
						'terms'    => [ $slot ],
					],
					[
						'taxonomy' => MedicalTopicTaxonomy::SLUG,
						'field'    => 'term_id',
						'terms'    => $term_ids,
					],
				],
			]
		);

		$out = [];

		foreach ( $query->posts as $post ) {
			$source = $this->hydrate( $post, $slot, $graph );

			if ( null !== $source ) {
				$out[] = $source;
			}
		}

		return $out;
	}

	public function find( int $post_id, TopicGraph $graph ): ?Source {
		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post || SourcePostType::SLUG !== $post->post_type ) {
			return null;
		}

		$terms = get_the_terms( $post->ID, SourceTypeTaxonomy::SLUG );
		$slot  = is_array( $terms ) && ! empty( $terms ) ? $terms[0]->slug : '';

		return $this->hydrate( $post, $slot, $graph );
	}

	public function status_of( int $post_id ): string {
		return (string) get_post_status( $post_id );
	}

	private function hydrate( \WP_Post $post, string $slot, TopicGraph $graph ): ?Source {
		$meta = static fn( string $key ) => get_post_meta( $post->ID, $key, true );

		$topic_terms = get_the_terms( $post->ID, MedicalTopicTaxonomy::SLUG );
		$term_ids    = is_array( $topic_terms )
			? array_map( static fn( \WP_Term $t ): int => $t->term_id, $topic_terms )
			: [];

		$canonical = UrlPolicy::canonical(
			[
				'doi'           => (string) $meta( MetaRegistry::SOURCE_DOI ),
				'pmc_id'        => (string) $meta( MetaRegistry::SOURCE_PMC_ID ),
				'publisher_url' => (string) $meta( MetaRegistry::SOURCE_PUBLISHER_URL ),
				'url'           => (string) $meta( MetaRegistry::SOURCE_URL ),
				'pmid'          => (string) $meta( MetaRegistry::SOURCE_PMID ),
			]
		);

		$health = (string) $meta( MetaRegistry::SOURCE_HEALTH );

		return new Source(
			$post->ID,
			(string) $meta( MetaRegistry::SOURCE_UID ),
			$post->post_title,
			$slot,
			( (string) $meta( MetaRegistry::SOURCE_PUBLICATION_TYPE ) ) ?: null,
			(bool) $meta( MetaRegistry::SOURCE_PEER_REVIEWED ),
			(int) $meta( MetaRegistry::SOURCE_PRIORITY ),
			(int) $meta( MetaRegistry::SOURCE_PUB_YEAR ),
			'' !== $health ? $health : SourceHealth::UNKNOWN,
			$graph->uids_for_terms( $term_ids ),
			$canonical,
			(string) $meta( MetaRegistry::SOURCE_PUBLISHER ),
			(string) $meta( MetaRegistry::SOURCE_JOURNAL ),
			(string) $meta( MetaRegistry::SOURCE_LANG )
		);
	}
}
