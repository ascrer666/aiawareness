<?php
/**
 * Konu yakınlık merdiveni (Addendum A §4 — DONDURULMUŞ).
 *
 *   L1 birincil konu                 100
 *   L2 ikincil konular                75
 *   L3 küratörlü ilişkili konu        60   ← çıkarsanmış üst konudan ÖNCE
 *   L4 üst konu (1 seviye)            55
 *   L5 üst konu (2 seviye)            35
 *   L6 üst konu (3 seviye)            25
 *   L7 genel havuz                    10
 *
 * Küratörlü ilişkinin üst konudan önce gelmesi kasıtlıdır: editörün açıkça
 * kurduğu ilişki, taksonomi ağacından otomatik türetilenden güvenilirdir.
 *
 * SAF: WordPress fonksiyonu çağırmaz.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Resolver;

use DLA\MedicalTrust\Domain\PageMedicalData;
use DLA\MedicalTrust\Domain\TopicGraph;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TopicProximity {

	public const PRIMARY           = 100;
	public const SECONDARY         = 75;
	public const RELATED           = 60;
	public const PARENT            = 55;
	public const GRANDPARENT       = 35;
	public const GREAT_GRANDPARENT = 25;
	public const GLOBAL_POOL       = 10;

	private const MAX_ANCESTOR_DEPTH = 3;

	/**
	 * Konu UID => [puan, basamak etiketi]
	 *
	 * Bir konu birden çok basamağa uyabilir; EN YÜKSEK puan kazanır.
	 *
	 * @return array{scores:array<string,int>,rungs:array<string,string>}
	 */
	public static function build( PageMedicalData $page, TopicGraph $graph ): array {
		$scores = [];
		$rungs  = [];

		$assign = static function ( ?string $uid, int $score, string $rung ) use ( &$scores, &$rungs ): void {
			if ( null === $uid || '' === $uid ) {
				return;
			}

			if ( ! isset( $scores[ $uid ] ) || $scores[ $uid ] < $score ) {
				$scores[ $uid ] = $score;
				$rungs[ $uid ]  = $rung;
			}
		};

		$primary = $page->effective_primary_topic();

		$assign( $primary, self::PRIMARY, 'primary' );

		foreach ( $page->secondary_topics() as $uid ) {
			$assign( $uid, self::SECONDARY, 'secondary' );
		}

		if ( null !== $primary ) {
			$topic = $graph->get( $primary );

			if ( null !== $topic ) {
				foreach ( $topic->related_uids as $related_uid ) {
					$assign( (string) $related_uid, self::RELATED, 'related' );
				}
			}
		}

		// Üst konu zinciri — sayfaya atanmış TÜM konulardan yürünür.
		$ancestor_scores = [ self::PARENT, self::GRANDPARENT, self::GREAT_GRANDPARENT ];
		$ancestor_rungs  = [ 'parent', 'grandparent', 'great_grandparent' ];

		foreach ( $page->topic_uids as $uid ) {
			$ancestors = $graph->ancestors( (string) $uid, self::MAX_ANCESTOR_DEPTH );

			foreach ( $ancestors as $depth => $ancestor_uid ) {
				$assign( $ancestor_uid, $ancestor_scores[ $depth ] ?? self::GREAT_GRANDPARENT, $ancestor_rungs[ $depth ] ?? 'great_grandparent' );
			}
		}

		// Genel havuz — varsayılan tabanın altındadır, yani normalde kapalı.
		foreach ( $graph->all() as $topic ) {
			if ( $topic->is_global_pool() ) {
				$assign( $topic->uid, self::GLOBAL_POOL, 'global' );
			}
		}

		return [
			'scores' => $scores,
			'rungs'  => $rungs,
		];
	}

	/**
	 * Bir kaynağın en iyi yakınlığı: atandığı konular arasından en yüksek.
	 *
	 * @param string[]          $source_topic_uids
	 * @param array<string,int> $scores
	 * @return array{proximity:int,uid:?string}
	 */
	public static function best_for( array $source_topic_uids, array $scores ): array {
		$best     = -1;
		$best_uid = null;

		foreach ( $source_topic_uids as $uid ) {
			$score = $scores[ $uid ] ?? null;

			if ( null !== $score && $score > $best ) {
				$best     = $score;
				$best_uid = (string) $uid;
			}
		}

		return [
			'proximity' => $best < 0 ? 0 : $best,
			'uid'       => $best_uid,
		];
	}
}
