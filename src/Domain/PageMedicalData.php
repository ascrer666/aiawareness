<?php
/**
 * Sayfa düzeyi tıbbi veri — çözümleyicinin girdisi.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PageMedicalData {

	/**
	 * @param string[]           $topic_uids Sayfaya atanmış konular (UID).
	 * @param array<string,int>  $source_overrides slot => kaynak post ID
	 */
	public function __construct(
		public int $post_id,
		public string $group_uid,
		public array $topic_uids,
		public ?string $primary_topic_uid,
		public array $source_overrides
	) {}

	/**
	 * Rendezvous seed'inin sayfa bileşeni.
	 *
	 * Grup kimliği varsa o kullanılır — çeviriler aynı kaynakları gösterir
	 * ve bir çevirinin silinmesi kalanları etkilemez. Yoksa post ID'ye
	 * düşülür (tek dilli kurulum veya kimliği henüz üretilmemiş sayfa).
	 */
	public function seed_key(): string {
		return '' !== $this->group_uid ? $this->group_uid : 'post:' . $this->post_id;
	}

	/**
	 * Birincil konu belirlenmemişse ilk konu kullanılır.
	 */
	public function effective_primary_topic(): ?string {
		if ( null !== $this->primary_topic_uid && in_array( $this->primary_topic_uid, $this->topic_uids, true ) ) {
			return $this->primary_topic_uid;
		}

		return $this->topic_uids[0] ?? null;
	}

	/**
	 * Birincil dışındaki konular.
	 *
	 * @return string[]
	 */
	public function secondary_topics(): array {
		$primary = $this->effective_primary_topic();

		if ( null === $primary ) {
			return [];
		}

		return array_values( array_filter( $this->topic_uids, static fn( string $uid ): bool => $uid !== $primary ) );
	}

	public function is_medical(): bool {
		return ! empty( $this->topic_uids );
	}
}
