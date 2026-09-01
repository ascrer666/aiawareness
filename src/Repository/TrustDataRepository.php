<?php
/**
 * Reads M1-M3 facts for M4. Source scoring remains in M2's SelectionCache;
 * this repository only hydrates its selected IDs for presentation.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Repository;

use DLA\MedicalTrust\Domain\Enum\AuthorMode;
use DLA\MedicalTrust\Domain\Enum\ReviewStatus;
use DLA\MedicalTrust\Domain\Enum\ReviewValidity;
use DLA\MedicalTrust\Domain\Enum\SourceType;
use DLA\MedicalTrust\Domain\TrustData;
use DLA\MedicalTrust\Meta\MetaRegistry;
use DLA\MedicalTrust\PostTypes\ExpertPostType;
use DLA\MedicalTrust\Review\ReviewService;
use DLA\MedicalTrust\Resolver\SelectionCache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TrustDataRepository {

	public function __construct(
		private ?ReviewPageRepository $pages = null,
		private ?SelectionCache $selection_cache = null,
		private ?SourceRepository $sources = null,
		private ?TopicRepository $topics = null,
		private ?ReviewService $reviews = null
	) {
		$this->pages           ??= new ReviewPageRepository();
		$this->selection_cache ??= new SelectionCache();
		$this->sources         ??= new SourceRepository();
		$this->topics          ??= new TopicRepository();
		$this->reviews         ??= new ReviewService( $this->pages );
	}

	public function is_medical_post( int $post_id ): bool {
		return null !== $this->pages->find( $post_id );
	}

	public function for_post( int $post_id ): ?TrustData {
		$page = $this->pages->find( $post_id );
		if ( null === $page ) {
			return null;
		}

		$author_mode = AuthorMode::coerce( get_post_meta( $post_id, MetaRegistry::PAGE_AUTHOR_MODE, true ) ) ?? AuthorMode::ORGANIZATION;
		$author      = AuthorMode::EXPERT === $author_mode
			? $this->expert( (int) get_post_meta( $post_id, MetaRegistry::PAGE_EXPERT_ID, true ) )
			: null;
		$status      = (string) get_post_meta( $post_id, MetaRegistry::PAGE_REVIEW_STATUS, true );
		$validity    = (string) get_post_meta( $post_id, MetaRegistry::PAGE_REVIEW_VALIDITY, true );
		$valid_review = ReviewStatus::REVIEWED === $status && ReviewValidity::VALID === $validity;
		$reviewer    = $valid_review
			? $this->expert( (int) get_post_meta( $post_id, MetaRegistry::PAGE_REVIEWER_EXPERT_ID, true ) )
			: null;
		$review_date = $valid_review ? MetaRegistry::sanitize_past_date( get_post_meta( $post_id, MetaRegistry::PAGE_REVIEW_DATE, true ) ) : '';

		// A review without a valid expert or valid date must not be displayed as a review.
		if ( null === $reviewer || '' === $review_date ) {
			$reviewer    = null;
			$review_date = '';
		}

		$flags = get_post_meta( $post_id, MetaRegistry::PAGE_DISPLAY_FLAGS, true );
		$flags = is_array( $flags ) ? $flags : [];
		$show_commentary = ! array_key_exists( 'show_commentary', $flags ) || (bool) $flags['show_commentary'];
		$show_sources = ! array_key_exists( 'show_sources', $flags ) || (bool) $flags['show_sources'];
		$commentary = null !== $reviewer && $show_commentary ? (string) get_post_meta( $post_id, MetaRegistry::PAGE_COMMENTARY, true ) : '';
		$data = new TrustData(
			$post_id,
			$author_mode,
			$author,
			$reviewer,
			$reviewer ?? $author,
			'' !== $review_date ? $review_date : null,
			'' !== $review_date ? $this->reviews->freshness_for_post( $post_id ) : null,
			$status,
			$validity,
			$commentary,
			$show_sources ? $this->selected_sources( $post_id ) : []
		);

		return $data->has_visible_facts() ? $data : null;
	}

	/** @return array<string,mixed>|null */
	private function expert( int $expert_id ): ?array {
		$expert = get_post( $expert_id );
		if ( ! $expert instanceof \WP_Post || ExpertPostType::SLUG !== $expert->post_type || 'publish' !== $expert->post_status ) {
			return null;
		}
		$profile_id = (int) get_post_meta( $expert_id, MetaRegistry::EXPERT_PROFILE_PAGE, true );
		$profile    = get_post( $profile_id );
		$profile_url = $profile instanceof \WP_Post && 'publish' === $profile->post_status ? (string) get_permalink( $profile_id ) : '';
		$honorific  = trim( (string) get_post_meta( $expert_id, MetaRegistry::EXPERT_HONORIFIC, true ) );
		$name       = trim( $honorific . ' ' . $expert->post_title );

		return [
			'id'          => $expert_id,
			'name'        => $name,
			'specialty'   => (string) get_post_meta( $expert_id, MetaRegistry::EXPERT_JOB_TITLE, true ),
			'profile_url' => $profile_url,
			'image_id'    => (int) get_post_thumbnail_id( $expert_id ),
		];
	}

	/** @return array<int,array<string,mixed>> */
	private function selected_sources( int $post_id ): array {
		$graph  = $this->topics->graph();
		$ids    = $this->selection_cache->get( $post_id );
		$result = [];

		foreach ( SourceType::values() as $slot ) {
			$source_id = (int) ( $ids[ $slot ] ?? 0 );
			if ( $source_id < 1 || 'publish' !== $this->sources->status_of( $source_id ) ) {
				continue;
			}
			$source = $this->sources->find( $source_id, $graph );
			if ( null === $source || $slot !== $source->type || ! $source->is_eligible() || null === $source->canonical_url ) {
				continue;
			}
			$result[] = [
				'id'        => $source->id,
				'title'     => $source->title,
				'type'      => $source->type,
				'url'       => $source->canonical_url,
				'publisher' => $source->publisher,
				'journal'   => $source->journal,
				'year'      => $source->pub_year,
			];
		}

		return $result;
	}
}
