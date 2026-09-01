<?php
/**
 * Tam çözümleme sonucu.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Domain\Resolution;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ResolutionResult {

	/**
	 * @param array<string,SlotResult>  $slots
	 * @param array<string,int>         $proximity_map uid => yakınlık puanı
	 * @param array<string,string>      $rung_map      uid => basamak etiketi
	 */
	public function __construct(
		public int $post_id,
		public string $seed_key,
		public array $slots,
		public array $proximity_map,
		public array $rung_map,
		public int $min_topic_proximity,
		public ?string $primary_topic_uid
	) {}

	/**
	 * Cache'e yazılacak sadeleştirilmiş biçim: yalnızca seçilen ID'ler.
	 *
	 * @return array<string,int>
	 */
	public function selected_ids(): array {
		$out = [];

		foreach ( $this->slots as $slot => $result ) {
			$out[ $slot ] = $result->selected instanceof \DLA\MedicalTrust\Domain\Source
				? $result->selected->id
				: 0;
		}

		return $out;
	}

	public function filled_slot_count(): int {
		return count( array_filter( $this->slots, static fn( SlotResult $s ): bool => $s->is_filled() ) );
	}
}
