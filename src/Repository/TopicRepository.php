<?php
/**
 * Konu grafiği deposu.
 *
 * Tüm konular tek seferde yüklenir ve kütüphane sürümüne damgalı olarak
 * önbelleğe alınır. Konular az sayıdadır; parça parça sorgulamak yerine
 * tek yükleme hem daha az sorgu hem daha basit kod demektir.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Repository;

use DLA\MedicalTrust\Domain\Enum\ReviewPolicy;
use DLA\MedicalTrust\Domain\Topic;
use DLA\MedicalTrust\Domain\TopicGraph;
use DLA\MedicalTrust\Meta\MetaRegistry;
use DLA\MedicalTrust\Settings\Settings;
use DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TopicRepository {

	private const CACHE_GROUP = 'dla_mt';

	private static ?TopicGraph $memo = null;
	private static int $memo_version = -1;

	public function graph(): TopicGraph {
		$version = Settings::library_version();

		if ( null !== self::$memo && self::$memo_version === $version ) {
			return self::$memo;
		}

		$key    = 'topic_graph_v' . $version;
		$cached = wp_cache_get( $key, self::CACHE_GROUP );

		if ( is_array( $cached ) ) {
			$graph = $this->hydrate( $cached );
		} else {
			$payload = $this->build_payload();
			wp_cache_set( $key, $payload, self::CACHE_GROUP, HOUR_IN_SECONDS );
			$graph = $this->hydrate( $payload );
		}

		self::$memo         = $graph;
		self::$memo_version = $version;

		return $graph;
	}

	public static function flush_memo(): void {
		self::$memo         = null;
		self::$memo_version = -1;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function build_payload(): array {
		$terms = get_terms(
			[
				'taxonomy'   => MedicalTopicTaxonomy::SLUG,
				'hide_empty' => false,
			]
		);

		if ( ! is_array( $terms ) || empty( $terms ) ) {
			return [];
		}

		$term_ids = array_map( static fn( \WP_Term $t ): int => $t->term_id, $terms );
		update_termmeta_cache( $term_ids );

		// 1. geçiş: term_id => uid haritası (üst konu çözümlemesi için gerekli).
		$uid_of_term = [];
		foreach ( $terms as $term ) {
			$uid = (string) get_term_meta( $term->term_id, MetaRegistry::TOPIC_UID, true );

			if ( '' !== $uid ) {
				$uid_of_term[ $term->term_id ] = $uid;
			}
		}

		// 2. geçiş: UID başına birleştirilmiş konu.
		$payload = [];

		foreach ( $terms as $term ) {
			$uid = $uid_of_term[ $term->term_id ] ?? '';

			if ( '' === $uid ) {
				continue; // Kimliği olmayan konu çözümlemeye giremez.
			}

			$parent_uid = $term->parent > 0 ? ( $uid_of_term[ $term->parent ] ?? null ) : null;

			if ( ! isset( $payload[ $uid ] ) ) {
				$band = metadata_exists( 'term', $term->term_id, MetaRegistry::TOPIC_DIVERSITY_BAND )
					? (int) get_term_meta( $term->term_id, MetaRegistry::TOPIC_DIVERSITY_BAND, true )
					: null;

				$related = get_term_meta( $term->term_id, MetaRegistry::TOPIC_RELATED_UIDS, true );

				$payload[ $uid ] = [
					'uid'               => $uid,
					'name'              => $term->name,
					'slug'              => $term->slug,
					'term_ids'          => [],
					'parent_uid'        => $parent_uid,
					'related_uids'      => is_array( $related ) ? array_values( array_map( 'strval', $related ) ) : [],
					'review_policy'     => (string) ( ReviewPolicy::coerce( get_term_meta( $term->term_id, MetaRegistry::TOPIC_REVIEW_POLICY, true ) ) ?? ReviewPolicy::STANDARD ),
					'diversity_band'    => $band,
					'default_expert_id' => (int) get_term_meta( $term->term_id, MetaRegistry::TOPIC_DEFAULT_EXPERT, true ),
					'schema_type_hint'  => ( (string) get_term_meta( $term->term_id, MetaRegistry::TOPIC_SCHEMA_HINT, true ) ) ?: null,
				];
			}

			$payload[ $uid ]['term_ids'][] = $term->term_id;

			// Üst konu, gruptaki herhangi bir çeviriden gelebilir.
			if ( null === $payload[ $uid ]['parent_uid'] && null !== $parent_uid ) {
				$payload[ $uid ]['parent_uid'] = $parent_uid;
			}
		}

		return $payload;
	}

	/**
	 * @param array<string,array<string,mixed>> $payload
	 */
	private function hydrate( array $payload ): TopicGraph {
		$topics = [];

		foreach ( $payload as $row ) {
			$topics[] = new Topic(
				(string) $row['uid'],
				(string) $row['name'],
				(string) ( $row['slug'] ?? '' ),
				array_map( 'intval', (array) $row['term_ids'] ),
				null !== $row['parent_uid'] ? (string) $row['parent_uid'] : null,
				(array) $row['related_uids'],
				(string) $row['review_policy'],
				null !== $row['diversity_band'] ? (int) $row['diversity_band'] : null,
				(int) $row['default_expert_id'],
				null !== $row['schema_type_hint'] ? (string) $row['schema_type_hint'] : null
			);
		}

		return new TopicGraph( $topics );
	}
}
