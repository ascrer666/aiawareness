<?php
/** @package DLA\MedicalTrust */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Review;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ReviewRecordRequest {

	public function __construct(
		public int $post_id,
		public int $reviewer_expert_id,
		public string $review_date,
		public string $signoff_reference = '',
		public ?string $supersedes_event_id = null
	) {}
}
