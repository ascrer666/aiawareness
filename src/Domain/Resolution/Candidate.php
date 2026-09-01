<?php
/**
 * Değerlendirilmiş aday — skor bileşenleri ve karar izi.
 *
 * Çözümleme her zaman bu izi üretir; açıklama paneli yalnızca OKUR.
 * "Açıklama modu" diye ayrı bir kod yolu yoktur — panelin sonucu
 * etkilemesi böylece yapısal olarak imkânsızdır.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Domain\Resolution;

use DLA\MedicalTrust\Domain\Source;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Candidate {

	public const REJECT_INELIGIBLE   = 'ineligible';
	public const REJECT_PROXIMITY    = 'below_min_proximity';
	public const REJECT_SLOT         = 'slot_mismatch';
	public const REJECT_OUT_OF_BAND  = 'outside_diversity_band';
	public const REJECT_TIER_TRIM    = 'trimmed_by_max_tier_size';
	public const REJECT_NOT_SELECTED = 'lost_rendezvous';

	/**
	 * @param array<string,int> $components Skor bileşenleri.
	 */
	public function __construct(
		public Source $source,
		public int $proximity,
		public string $proximity_rung,
		public ?string $matched_topic_uid,
		public int $score,
		public array $components,
		public ?string $rejected_reason = null,
		public bool $in_tier = false,
		public bool $selected = false,
		public ?int $rendezvous_weight = null
	) {}

	public function reject( string $reason ): self {
		$this->rejected_reason = $reason;

		return $this;
	}
}
