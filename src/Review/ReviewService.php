<?php
/** Tıbbi review durumuna yazan tek yetkili yol. */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Review;

use DLA\MedicalTrust\Capability\Capabilities;
use DLA\MedicalTrust\Domain\Enum\ChangeClassification;
use DLA\MedicalTrust\Domain\Enum\ReviewStatus;
use DLA\MedicalTrust\Domain\Enum\ReviewValidity;
use DLA\MedicalTrust\Meta\MetaRegistry;
use DLA\MedicalTrust\Repository\ReviewPageRepository;
use DLA\MedicalTrust\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ReviewService {

	public function __construct( private ?ReviewPageRepository $pages = null ) { $this->pages ??= new ReviewPageRepository(); }

	/**
	 * Freshness is derived from the retained review date and the page's resolved
	 * medical-topic policy. It is deliberately not persisted as review metadata.
	 */
	public function freshness_for_post( int $post_id, ?\DateTimeImmutable $now = null ): ?string {
		$page = $this->pages->find( $post_id );
		if ( null === $page ) { return null; }
		$policy = Settings::policy( $page['topic']->review_policy );

		return ExpirationEvaluator::evaluate(
			(string) get_post_meta( $post_id, MetaRegistry::PAGE_REVIEW_DATE, true ),
			$policy['interval_months'],
			$now
		);
	}

	public function record( ReviewRecordRequest $request ): ReviewOperationResult {
		$errors = $this->record_errors( $request );
		if ( ! empty( $errors ) ) { return ReviewOperationResult::failure( $errors ); }
		$page = $this->pages->find( $request->post_id );
		if ( null === $page ) { return ReviewOperationResult::failure( [ 'missing_topic' ] ); }
		$recorded_at = gmdate( 'c' );
		$event = [
			'id' => wp_generate_uuid4(), 'type' => 'review_recorded', 'review_date' => $request->review_date,
			'recorded_at' => $recorded_at, 'reviewer_expert_id' => $request->reviewer_expert_id,
			'recorded_by_user_id' => get_current_user_id(), 'content_hash' => $page['hash'],
			'signoff_reference' => $request->signoff_reference, 'status' => ReviewStatus::REVIEWED,
			'validity' => ReviewValidity::VALID, 'supersedes_event_id' => $request->supersedes_event_id,
		];
		$log = ReviewLog::append( $page['log'], $event );
		ReviewMetaGuard::write( static function () use ( $request, $page, $recorded_at, $event, $log ): void {
			update_post_meta( $request->post_id, MetaRegistry::PAGE_REVIEWER_EXPERT_ID, $request->reviewer_expert_id );
			update_post_meta( $request->post_id, MetaRegistry::PAGE_REVIEW_DATE, $request->review_date );
			update_post_meta( $request->post_id, MetaRegistry::PAGE_REVIEW_STATUS, ReviewStatus::REVIEWED );
			update_post_meta( $request->post_id, MetaRegistry::PAGE_REVIEW_VALIDITY, ReviewValidity::VALID );
			update_post_meta( $request->post_id, MetaRegistry::PAGE_RECORDED_BY_USER, $event['recorded_by_user_id'] );
			update_post_meta( $request->post_id, MetaRegistry::PAGE_RECORDED_AT, $recorded_at );
			update_post_meta( $request->post_id, MetaRegistry::PAGE_SIGNOFF_REFERENCE, $request->signoff_reference );
			update_post_meta( $request->post_id, MetaRegistry::PAGE_CONTENT_HASH, $page['hash'] );
			update_post_meta( $request->post_id, MetaRegistry::PAGE_REVIEW_LOG, $log );
		} );
		do_action( 'dla_mt/v1/review_recorded', $request->post_id, $event );

		return ReviewOperationResult::success( 'review_recorded' );
	}

	public function classify_content_change( int $post_id, ?string $classification ): ReviewOperationResult {
		if ( ! current_user_can( Capabilities::EDIT_META ) ) { return ReviewOperationResult::failure( [ 'unauthorized' ] ); }
		$page = $this->pages->find( $post_id );
		if ( null === $page ) { return ReviewOperationResult::failure( [ 'missing_topic' ] ); }
		$reviewed_hash = (string) get_post_meta( $post_id, MetaRegistry::PAGE_CONTENT_HASH, true );
		if ( '' === $reviewed_hash || hash_equals( $reviewed_hash, $page['hash'] ) ) { return ReviewOperationResult::success( 'unchanged' ); }
		if ( ! ChangeClassification::is_valid( $classification ) ) { return ReviewOperationResult::failure( [ 'classification_required' ] ); }
		if ( ChangeClassification::MINOR_EDIT === $classification ) { return ReviewOperationResult::success( 'minor_edit' ); }
		$event = [ 'id' => wp_generate_uuid4(), 'type' => 'content_superseded', 'recorded_at' => gmdate( 'c' ), 'recorded_by_user_id' => get_current_user_id(), 'content_hash' => $page['hash'], 'status' => (string) get_post_meta( $post_id, MetaRegistry::PAGE_REVIEW_STATUS, true ), 'validity' => ReviewValidity::SUPERSEDED, 'supersedes_event_id' => ( ReviewLog::latest( $page['log'] )['id'] ?? null ) ];
		ReviewMetaGuard::write( static function () use ( $post_id, $page, $event ): void {
			update_post_meta( $post_id, MetaRegistry::PAGE_REVIEW_VALIDITY, ReviewValidity::SUPERSEDED );
			update_post_meta( $post_id, MetaRegistry::PAGE_REVIEW_LOG, ReviewLog::append( $page['log'], $event ) );
		} );

		return ReviewOperationResult::success( 'medical_content_update' );
	}

	/** @return string[] */
	private function record_errors( ReviewRecordRequest $request ): array {
		$errors = [];
		if ( ! Capabilities::has_direct_review_capability( get_current_user_id() ) || ! current_user_can( Capabilities::REVIEW ) ) { $errors[] = 'unauthorized'; }
		$page = $this->pages->find( $request->post_id );
		if ( null === $page ) { $errors[] = 'missing_topic'; }
		if ( ! $this->pages->valid_expert( $request->reviewer_expert_id ) ) { $errors[] = 'missing_reviewer'; }
		if ( '' === MetaRegistry::sanitize_past_date( $request->review_date ) ) { $errors[] = 'invalid_review_date'; }
		if ( Settings::get( 'require_signoff_reference', true ) && '' === trim( $request->signoff_reference ) ) { $errors[] = 'signoff_required'; }
		if ( null !== $page && '' === $page['hash'] ) { $errors[] = 'missing_content_hash'; }

		return array_values( array_unique( $errors ) );
	}
}
