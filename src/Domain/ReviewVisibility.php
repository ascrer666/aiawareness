<?php
/**
 * The single definition of when a medical review can be represented publicly.
 *
 * Freshness is deliberately not a validity check: a due review is still a
 * valid historical review. A reviewer and a past review date are both required
 * before either M4 or a downstream contract can make the claim.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Domain;

use DLA\MedicalTrust\Domain\Enum\ReviewStatus;
use DLA\MedicalTrust\Domain\Enum\ReviewValidity;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReviewVisibility {

	/**
	 * @param array<string,mixed>|null $reviewer
	 */
	public static function is_applicable( string $status, string $validity, ?array $reviewer, string $review_date ): bool {
		return ReviewStatus::REVIEWED === $status
			&& ReviewValidity::VALID === $validity
			&& null !== $reviewer
			&& '' !== $review_date;
	}
}
