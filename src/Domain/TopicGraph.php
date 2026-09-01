<?php
/**
 * Konu grafiği — tüm konular tek seferde belleğe alınır.
 *
 * Konular onlarca/yüzlerce adettir; parça parça sorgulamak yerine bir kez
 * yüklenip önbelleğe alınması hem daha basit hem daha hızlıdır.
 *
 * SAF: WordPress bilmez, test edilebilir.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TopicGraph {

	/** @var array<string,Topic> uid => Topic */
	private array $by_uid = [];

	/** @var array<int,string> term_id => uid */
	private array $term_index = [];

	/**
	 * @param Topic[] $topics
	 */
	public function __construct( array $topics ) {
		foreach ( $topics as $topic ) {
			$this->by_uid[ $topic->uid ] = $topic;

			foreach ( $topic->term_ids as $term_id ) {
				$this->term_index[ (int) $term_id ] = $topic->uid;
			}
		}
	}

	public function get( string $uid ): ?Topic {
		return $this->by_uid[ $uid ] ?? null;
	}

	public function uid_for_term( int $term_id ): ?string {
		return $this->term_index[ $term_id ] ?? null;
	}

	/**
	 * @param int[] $term_ids
	 * @return string[]
	 */
	public function uids_for_terms( array $term_ids ): array {
		$out = [];

		foreach ( $term_ids as $term_id ) {
			$uid = $this->uid_for_term( (int) $term_id );

			if ( null !== $uid ) {
				$out[] = $uid;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Bir UID kümesine karşılık gelen tüm term ID'leri — her dildeki.
	 *
	 * Kaynak sorgusu bunu kullanır: sayfa İngilizce, kaynak Türkçe konuya
	 * atanmış olabilir; eşleşme UID üzerinden kurulur.
	 *
	 * @param string[] $uids
	 * @return int[]
	 */
	public function term_ids_for_uids( array $uids ): array {
		$out = [];

		foreach ( $uids as $uid ) {
			$topic = $this->get( $uid );

			if ( null === $topic ) {
				continue;
			}

			foreach ( $topic->term_ids as $term_id ) {
				$out[] = (int) $term_id;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Üst konu zinciri — en yakından uzağa, azami derinlikle.
	 *
	 * Döngüsel hiyerarşiye karşı ziyaret takibi yapılır.
	 *
	 * @return string[]
	 */
	public function ancestors( string $uid, int $max_depth = 3 ): array {
		$out     = [];
		$visited = [ $uid => true ];
		$current = $this->get( $uid );

		while ( null !== $current && null !== $current->parent_uid && count( $out ) < $max_depth ) {
			$parent_uid = $current->parent_uid;

			if ( isset( $visited[ $parent_uid ] ) ) {
				break;
			}

			$visited[ $parent_uid ] = true;
			$out[]                  = $parent_uid;
			$current                = $this->get( $parent_uid );
		}

		return $out;
	}

	/**
	 * @return Topic[]
	 */
	public function all(): array {
		return array_values( $this->by_uid );
	}

	public function count(): int {
		return count( $this->by_uid );
	}
}
