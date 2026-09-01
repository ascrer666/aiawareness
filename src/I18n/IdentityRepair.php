<?php
/**
 * Kimlik onarım rutini (M2.0).
 *
 * Ayrışmış çeviri kimliklerini tespit eder ve düzeltir. Oluşturma kancası
 * devreye girmeden önce oluşturulmuş çeviriler için gereklidir.
 *
 * İki kritik özellik:
 *   - IDEMPOTENT : ikinci çalıştırma hiçbir şey değiştirmez.
 *   - ATIF GÜVENLİ : bir konu kimliği değiştiğinde ona yapılan atıflar
 *     (`_dla_related_topic_uids`, `_dla_primary_topic_uid`) yeniden yazılır.
 *     Bu adım atlanırsa onarım, kırık referanslar üretir.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\I18n;

use DLA\MedicalTrust\Meta\MetaRegistry;
use DLA\MedicalTrust\PostTypes\ExpertPostType;
use DLA\MedicalTrust\Settings\Settings;
use DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IdentityRepair {

	private const BATCH = 200;

	/**
	 * Tam onarım.
	 *
	 * @return array{
	 *     topics_scanned:int, topics_fixed:int,
	 *     experts_scanned:int, experts_fixed:int,
	 *     pages_scanned:int, pages_fixed:int,
	 *     references_rewritten:int,
	 *     uid_replacements:array<string,string>,
	 *     library_version_bumped:bool
	 * }
	 */
	public function run(): array {
		$sync = new IdentitySync();

		$report = [
			'topics_scanned'         => 0,
			'topics_fixed'           => 0,
			'experts_scanned'        => 0,
			'experts_fixed'          => 0,
			'pages_scanned'          => 0,
			'pages_fixed'            => 0,
			'references_rewritten'   => 0,
			'uid_replacements'       => [],
			'library_version_bumped' => false,
		];

		/* ------------------------------------------------------ Konular */
		$replacements = [];
		$seen         = [];

		foreach ( $this->topic_ids() as $term_id ) {
			if ( isset( $seen[ $term_id ] ) ) {
				continue;
			}

			++$report['topics_scanned'];

			$result = $sync->normalize_term_group( $term_id );

			foreach ( Languages::adapter()->term_translations( $term_id ) as $member_id ) {
				$seen[ (int) $member_id ] = true;
			}

			if ( $result['changed'] > 0 ) {
				$report['topics_fixed'] += $result['changed'];
				$replacements            = array_merge( $replacements, $result['replaced'] );
			}
		}

		/* ----------------------------------------------------- Uzmanlar */
		$seen = [];
		foreach ( $this->post_ids( [ ExpertPostType::SLUG ] ) as $post_id ) {
			if ( isset( $seen[ $post_id ] ) ) {
				continue;
			}

			++$report['experts_scanned'];

			$result = $sync->normalize_post_group(
				$post_id,
				MetaRegistry::EXPERT_ENTITY_UID,
				\DLA\MedicalTrust\Identity\UidGenerator::PREFIX_EXPERT,
				'expert'
			);

			foreach ( Languages::adapter()->post_translations( $post_id ) as $member_id ) {
				$seen[ (int) $member_id ] = true;
			}

			$report['experts_fixed'] += $result['changed'];
		}

		/* ------------------------------------------------- Sayfa grupları */
		$eligible = Settings::eligible_post_types();

		if ( ! empty( $eligible ) ) {
			$seen = [];
			foreach ( $this->post_ids( $eligible ) as $post_id ) {
				if ( isset( $seen[ $post_id ] ) ) {
					continue;
				}

				++$report['pages_scanned'];

				$result = $sync->normalize_post_group(
					$post_id,
					MetaRegistry::PAGE_GROUP_UID,
					\DLA\MedicalTrust\Identity\UidGenerator::PREFIX_GROUP,
					'page_group'
				);

				foreach ( Languages::adapter()->post_translations( $post_id ) as $member_id ) {
					$seen[ (int) $member_id ] = true;
				}

				$report['pages_fixed'] += $result['changed'];
			}
		}

		/* --------------------------------------------------- Atıf onarımı */
		if ( ! empty( $replacements ) ) {
			$report['uid_replacements']     = $replacements;
			$report['references_rewritten'] = $this->rewrite_references( $replacements );
		}

		/* ------------------------------------- Cache geçersiz kılma */
		$changed = $report['topics_fixed'] + $report['experts_fixed']
			+ $report['pages_fixed'] + $report['references_rewritten'];

		if ( $changed > 0 ) {
			// Konu kimliği değişimi çözümleme sonucunu etkileyebilir; cache
			// tembel olarak yeniden hesaplansın diye sayaç artırılır.
			Settings::bump_library_version();
			$report['library_version_bumped'] = true;
		}

		return $report;
	}

	/**
	 * Değişen konu kimliklerine yapılan tüm atıfları yeniden yazar.
	 *
	 * @param array<string,string> $replacements eski uid => yeni uid
	 */
	private function rewrite_references( array $replacements ): int {
		$rewritten = 0;

		// 1) Konuların "ilişkili konular" listeleri.
		foreach ( $this->topic_ids() as $term_id ) {
			$related = get_term_meta( $term_id, MetaRegistry::TOPIC_RELATED_UIDS, true );

			if ( ! is_array( $related ) || empty( $related ) ) {
				continue;
			}

			$updated = [];
			$dirty   = false;

			foreach ( $related as $uid ) {
				$uid = (string) $uid;

				if ( isset( $replacements[ $uid ] ) ) {
					$updated[] = $replacements[ $uid ];
					$dirty     = true;
					continue;
				}

				$updated[] = $uid;
			}

			if ( $dirty ) {
				update_term_meta( $term_id, MetaRegistry::TOPIC_RELATED_UIDS, array_values( array_unique( $updated ) ) );
				++$rewritten;
			}
		}

		// 2) Sayfaların birincil konu atıfları.
		$eligible = Settings::eligible_post_types();

		if ( ! empty( $eligible ) ) {
			foreach ( $this->post_ids( $eligible ) as $post_id ) {
				$uid = (string) get_post_meta( $post_id, MetaRegistry::PAGE_PRIMARY_TOPIC_UID, true );

				if ( '' !== $uid && isset( $replacements[ $uid ] ) ) {
					update_post_meta( $post_id, MetaRegistry::PAGE_PRIMARY_TOPIC_UID, $replacements[ $uid ] );
					++$rewritten;
				}
			}
		}

		return $rewritten;
	}

	/**
	 * @return int[]
	 */
	private function topic_ids(): array {
		$terms = get_terms(
			[
				'taxonomy'   => MedicalTopicTaxonomy::SLUG,
				'hide_empty' => false,
				'fields'     => 'ids',
			]
		);

		return is_array( $terms ) ? array_map( 'intval', $terms ) : [];
	}

	/**
	 * Partiler hâlinde tüm ID'ler. Binlerce sayfada bellek patlamasın diye
	 * tek seferde -1 çekilmez.
	 *
	 * @param string[] $post_types
	 * @return int[]
	 */
	private function post_ids( array $post_types ): array {
		$out    = [];
		$offset = 0;

		do {
			$batch = get_posts(
				[
					'post_type'              => $post_types,
					'post_status'            => [ 'publish', 'draft', 'pending', 'private', 'future' ],
					'numberposts'            => self::BATCH,
					'offset'                 => $offset,
					'fields'                 => 'ids',
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'suppress_filters'       => false,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'no_found_rows'          => true,
				]
			);

			foreach ( $batch as $id ) {
				$out[] = (int) $id;
			}

			$offset += self::BATCH;
		} while ( count( $batch ) === self::BATCH );

		return $out;
	}
}
