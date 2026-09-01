<?php
/**
 * Bir slotun çözümleme sonucu.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Domain\Resolution;

use DLA\MedicalTrust\Domain\Source;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SlotResult {

	/**
	 * @param Candidate[] $candidates Tüm değerlendirilmiş adaylar (iz).
	 */
	public function __construct(
		public string $slot,
		public ?Source $selected,
		public array $candidates,
		public int $band,
		public int $max_tier_size,
		public int $tier_size,
		public bool $override_applied = false,
		public ?string $override_rejected_reason = null
	) {}

	public function is_filled(): bool {
		return null !== $this->selected;
	}

	public function eligible_count(): int {
		return count(
			array_filter(
				$this->candidates,
				static fn( Candidate $c ): bool => null === $c->rejected_reason || $c->in_tier
			)
		);
	}

	/**
	 * @return Candidate[]
	 */
	public function tier(): array {
		return array_values( array_filter( $this->candidates, static fn( Candidate $c ): bool => $c->in_tier ) );
	}
}
