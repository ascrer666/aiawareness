<?php
/**
 * Builds the M6 canonical, read-only Medical Trust contract.
 *
 * It intentionally has no independent review or source-selection algorithm:
 * review applicability is shared with M4 and sources come only from M2's final
 * SelectionCache slots. This service does not cache its own output.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Contract;

use DLA\MedicalTrust\Domain\Enum\AuthorMode;
use DLA\MedicalTrust\Domain\Enum\ReviewStatus;
use DLA\MedicalTrust\Domain\Enum\ReviewValidity;
use DLA\MedicalTrust\Domain\Enum\SourceType;
use DLA\MedicalTrust\Domain\ReviewVisibility;
use DLA\MedicalTrust\I18n\Languages;
use DLA\MedicalTrust\Meta\MetaRegistry;
use DLA\MedicalTrust\PostTypes\ExpertPostType;
use DLA\MedicalTrust\Repository\ReviewPageRepository;
use DLA\MedicalTrust\Repository\SourceRepository;
use DLA\MedicalTrust\Repository\TopicRepository;
use DLA\MedicalTrust\Resolver\SelectionCache;
use DLA\MedicalTrust\Review\ReviewService;
use DLA\MedicalTrust\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TrustContractService {

	public const VERSION = 'dla-medical-trust/v1';

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

	public function for_post( int $post_id ): ?TrustContract {
		$page = $this->pages->find( $post_id );
		if ( null === $page ) {
			return null;
		}

		$post        = $page['post'];
		$author_mode = AuthorMode::coerce( get_post_meta( $post_id, MetaRegistry::PAGE_AUTHOR_MODE, true ) ) ?? AuthorMode::ORGANIZATION;
		$author      = AuthorMode::EXPERT === $author_mode ? $this->expert( (int) get_post_meta( $post_id, MetaRegistry::PAGE_EXPERT_ID, true ) ) : null;
		$status      = ReviewStatus::coerce( get_post_meta( $post_id, MetaRegistry::PAGE_REVIEW_STATUS, true ) ) ?? ReviewStatus::NONE;
		$stored_validity = get_post_meta( $post_id, MetaRegistry::PAGE_REVIEW_VALIDITY, true );
		$validity        = ReviewValidity::is_valid( $stored_validity ) ? (string) $stored_validity : null;
		$reviewer    = ReviewStatus::REVIEWED === $status && ReviewValidity::VALID === $validity
			? $this->expert( (int) get_post_meta( $post_id, MetaRegistry::PAGE_REVIEWER_EXPERT_ID, true ) )
			: null;
		$review_date = ReviewStatus::REVIEWED === $status && ReviewValidity::VALID === $validity
			? MetaRegistry::sanitize_past_date( get_post_meta( $post_id, MetaRegistry::PAGE_REVIEW_DATE, true ) )
			: '';
		$applicable  = ReviewVisibility::is_applicable( $status, (string) $validity, $reviewer, $review_date );
		$flags       = get_post_meta( $post_id, MetaRegistry::PAGE_DISPLAY_FLAGS, true );
		$flags       = is_array( $flags ) ? $flags : [];
		$show_commentary = ! array_key_exists( 'show_commentary', $flags ) || (bool) $flags['show_commentary'];
		$show_sources    = ! array_key_exists( 'show_sources', $flags ) || (bool) $flags['show_sources'];
		// Kontrat KANONIK gercekleri tasir; sunum bayraklari onu SILEMEZ.
		// (Sunum tarafi icin bkz. TrustDataRepository.) Tek degisiklik:
		// commentary artik tibbi inceleme kaydina bagli degil.
		$commentary      = (string) get_post_meta( $post_id, MetaRegistry::PAGE_COMMENTARY, true );

		// Icerik guncelleme tarihi: medical_review'dan AYRI anahtar.
		// Tuketici bunu dateModified'a esleyebilir; lastReviewed'a ASLA.
		$content_updated = $this->nullable_string( substr( (string) get_post_field( 'post_modified', $post_id ), 0, 10 ) );
		$organization    = $this->organization();
		$topic_data      = $this->topic_data( $post_id, $page['topic']->uid );
		$source_data     = $this->selected_sources( $post_id );

		return new TrustContract(
			[
				'contract_version' => self::VERSION,
				'content'          => [
					'post_id'       => $post_id,
					'group_uid'      => $this->nullable_string( get_post_meta( $post_id, MetaRegistry::PAGE_GROUP_UID, true ) ),
					'canonical_url'  => $this->nullable_string( get_permalink( $post_id ) ),
					'content_type'   => $post->post_type,
					'language'       => Languages::adapter()->post_language( $post_id ),
					'locale'         => (string) get_locale(),
				],
				'content_updated' => $content_updated,
				'organization'    => $organization,
				'authorship'      => [
					'mode'         => $author_mode,
					'organization' => AuthorMode::ORGANIZATION === $author_mode ? $organization : null,
					'expert'       => $author,
				],
				'reviewer'        => $applicable ? $reviewer : null,
				'medical_review'  => [
					'status'       => $status,
					'validity'     => ReviewStatus::REVIEWED === $status ? $validity : null,
					'freshness'    => $applicable ? $this->reviews->freshness_for_post( $post_id ) : null,
					'applicable'   => $applicable,
					'review_date'  => $applicable ? $review_date : null,
				],
				'topics'          => $topic_data,
				'expert_commentary' => [
					'content'              => '' !== $commentary ? $commentary : null,
					'presentation_enabled' => $show_commentary,
				],
				'sources'         => $source_data,
				'visibility'      => [
					'review_applicable'              => $applicable,
					'commentary_presentation_enabled' => $show_commentary,
					'sources_presentation_enabled'    => $show_sources,
				],
			]
		);
	}

	/** @return array<string,mixed>|null */
	private function organization(): ?array {
		$organization = Settings::get( 'organization', [] );
		$organization = is_array( $organization ) ? $organization : [];
		$name         = $this->nullable_string( $organization['name'] ?? null );
		$url          = $this->nullable_string( $organization['url'] ?? null );
		$logo_id      = (int) ( $organization['logo_id'] ?? 0 );

		if ( null === $name && null === $url && $logo_id < 1 ) {
			return null;
		}

		return [ 'name' => $name, 'url' => $url, 'logo_id' => $logo_id > 0 ? $logo_id : null ];
	}

	/** @return array<string,mixed>|null */
	private function expert( int $expert_id ): ?array {
		$expert = get_post( $expert_id );
		if ( ! $expert instanceof \WP_Post || ExpertPostType::SLUG !== $expert->post_type || 'publish' !== $expert->post_status ) {
			return null;
		}

		$profile_id  = (int) get_post_meta( $expert_id, MetaRegistry::EXPERT_PROFILE_PAGE, true );
		$profile     = get_post( $profile_id );
		$credentials = get_post_meta( $expert_id, MetaRegistry::EXPERT_CREDENTIALS, true );
		$same_as     = get_post_meta( $expert_id, MetaRegistry::EXPERT_SAMEAS, true );

		return [
			'entity_uid'  => $this->nullable_string( get_post_meta( $expert_id, MetaRegistry::EXPERT_ENTITY_UID, true ) ),
			'name'        => $this->nullable_string( $expert->post_title ),
			'honorific'   => $this->nullable_string( get_post_meta( $expert_id, MetaRegistry::EXPERT_HONORIFIC, true ) ),
			'specialty'   => $this->nullable_string( get_post_meta( $expert_id, MetaRegistry::EXPERT_JOB_TITLE, true ) ),
			'credentials' => $this->string_list( $credentials ),
			'profile_url' => $profile instanceof \WP_Post && 'publish' === $profile->post_status ? $this->nullable_string( get_permalink( $profile_id ) ) : null,
			'same_as'     => $this->string_list( $same_as ),
		];
	}

	/** @return array{primary:array<string,mixed>,secondary:array<int,array<string,mixed>>} */
	private function topic_data( int $post_id, string $primary_uid ): array {
		$graph     = $this->topics->graph();
		$language  = Languages::adapter()->post_language( $post_id );
		$primary   = $this->topic( $graph->get( $primary_uid ), $language );
		$secondary = [];

		$terms = get_the_terms( $post_id, \DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy::SLUG );
		foreach ( $graph->uids_for_terms( is_array( $terms ) ? array_map( static fn( \WP_Term $term ): int => $term->term_id, $terms ) : [] ) as $uid ) {
			if ( $uid === $primary_uid ) {
				continue;
			}
			$topic = $this->topic( $graph->get( $uid ), $language );
			if ( null !== $topic ) {
				$secondary[] = $topic;
			}
		}

		return [ 'primary' => $primary, 'secondary' => $secondary ];
	}

	/** @return array<string,mixed>|null */
	private function topic( ?\DLA\MedicalTrust\Domain\Topic $topic, string $language ): ?array {
		if ( null === $topic ) {
			return null;
		}

		$label = $topic->name;
		foreach ( $topic->term_ids as $term_id ) {
			if ( Languages::adapter()->term_language( $term_id ) === $language ) {
				$term = get_term( $term_id );
				$label = $term instanceof \WP_Term ? $term->name : $label;
				break;
			}
		}

		return [ 'uid' => $topic->uid, 'label' => $label, 'schema_type_hint' => $topic->schema_type_hint ];
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
				'source_uid'       => $source->uid,
				'type'             => $source->type,
				'title'            => $source->title,
				'canonical_url'    => $source->canonical_url,
				'publisher'        => $this->nullable_string( $source->publisher ),
				'journal'          => $this->nullable_string( $source->journal ),
				'publication_year' => $source->pub_year > 0 ? $source->pub_year : null,
				'publication_type' => $source->publication_type,
				'peer_reviewed'    => $source->peer_reviewed,
				'identifiers'      => [
					'doi'    => $this->nullable_string( get_post_meta( $source_id, MetaRegistry::SOURCE_DOI, true ) ),
					'pmid'   => $this->nullable_string( get_post_meta( $source_id, MetaRegistry::SOURCE_PMID, true ) ),
					'pmc_id' => $this->nullable_string( get_post_meta( $source_id, MetaRegistry::SOURCE_PMC_ID, true ) ),
				],
			];
		}

		return $result;
	}

	private function nullable_string( $value ): ?string {
		$value = trim( (string) $value );

		return '' === $value ? null : $value;
	}

	/** @return string[] */
	private function string_list( $value ): array {
		if ( ! is_array( $value ) ) {
			return [];
		}

		return array_values( array_filter( array_map( static fn( $item ): string => trim( (string) $item ), $value ) ) );
	}
}
