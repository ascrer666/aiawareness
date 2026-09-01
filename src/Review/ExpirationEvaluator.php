<?php
/** @package DLA\MedicalTrust */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Review;

use DLA\MedicalTrust\Domain\Enum\ReviewFreshness;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ExpirationEvaluator {

	public static function evaluate( ?string $review_date, int $interval_months, ?\DateTimeImmutable $now = null ): ?string {
		$date = is_string( $review_date ) ? \DateTimeImmutable::createFromFormat( '!Y-m-d', $review_date, new \DateTimeZone( 'UTC' ) ) : false;
		if ( false === $date || $interval_months < 1 ) {
			return null;
		}

		$due_at = $date->add( new \DateInterval( 'P' . $interval_months . 'M' ) );
		$now    = $now ?? new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );

		return $now >= $due_at ? ReviewFreshness::DUE : ReviewFreshness::CURRENT;
	}
}
