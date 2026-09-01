<?php
/**
 * Tipli meta kaydı — tek doğruluk kaynağı (v0.1 §6, v0.2 §3/§6, Addendum A §5).
 *
 * Her alan: tip + sanitize_callback + auth_callback. `show_in_rest` istisnasız
 * false — herkese açık REST yüzeyi yok (v0.1 §13).
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Meta;

use DLA\MedicalTrust\Capability\Capabilities;
use DLA\MedicalTrust\Domain\Enum\AuthorMode;
use DLA\MedicalTrust\Domain\Enum\DiscoveredVia;
use DLA\MedicalTrust\Domain\Enum\PublicationType;
use DLA\MedicalTrust\Domain\Enum\ReviewPolicy;
use DLA\MedicalTrust\Domain\Enum\ReviewStatus;
use DLA\MedicalTrust\Domain\Enum\ReviewValidity;
use DLA\MedicalTrust\Domain\Enum\SchemaTypeHint;
use DLA\MedicalTrust\Domain\Enum\SourceHealth;
use DLA\MedicalTrust\PostTypes\ExpertPostType;
use DLA\MedicalTrust\PostTypes\SourcePostType;
use DLA\MedicalTrust\Settings\Settings;
use DLA\MedicalTrust\Support\Sanitizer;
use DLA\MedicalTrust\Support\UrlPolicy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MetaRegistry {

	/* ---------------------------------------------------------- Expert */

	public const EXPERT_ENTITY_UID     = '_dla_entity_uid';
	public const EXPERT_HONORIFIC      = '_dla_honorific';
	public const EXPERT_JOB_TITLE      = '_dla_job_title';
	public const EXPERT_CREDENTIALS    = '_dla_credentials';
	public const EXPERT_PROFILE_PAGE   = '_dla_profile_page_id';
	public const EXPERT_SAMEAS         = '_dla_sameas';
	public const EXPERT_SHORT_BIO      = '_dla_short_bio';

	/* ---------------------------------------------------------- Source */

	public const SOURCE_UID              = '_dla_source_uid';
	public const SOURCE_URL              = '_dla_url';
	public const SOURCE_PUBLISHER_URL    = '_dla_publisher_url';
	public const SOURCE_DOI              = '_dla_doi';
	public const SOURCE_PMID             = '_dla_pmid';
	public const SOURCE_PMC_ID           = '_dla_pmc_id';
	public const SOURCE_PUBLISHER        = '_dla_publisher';
	public const SOURCE_JOURNAL          = '_dla_journal';
	public const SOURCE_AUTHORS          = '_dla_authors';
	public const SOURCE_PUB_YEAR         = '_dla_pub_year';
	public const SOURCE_LANG             = '_dla_lang';
	public const SOURCE_PRIORITY         = '_dla_priority';
	public const SOURCE_PEER_REVIEWED    = '_dla_peer_reviewed';
	public const SOURCE_PUBLICATION_TYPE = '_dla_publication_type';
	public const SOURCE_DISCOVERED_VIA   = '_dla_discovered_via';
	public const SOURCE_DISCOVERED_AT    = '_dla_discovered_at';
	public const SOURCE_VERIFIED_AT      = '_dla_verified_at';
	public const SOURCE_RELEVANCE_NOTE   = '_dla_relevance_note';
	public const SOURCE_HEALTH           = '_dla_health';

	/* ----------------------------------------------------------- Topic */

	public const TOPIC_UID            = '_dla_topic_uid';
	public const TOPIC_DEFAULT_EXPERT = '_dla_default_expert_id';
	public const TOPIC_REVIEW_POLICY  = '_dla_review_policy';
	public const TOPIC_RELATED_UIDS   = '_dla_related_topic_uids';
	public const TOPIC_SCHEMA_HINT    = '_dla_schema_type_hint';
	public const TOPIC_DIVERSITY_BAND = '_dla_diversity_band';

	/* ------------------------------------------------ Page (içerik) */

	public const PAGE_GROUP_UID          = '_dla_page_group_uid';
	public const PAGE_AUTHOR_MODE        = '_dla_author_mode';
	public const PAGE_EXPERT_ID          = '_dla_expert_id';
	public const PAGE_REVIEWER_EXPERT_ID = '_dla_reviewer_expert_id';
	public const PAGE_PRIMARY_TOPIC_UID  = '_dla_primary_topic_uid';
	public const PAGE_REVIEW_DATE        = '_dla_review_date';
	public const PAGE_REVIEW_STATUS      = '_dla_review_status';
	public const PAGE_REVIEW_VALIDITY    = '_dla_review_validity';
	public const PAGE_RECORDED_BY_USER   = '_dla_recorded_by_user_id';
	public const PAGE_RECORDED_AT        = '_dla_recorded_at';
	public const PAGE_SIGNOFF_REFERENCE  = '_dla_signoff_reference';
	public const PAGE_CONTENT_HASH       = '_dla_content_hash_at_review';
	public const PAGE_COMMENTARY         = '_dla_commentary';
	public const PAGE_SOURCE_OVERRIDES   = '_dla_source_overrides';
	public const PAGE_DISPLAY_FLAGS      = '_dla_display_flags';
	public const PAGE_RESOLVED_SOURCES   = '_dla_resolved_sources';
	public const PAGE_REVIEW_LOG         = '_dla_review_log';

	public function register(): void {
		$this->register_expert_meta();
		$this->register_source_meta();
		$this->register_topic_meta();
		$this->register_page_meta();
	}

	/* ================================================================= */

	private function register_expert_meta(): void {
		$auth = static fn(): bool => current_user_can( Capabilities::MANAGE_EXPERTS );
		$pt   = ExpertPostType::SLUG;

		$this->post_meta( $pt, self::EXPERT_ENTITY_UID, 'string', [ self::class, 'sanitize_uid' ], $auth );
		$this->post_meta( $pt, self::EXPERT_HONORIFIC, 'string', [ Sanitizer::class, 'text' ], $auth );
		$this->post_meta( $pt, self::EXPERT_JOB_TITLE, 'string', [ Sanitizer::class, 'text' ], $auth );
		$this->post_meta( $pt, self::EXPERT_CREDENTIALS, 'array', [ Sanitizer::class, 'string_list' ], $auth );
		$this->post_meta( $pt, self::EXPERT_SAMEAS, 'array', [ Sanitizer::class, 'url_list' ], $auth );
		$this->post_meta( $pt, self::EXPERT_SHORT_BIO, 'string', [ Sanitizer::class, 'restricted_html' ], $auth );
		$this->post_meta(
			$pt,
			self::EXPERT_PROFILE_PAGE,
			'integer',
			static fn( $value ): int => Sanitizer::content_post_id( $value, Settings::eligible_post_types() ),
			$auth
		);
	}

	private function register_source_meta(): void {
		$auth = static fn(): bool => current_user_can( Capabilities::MANAGE_SOURCES );
		$pt   = SourcePostType::SLUG;

		$this->post_meta( $pt, self::SOURCE_UID, 'string', [ self::class, 'sanitize_uid' ], $auth );
		$this->post_meta( $pt, self::SOURCE_URL, 'string', [ self::class, 'sanitize_url' ], $auth );
		$this->post_meta( $pt, self::SOURCE_PUBLISHER_URL, 'string', [ self::class, 'sanitize_url' ], $auth );
		$this->post_meta( $pt, self::SOURCE_DOI, 'string', [ self::class, 'sanitize_doi' ], $auth );
		$this->post_meta( $pt, self::SOURCE_PMID, 'string', [ self::class, 'sanitize_pmid' ], $auth );
		$this->post_meta( $pt, self::SOURCE_PMC_ID, 'string', [ self::class, 'sanitize_pmc' ], $auth );
		$this->post_meta( $pt, self::SOURCE_PUBLISHER, 'string', [ Sanitizer::class, 'text' ], $auth );
		$this->post_meta( $pt, self::SOURCE_JOURNAL, 'string', [ Sanitizer::class, 'text' ], $auth );
		$this->post_meta( $pt, self::SOURCE_AUTHORS, 'string', [ Sanitizer::class, 'text' ], $auth );
		$this->post_meta( $pt, self::SOURCE_RELEVANCE_NOTE, 'string', [ Sanitizer::class, 'textarea' ], $auth );
		$this->post_meta( $pt, self::SOURCE_LANG, 'string', [ Sanitizer::class, 'language_code' ], $auth );
		$this->post_meta( $pt, self::SOURCE_PUB_YEAR, 'integer', [ Sanitizer::class, 'publication_year' ], $auth );
		$this->post_meta( $pt, self::SOURCE_PEER_REVIEWED, 'boolean', [ Sanitizer::class, 'bool' ], $auth );

		$this->post_meta(
			$pt,
			self::SOURCE_PRIORITY,
			'integer',
			static fn( $value ): int => Sanitizer::int_in_range( $value, 0, 20, 0 ),
			$auth
		);

		$this->post_meta(
			$pt,
			self::SOURCE_PUBLICATION_TYPE,
			'string',
			static fn( $value ): string => (string) Sanitizer::enum( $value, PublicationType::class ),
			$auth
		);

		// İdari köken. ScoringPolicy DTO'suna ASLA eklenmez (Addendum A §3).
		$this->post_meta(
			$pt,
			self::SOURCE_DISCOVERED_VIA,
			'string',
			static fn( $value ): string => (string) Sanitizer::enum( $value, DiscoveredVia::class ),
			$auth
		);

		$this->post_meta( $pt, self::SOURCE_DISCOVERED_AT, 'string', [ self::class, 'sanitize_past_date' ], $auth );
		$this->post_meta( $pt, self::SOURCE_VERIFIED_AT, 'string', [ self::class, 'sanitize_past_date' ], $auth );

		// M1'de daima `unknown`. Alan şimdi kayıtlı ki Phase 2 sağlık
		// kontrolü migration gerektirmesin.
		$this->post_meta(
			$pt,
			self::SOURCE_HEALTH,
			'string',
			static fn( $value ): string => (string) Sanitizer::enum( $value, SourceHealth::class ),
			$auth
		);
	}

	private function register_topic_meta(): void {
		$auth     = static fn(): bool => current_user_can( Capabilities::MANAGE_SOURCES );
		$taxonomy = \DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy::SLUG;

		$this->term_meta( $taxonomy, self::TOPIC_UID, 'string', [ self::class, 'sanitize_uid' ], $auth );
		$this->term_meta( $taxonomy, self::TOPIC_RELATED_UIDS, 'array', [ self::class, 'sanitize_uid_list' ], $auth );

		$this->term_meta(
			$taxonomy,
			self::TOPIC_DEFAULT_EXPERT,
			'integer',
			static fn( $value ): int => Sanitizer::post_id_of_type( $value, ExpertPostType::SLUG ),
			$auth
		);

		$this->term_meta(
			$taxonomy,
			self::TOPIC_REVIEW_POLICY,
			'string',
			static fn( $value ): string => (string) Sanitizer::enum( $value, ReviewPolicy::class ),
			$auth
		);

		$this->term_meta(
			$taxonomy,
			self::TOPIC_SCHEMA_HINT,
			'string',
			static fn( $value ): string => (string) Sanitizer::enum( $value, SchemaTypeHint::class ),
			$auth
		);

		// Tam sayı geçersiz kılma. Devralma "boş dize" ile değil, meta'nın
		// HİÇ BULUNMAMASI ile ifade edilir — bkz. TopicRepository::band_for().
		$this->term_meta(
			$taxonomy,
			self::TOPIC_DIVERSITY_BAND,
			'integer',
			static fn( $value ): int => Sanitizer::int_in_range( $value, 0, 200, 10 ),
			$auth
		);
	}

	private function register_page_meta(): void {
		$edit   = static fn(): bool => current_user_can( Capabilities::EDIT_META );
		// Review alanları REST/meta UI ile yazılamaz. M3'te tek yazma yolu
		// ReviewService + ReviewMetaGuard bağlamıdır; doğrudan meta auth'ı
		// hiçbir kullanıcıya açmak bu sınırı delerdi.
		$review = static fn(): bool => false;

		foreach ( Settings::eligible_post_types() as $pt ) {
			$this->post_meta( $pt, self::PAGE_GROUP_UID, 'string', [ self::class, 'sanitize_uid' ], $edit );

			$this->post_meta(
				$pt,
				self::PAGE_AUTHOR_MODE,
				'string',
				static fn( $value ): string => (string) Sanitizer::enum( $value, AuthorMode::class ),
				$edit
			);

			$this->post_meta(
				$pt,
				self::PAGE_EXPERT_ID,
				'integer',
				static fn( $value ): int => Sanitizer::post_id_of_type( $value, ExpertPostType::SLUG ),
				$edit
			);

			$this->post_meta(
				$pt,
				self::PAGE_REVIEWER_EXPERT_ID,
				'integer',
				static fn( $value ): int => Sanitizer::post_id_of_type( $value, ExpertPostType::SLUG ),
				$review
			);

			$this->post_meta( $pt, self::PAGE_PRIMARY_TOPIC_UID, 'string', [ self::class, 'sanitize_uid' ], $edit );
			$this->post_meta( $pt, self::PAGE_COMMENTARY, 'string', [ Sanitizer::class, 'restricted_html' ], $edit );

			$this->post_meta(
				$pt,
				self::PAGE_REVIEW_VALIDITY,
				'string',
				static fn( $value ): string => (string) Sanitizer::enum( $value, ReviewValidity::class ),
				$review
			);

			// --- Yalnızca inceleme yetkisi olan kullanıcı yazabilir ---

			$this->post_meta(
				$pt,
				self::PAGE_REVIEW_STATUS,
				'string',
				static fn( $value ): string => (string) Sanitizer::enum( $value, ReviewStatus::class ),
				$review
			);

			$this->post_meta( $pt, self::PAGE_REVIEW_DATE, 'string', [ self::class, 'sanitize_past_date' ], $review );
			$this->post_meta( $pt, self::PAGE_RECORDED_AT, 'string', [ Sanitizer::class, 'text' ], $review );
			$this->post_meta( $pt, self::PAGE_SIGNOFF_REFERENCE, 'string', [ Sanitizer::class, 'textarea' ], $review );
			$this->post_meta( $pt, self::PAGE_CONTENT_HASH, 'string', [ self::class, 'sanitize_hash' ], $review );
			$this->post_meta(
				$pt,
				self::PAGE_RECORDED_BY_USER,
				'integer',
				static fn( $value ): int => absint( $value ),
				$review
			);
			$this->post_meta( $pt, self::PAGE_REVIEW_LOG, 'array', [ self::class, 'passthrough_array' ], $review );

			// --- Sistem tarafından yazılan alanlar (M2) ---

			$this->post_meta( $pt, self::PAGE_SOURCE_OVERRIDES, 'array', [ self::class, 'passthrough_array' ], $edit );
			$this->post_meta( $pt, self::PAGE_DISPLAY_FLAGS, 'array', [ self::class, 'passthrough_array' ], $edit );
			$this->post_meta( $pt, self::PAGE_RESOLVED_SOURCES, 'array', [ self::class, 'passthrough_array' ], $edit );
		}
	}

	/* ============================================ sanitize yardımcıları */

	public static function sanitize_uid( $value ): string {
		$value = strtolower( trim( (string) $value ) );

		return preg_match( '#^(exp|top|src|grp)_[0-9a-f]{12}$#', $value ) ? $value : '';
	}

	/**
	 * @return string[]
	 */
	public static function sanitize_uid_list( $value ): array {
		$items = is_array( $value ) ? $value : preg_split( '/[\s,]+/', (string) $value );
		$out   = [];

		foreach ( (array) $items as $item ) {
			$uid = self::sanitize_uid( $item );
			if ( '' !== $uid ) {
				$out[] = $uid;
			}
		}

		return array_values( array_unique( $out ) );
	}

	public static function sanitize_url( $value ): string {
		$result = UrlPolicy::validate( (string) $value );

		return $result['valid'] ? $result['url'] : '';
	}

	public static function sanitize_doi( $value ): string {
		return UrlPolicy::normalize_doi( (string) $value ) ?? '';
	}

	public static function sanitize_pmid( $value ): string {
		return UrlPolicy::normalize_pmid( (string) $value ) ?? '';
	}

	public static function sanitize_pmc( $value ): string {
		return UrlPolicy::normalize_pmc_id( (string) $value ) ?? '';
	}

	public static function sanitize_past_date( $value ): string {
		return Sanitizer::date( $value, false );
	}

	public static function sanitize_hash( $value ): string {
		$value = strtolower( trim( (string) $value ) );

		return preg_match( '#^[0-9a-f]{64}$#', $value ) ? $value : '';
	}

	/**
	 * Dizi alanları M2'de kendi servisleri tarafından yazılır; burada
	 * yalnızca tip güvenliği sağlanır.
	 */
	public static function passthrough_array( $value ): array {
		return is_array( $value ) ? $value : [];
	}

	/* ================================================ kayıt yardımcıları */

	private function post_meta( string $post_type, string $key, string $type, callable $sanitize, callable $auth ): void {
		register_post_meta(
			$post_type,
			$key,
			[
				'type'              => $type,
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => $sanitize,
				'auth_callback'     => $auth,
			]
		);
	}

	private function term_meta( string $taxonomy, string $key, string $type, callable $sanitize, callable $auth ): void {
		register_term_meta(
			$taxonomy,
			$key,
			[
				'type'              => $type,
				'single'            => true,
				'show_in_rest'      => false,
				'sanitize_callback' => $sanitize,
				'auth_callback'     => $auth,
			]
		);
	}
}
