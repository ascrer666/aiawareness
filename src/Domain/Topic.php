<?php
/**
 * Tıbbi konu — değişmez değer nesnesi.
 *
 * Tüm ilişkiler term_id ile değil UID ile kurulur; çeviri grupları böylece
 * tek bir konu olarak davranır.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Topic {

	/**
	 * @param int[]    $term_ids     Tüm dillerdeki term ID'leri.
	 * @param string[] $related_uids
	 */
	public function __construct(
		public string $uid,
		public string $name,
		public string $slug,
		public array $term_ids,
		public ?string $parent_uid,
		public array $related_uids,
		public string $review_policy,
		public ?int $diversity_band,
		public int $default_expert_id,
		public ?string $schema_type_hint
	) {}

	/**
	 * Genel havuz konusu mu? (yakınlık merdiveninin en alt basamağı)
	 */
	public function is_global_pool(): bool {
		return 'general' === $this->slug;
	}

	/**
	 * Konu kendi bandını geçersiz kılmıyorsa global değer devralınır.
	 * Devralma "boş dize" ile değil, null ile ifade edilir.
	 */
	public function band( int $global_band ): int {
		return null === $this->diversity_band ? $global_band : $this->diversity_band;
	}
}
