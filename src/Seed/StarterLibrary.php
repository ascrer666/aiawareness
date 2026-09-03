<?php
/**
 * Başlangıç kaynak kütüphanesi.
 *
 * Kaynak seçimi motoru çalışıyor ama kütüphane boşken hiçbir şey seçemiyor.
 * Bu sınıf, konularla eşleşen GERÇEK ve DOĞRULANMIŞ otorite/akademik
 * kaynakları tek tıkla oluşturur.
 *
 * ────────────────────────────────────────────────────────────────────────
 *  KAPSAM DÜRÜSTLÜĞÜ
 *
 *  - Yalnızca `authority` ve `academic` slotları doldurulur. Bunlar mesleki
 *    kuruluşların ve klinik kurumların kalıcı, doğrulanabilir sayfalarıdır.
 *
 *  - `publication` (hakemli bilimsel yayın) slotu için VERİ ÜRETİLMEZ.
 *    Konuya gerçekten uygun bir yayın seçmek küratörlük ister; DOI uydurmak
 *    sistemin tüm amacını bozar. O slot boş kalır ve kutu onsuz render edilir.
 *
 *  - Kayıtlar `pending` durumunda oluşturulur — CPT'nin zaten tanımlı olan
 *    "aday, onay bekliyor" semantiği. Yayımlamadan önce siz görürsünüz;
 *    `pending` kayıtlar hiçbir sayfada görünmez.
 * ────────────────────────────────────────────────────────────────────────
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Seed;

use DLA\MedicalTrust\Domain\Enum\DiscoveredVia;
use DLA\MedicalTrust\Domain\Enum\PublicationType;
use DLA\MedicalTrust\Domain\Enum\SourceHealth;
use DLA\MedicalTrust\Domain\Enum\SourceType;
use DLA\MedicalTrust\Meta\MetaRegistry;
use DLA\MedicalTrust\PostTypes\SourcePostType;
use DLA\MedicalTrust\Repository\TopicRepository;
use DLA\MedicalTrust\Settings\Settings;
use DLA\MedicalTrust\Support\UrlPolicy;
use DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy;
use DLA\MedicalTrust\Taxonomies\SourceTypeTaxonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class StarterLibrary {

	/** Version of the built-in verified catalog, not the plugin version. */
	public const CATALOG_VERSION = '0.6.1';
	public const OPTION_LAST_SYNC = 'dla_mt_catalog_sync_report';

	/**
	 * Katalog. Her URL 2026-09 tarihinde HTTP 200 ile doğrulanmıştır.
	 *
	 * SAF: WordPress fonksiyonu çağırmaz.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function catalog(): array {
		return [
			/* ---------------------------------------- ASPS (authority) */
			[
				'title'     => 'ASPS — Rhinoplasty',
				'url'       => 'https://www.plasticsurgery.org/cosmetic-procedures/rhinoplasty',
				'type'      => SourceType::AUTHORITY,
				'publisher' => 'American Society of Plastic Surgeons',
				'via'       => DiscoveredVia::PROFESSIONAL_SOCIETY,
				'keywords'  => [ 'rinoplasti', 'burun', 'nose', 'rhinoplasty' ],
			],
			[
				'title'     => 'ASPS — Breast Augmentation',
				'url'       => 'https://www.plasticsurgery.org/cosmetic-procedures/breast-augmentation',
				'type'      => SourceType::AUTHORITY,
				'publisher' => 'American Society of Plastic Surgeons',
				'via'       => DiscoveredVia::PROFESSIONAL_SOCIETY,
				'keywords'  => [ 'meme buyutme', 'meme', 'gogus', 'breast' ],
			],
			[
				'title'     => 'ASPS — Liposuction',
				'url'       => 'https://www.plasticsurgery.org/cosmetic-procedures/liposuction',
				'type'      => SourceType::AUTHORITY,
				'publisher' => 'American Society of Plastic Surgeons',
				'via'       => DiscoveredVia::PROFESSIONAL_SOCIETY,
				'keywords'  => [ 'liposuction', 'lipo', 'vaser', 'yag aldirma' ],
			],
			[
				'title'     => 'ASPS — Facelift',
				'url'       => 'https://www.plasticsurgery.org/cosmetic-procedures/facelift',
				'type'      => SourceType::AUTHORITY,
				'publisher' => 'American Society of Plastic Surgeons',
				'via'       => DiscoveredVia::PROFESSIONAL_SOCIETY,
				'keywords'  => [ 'yuz germe', 'facelift', 'face lift' ],
			],
			[
				'title'     => 'ASPS — Eyelid Surgery',
				'url'       => 'https://www.plasticsurgery.org/cosmetic-procedures/eyelid-surgery',
				'type'      => SourceType::AUTHORITY,
				'publisher' => 'American Society of Plastic Surgeons',
				'via'       => DiscoveredVia::PROFESSIONAL_SOCIETY,
				'keywords'  => [ 'goz kapagi', 'blefaroplasti', 'blepharoplasty', 'eyelid' ],
			],
			[
				'title'     => 'ASPS — Tummy Tuck',
				'url'       => 'https://www.plasticsurgery.org/cosmetic-procedures/tummy-tuck',
				'type'      => SourceType::AUTHORITY,
				'publisher' => 'American Society of Plastic Surgeons',
				'via'       => DiscoveredVia::PROFESSIONAL_SOCIETY,
				'keywords'  => [ 'karin germe', 'abdominoplasti', 'tummy' ],
			],
			[
				'title'     => 'ASPS — Botulinum Toxin',
				'url'       => 'https://www.plasticsurgery.org/cosmetic-procedures/botulinum-toxin',
				'type'      => SourceType::AUTHORITY,
				'publisher' => 'American Society of Plastic Surgeons',
				'via'       => DiscoveredVia::PROFESSIONAL_SOCIETY,
				'keywords'  => [ 'botoks', 'botox', 'dysport', 'botulinum' ],
			],
			[
				'title'     => 'ASPS — Dermal Fillers',
				'url'       => 'https://www.plasticsurgery.org/cosmetic-procedures/dermal-fillers',
				'type'      => SourceType::AUTHORITY,
				'publisher' => 'American Society of Plastic Surgeons',
				'via'       => DiscoveredVia::PROFESSIONAL_SOCIETY,
				'keywords'  => [ 'dolgu', 'filler', 'hyaluronik' ],
			],

			/* ----------------------------------------- NHS (authority) */
			[
				'title'     => 'NHS — Botulinum toxin injections',
				'url'       => 'https://www.nhs.uk/conditions/cosmetic-procedures/non-surgical-cosmetic-procedures/botox-injections/',
				'type'      => SourceType::AUTHORITY,
				'publisher' => 'NHS',
				'via'       => DiscoveredVia::GOVERNMENT,
				'keywords'  => [ 'botoks', 'botox', 'dysport', 'botulinum' ],
			],
			[
				'title'     => 'NHS — Dermal fillers',
				'url'       => 'https://www.nhs.uk/conditions/cosmetic-procedures/non-surgical-cosmetic-procedures/dermal-fillers/',
				'type'      => SourceType::AUTHORITY,
				'publisher' => 'NHS',
				'via'       => DiscoveredVia::GOVERNMENT,
				'keywords'  => [ 'dolgu', 'filler', 'hyaluronik' ],
			],
			[
				'title'     => 'NHS — Breast enlargement',
				'url'       => 'https://www.nhs.uk/conditions/cosmetic-procedures/cosmetic-surgery/breast-enlargement/',
				'type'      => SourceType::AUTHORITY,
				'publisher' => 'NHS',
				'via'       => DiscoveredVia::GOVERNMENT,
				'keywords'  => [ 'meme buyutme', 'meme', 'gogus', 'breast' ],
			],
			[
				'title'     => 'NHS — Liposuction',
				'url'       => 'https://www.nhs.uk/conditions/cosmetic-procedures/cosmetic-surgery/liposuction/',
				'type'      => SourceType::AUTHORITY,
				'publisher' => 'NHS',
				'via'       => DiscoveredVia::GOVERNMENT,
				'keywords'  => [ 'liposuction', 'lipo', 'vaser', 'yag aldirma' ],
			],
			[
				'title'     => 'NHS — Eyelid surgery',
				'url'       => 'https://www.nhs.uk/conditions/cosmetic-procedures/cosmetic-surgery/eyelid-surgery/',
				'type'      => SourceType::AUTHORITY,
				'publisher' => 'NHS',
				'via'       => DiscoveredVia::GOVERNMENT,
				'keywords'  => [ 'goz kapagi', 'blefaroplasti', 'blepharoplasty', 'eyelid' ],
			],
			[
				'title'     => 'NHS — Tummy tuck',
				'url'       => 'https://www.nhs.uk/conditions/cosmetic-procedures/cosmetic-surgery/tummy-tuck/',
				'type'      => SourceType::AUTHORITY,
				'publisher' => 'NHS',
				'via'       => DiscoveredVia::GOVERNMENT,
				'keywords'  => [ 'karin germe', 'abdominoplasti', 'tummy' ],
			],

			/* ------------------------------ Cleveland Clinic (academic) */
			[
				'title'     => 'Cleveland Clinic — Rhinoplasty',
				'url'       => 'https://my.clevelandclinic.org/health/treatments/11011-rhinoplasty',
				'type'      => SourceType::ACADEMIC,
				'publisher' => 'Cleveland Clinic',
				'via'       => DiscoveredVia::UNIVERSITY,
				'keywords'  => [ 'rinoplasti', 'burun', 'nose', 'rhinoplasty' ],
			],
			[
				'title'     => 'Cleveland Clinic — Botulinum Toxin Injections',
				'url'       => 'https://my.clevelandclinic.org/health/treatments/8312-botulinum-toxin-injections',
				'type'      => SourceType::ACADEMIC,
				'publisher' => 'Cleveland Clinic',
				'via'       => DiscoveredVia::UNIVERSITY,
				'keywords'  => [ 'botoks', 'botox', 'dysport', 'botulinum' ],
			],
		];
	}

	/**
	 * Türkçe karakterleri katlayarak karşılaştırılabilir hale getirir.
	 *
	 * SAF.
	 */
	public static function normalize( string $value ): string {
		$value = mb_strtolower( $value, 'UTF-8' );
		$value = strtr(
			$value,
			[
				'ı' => 'i',
				'İ' => 'i',
				'ğ' => 'g',
				'ü' => 'u',
				'ş' => 's',
				'ö' => 'o',
				'ç' => 'c',
				'â' => 'a',
				'î' => 'i',
				'-' => ' ',
				'_' => ' ',
			]
		);

		return trim( (string) preg_replace( '/\s+/u', ' ', $value ) );
	}

	/**
	 * Katalog girdisi bu konu adına/slug'ına uyuyor mu?
	 *
	 * SAF.
	 *
	 * @param string[] $keywords
	 */
	public static function matches( array $keywords, string $topic_name, string $topic_slug ): bool {
		$haystack = self::normalize( $topic_name ) . ' ' . self::normalize( $topic_slug );

		foreach ( $keywords as $keyword ) {
			if ( '' !== $keyword && str_contains( $haystack, self::normalize( $keyword ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Oluşturulacak kayıtların planı. Hiçbir şey yazmaz.
	 *
	 * @return array<int,array<string,mixed>> Her biri: entry + term_ids
	 */
	public function plan(): array {
		$topics = get_terms(
			[
				'taxonomy'   => MedicalTopicTaxonomy::SLUG,
				'hide_empty' => false,
			]
		);

		if ( ! is_array( $topics ) || empty( $topics ) ) {
			return [];
		}

		$existing = $this->existing_canonical_urls();
		$plan     = [];

		foreach ( self::catalog() as $entry ) {
			$canonical = UrlPolicy::canonical( [ 'url' => (string) $entry['url'] ] );

			// İdempotent: aynı kanonik hedef zaten kütüphanedeyse atlanır.
			if ( null === $canonical || in_array( $canonical, $existing, true ) ) {
				continue;
			}

			$term_ids = [];

			foreach ( $topics as $topic ) {
				if ( self::matches( (array) $entry['keywords'], $topic->name, $topic->slug ) ) {
					$term_ids[] = (int) $topic->term_id;
				}
			}

			// Sitede karşılığı olmayan konu için kayıt üretilmez.
			if ( empty( $term_ids ) ) {
				continue;
			}

			$entry['term_ids'] = $term_ids;
			$plan[]            = $entry;
		}

		return $plan;
	}

	/**
	 * Synchronize verified catalog sources for existing or newly-created topics.
	 *
	 * New catalog entries are immediately publishable because their URL, type,
	 * publisher and discovery origin are shipped with this plugin version. A
	 * manually created pending source is never promoted by this method.
	 *
	 * @param int[]|null $topic_ids Null means all medical topics.
	 * @return array{created:int,published:int,relations_added:int,topics_matched:int,topics_unmatched:int,manual_pending_preserved:int,ran_at:string}
	 */
	public function synchronize_and_publish( ?array $topic_ids = null ): array {
		$topics = $this->topics( $topic_ids );
		$report = [
			'created'                  => 0,
			'published'                => 0,
			'relations_added'          => 0,
			'topics_matched'           => 0,
			'topics_unmatched'         => 0,
			'manual_pending_preserved' => 0,
			'ran_at'                   => current_time( 'mysql', true ),
		];

		if ( empty( $topics ) ) {
			update_option( self::OPTION_LAST_SYNC, $report, false );

			return $report;
		}

		$source_map = $this->sources_by_canonical_url();
		$matched_topics = [];
		$changed = false;

		foreach ( self::catalog() as $entry ) {
			$canonical = self::canonical_url( (string) $entry['url'] );
			if ( '' === $canonical ) {
				continue;
			}

			$matching_ids = [];
			foreach ( $topics as $topic ) {
				if ( self::matches( (array) $entry['keywords'], $topic->name, $topic->slug ) ) {
					$matching_ids[] = (int) $topic->term_id;
					$matched_topics[ (int) $topic->term_id ] = true;
				}
			}

			if ( empty( $matching_ids ) ) {
				continue;
			}

			$catalog_source_id = 0;
			foreach ( $source_map[ $canonical ] ?? [] as $source_id ) {
				if ( $this->source_matches_catalog_entry( (int) $source_id, $entry ) ) {
					$catalog_source_id = (int) $source_id;
					break;
				}
			}

			if ( $catalog_source_id > 0 ) {
				$relations = $this->append_topics( $catalog_source_id, $matching_ids );
				$report['relations_added'] += $relations;
				$changed = $changed || $relations > 0;
				if ( '' === (string) get_post_meta( $catalog_source_id, MetaRegistry::SOURCE_CATALOG_KEY, true ) ) {
					update_post_meta( $catalog_source_id, MetaRegistry::SOURCE_CATALOG_KEY, $canonical );
					$changed = true;
				}
				if ( 'pending' === get_post_status( $catalog_source_id ) ) {
					$updated = wp_update_post( [ 'ID' => $catalog_source_id, 'post_status' => 'publish' ], true );
					if ( ! is_wp_error( $updated ) ) {
						++$report['published'];
						$changed = true;
					}
				}

				continue;
			}

			// A user-owned source with the same canonical URL wins over a duplicate.
			// Its topics/status remain editor-controlled, including pending status.
			if ( ! empty( $source_map[ $canonical ] ) ) {
				foreach ( $source_map[ $canonical ] as $source_id ) {
					if ( 'pending' === get_post_status( (int) $source_id ) ) {
						++$report['manual_pending_preserved'];
					}
				}

				continue;
			}

			$source_id = $this->create_catalog_source( $entry, $matching_ids, 'publish' );
			if ( $source_id > 0 ) {
				$source_map[ $canonical ] = [ $source_id ];
				++$report['created'];
				++$report['published'];
				$changed = true;
			}
		}

		$report['topics_matched'] = count( $matched_topics );
		$report['topics_unmatched'] = max( 0, count( $topics ) - $report['topics_matched'] );

		if ( $changed ) {
			Settings::bump_library_version();
			TopicRepository::flush_memo();
		}

		update_option( self::OPTION_LAST_SYNC, $report, false );

		return $report;
	}

	/**
	 * Planı uygular. Kayıtlar `pending` durumunda oluşturulur.
	 *
	 * @return array{created:int,skipped:int,topics:int,titles:array<int,string>}
	 */
	public function install(): array {
		$plan   = $this->plan();
		$titles = [];
		$today  = current_time( 'Y-m-d' );

		foreach ( $plan as $entry ) {
			$post_id = $this->create_catalog_source( $entry, (array) $entry['term_ids'], 'pending', $today );

			if ( $post_id > 0 ) {
				$titles[] = (string) $entry['title'];
			}
		}

		return [
			'created' => count( $titles ),
			'skipped' => count( self::catalog() ) - count( $titles ),
			'topics'  => $this->topic_count(),
			'titles'  => $titles,
		];
	}

	/**
	 * Bekleyen aday kaynakları yayımlar.
	 */
	public function publish_pending(): int {
		$pending = get_posts(
			[
				'post_type'              => SourcePostType::SLUG,
				'post_status'            => 'pending',
				'numberposts'            => 200,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		$count = 0;

		foreach ( $pending as $post_id ) {
			if ( ! $this->is_catalog_source( (int) $post_id ) ) {
				continue;
			}

			$result = wp_update_post(
				[
					'ID'          => (int) $post_id,
					'post_status' => 'publish',
				],
				true
			);

			if ( ! is_wp_error( $result ) ) {
				++$count;
			}
		}

		return $count;
	}

	public function is_catalog_source( int $source_id ): bool {
		return null !== $this->catalog_entry_for_source( $source_id );
	}

	public function pending_count(): int {
		$pending = get_posts(
			[
				'post_type'              => SourcePostType::SLUG,
				'post_status'            => 'pending',
				'numberposts'            => 200,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		return count(
			array_filter(
				array_map( 'intval', $pending ),
				fn( int $source_id ): bool => $this->is_catalog_source( $source_id )
			)
		);
	}

	/** @param int[]|null $topic_ids @return \WP_Term[] */
	private function topics( ?array $topic_ids ): array {
		$args = [
			'taxonomy'   => MedicalTopicTaxonomy::SLUG,
			'hide_empty' => false,
		];

		if ( null !== $topic_ids ) {
			$ids = array_values( array_unique( array_filter( array_map( 'absint', $topic_ids ) ) ) );
			if ( empty( $ids ) ) {
				return [];
			}
			$args['include'] = $ids;
		}

		$terms = get_terms( $args );

		return is_array( $terms ) ? array_values( array_filter( $terms, static fn( $term ): bool => $term instanceof \WP_Term ) ) : [];
	}

	private static function canonical_url( string $url ): string {
		$canonical = UrlPolicy::canonical( [ 'url' => $url ] );

		return is_string( $canonical ) ? $canonical : '';
	}

	/** @return array<string,int[]> */
	private function sources_by_canonical_url(): array {
		$sources = get_posts(
			[
				'post_type'              => SourcePostType::SLUG,
				'post_status'            => 'any',
				'numberposts'            => 500,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			]
		);
		$map = [];

		foreach ( $sources as $source_id ) {
			$canonical = $this->source_canonical_url( (int) $source_id );
			if ( '' !== $canonical ) {
				$map[ $canonical ][] = (int) $source_id;
			}
		}

		return $map;
	}

	private function source_canonical_url( int $source_id ): string {
		$canonical = UrlPolicy::canonical(
			[
				'doi'           => (string) get_post_meta( $source_id, MetaRegistry::SOURCE_DOI, true ),
				'pmc_id'        => (string) get_post_meta( $source_id, MetaRegistry::SOURCE_PMC_ID, true ),
				'publisher_url' => (string) get_post_meta( $source_id, MetaRegistry::SOURCE_PUBLISHER_URL, true ),
				'url'           => (string) get_post_meta( $source_id, MetaRegistry::SOURCE_URL, true ),
				'pmid'          => (string) get_post_meta( $source_id, MetaRegistry::SOURCE_PMID, true ),
			]
		);

		return is_string( $canonical ) ? $canonical : '';
	}

	/** @param array<string,mixed> $entry */
	private function source_matches_catalog_entry( int $source_id, array $entry ): bool {
		$canonical = self::canonical_url( (string) $entry['url'] );
		if ( '' === $canonical || $canonical !== $this->source_canonical_url( $source_id ) ) {
			return false;
		}

		if ( $canonical === (string) get_post_meta( $source_id, MetaRegistry::SOURCE_CATALOG_KEY, true ) ) {
			return true;
		}

		// RC1.1's pending seed rows pre-date the origin marker. Recognize only
		// their complete deterministic signature, not arbitrary manual sources.
		$post = get_post( $source_id );
		if ( ! $post instanceof \WP_Post || (string) $entry['title'] !== $post->post_title ) {
			return false;
		}
		if ( (string) $entry['publisher'] !== (string) get_post_meta( $source_id, MetaRegistry::SOURCE_PUBLISHER, true )
			|| (string) $entry['via'] !== (string) get_post_meta( $source_id, MetaRegistry::SOURCE_DISCOVERED_VIA, true )
			|| PublicationType::INSTITUTIONAL_PAGE !== (string) get_post_meta( $source_id, MetaRegistry::SOURCE_PUBLICATION_TYPE, true )
			|| 'en' !== (string) get_post_meta( $source_id, MetaRegistry::SOURCE_LANG, true )
			|| (bool) get_post_meta( $source_id, MetaRegistry::SOURCE_PEER_REVIEWED, true ) ) {
			return false;
		}

		$types = get_the_terms( $source_id, SourceTypeTaxonomy::SLUG );

		return is_array( $types ) && 1 === count( $types ) && (string) $entry['type'] === $types[0]->slug;
	}

	/** @param array<string,mixed> $entry */
	private function create_catalog_source( array $entry, array $topic_ids, string $status, ?string $today = null ): int {
		$post_id = wp_insert_post(
			[
				'post_type'   => SourcePostType::SLUG,
				'post_title'  => (string) $entry['title'],
				'post_status' => 'publish' === $status ? 'publish' : 'pending',
			],
			true
		);
		if ( is_wp_error( $post_id ) || (int) $post_id < 1 ) {
			return 0;
		}

		$post_id = (int) $post_id;
		$today   = null !== $today ? $today : current_time( 'Y-m-d' );
		update_post_meta( $post_id, MetaRegistry::SOURCE_URL, (string) $entry['url'] );
		update_post_meta( $post_id, MetaRegistry::SOURCE_CATALOG_KEY, self::canonical_url( (string) $entry['url'] ) );
		update_post_meta( $post_id, MetaRegistry::SOURCE_PUBLISHER, (string) $entry['publisher'] );
		update_post_meta( $post_id, MetaRegistry::SOURCE_PUBLICATION_TYPE, PublicationType::INSTITUTIONAL_PAGE );
		update_post_meta( $post_id, MetaRegistry::SOURCE_DISCOVERED_VIA, (string) $entry['via'] );
		update_post_meta( $post_id, MetaRegistry::SOURCE_DISCOVERED_AT, $today );
		update_post_meta( $post_id, MetaRegistry::SOURCE_VERIFIED_AT, $today );
		update_post_meta( $post_id, MetaRegistry::SOURCE_HEALTH, SourceHealth::UNKNOWN );
		update_post_meta( $post_id, MetaRegistry::SOURCE_PEER_REVIEWED, false );
		update_post_meta( $post_id, MetaRegistry::SOURCE_LANG, 'en' );
		wp_set_object_terms( $post_id, [ (string) $entry['type'] ], SourceTypeTaxonomy::SLUG, false );
		wp_set_object_terms( $post_id, array_values( array_unique( array_map( 'intval', $topic_ids ) ) ), MedicalTopicTaxonomy::SLUG, false );

		return $post_id;
	}

	/** @param int[] $topic_ids */
	private function append_topics( int $source_id, array $topic_ids ): int {
		$current = get_the_terms( $source_id, MedicalTopicTaxonomy::SLUG );
		$current_ids = is_array( $current ) ? array_map( static fn( \WP_Term $term ): int => (int) $term->term_id, $current ) : [];
		$missing = array_values( array_diff( array_map( 'intval', $topic_ids ), $current_ids ) );
		if ( empty( $missing ) ) {
			return 0;
		}

		$result = wp_set_object_terms( $source_id, $missing, MedicalTopicTaxonomy::SLUG, true );

		return is_wp_error( $result ) ? 0 : count( $missing );
	}

	/** @return array<string,mixed>|null */
	private function catalog_entry_for_source( int $source_id ): ?array {
		foreach ( self::catalog() as $entry ) {
			if ( $this->source_matches_catalog_entry( $source_id, $entry ) ) {
				return $entry;
			}
		}

		return null;
	}

	/**
	 * @return string[]
	 */
	private function existing_canonical_urls(): array {
		$sources = get_posts(
			[
				'post_type'              => SourcePostType::SLUG,
				'post_status'            => 'any',
				'numberposts'            => 500,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			]
		);

		$urls = [];

		foreach ( $sources as $source_id ) {
			$canonical = UrlPolicy::canonical(
				[
					'doi'           => (string) get_post_meta( (int) $source_id, MetaRegistry::SOURCE_DOI, true ),
					'pmc_id'        => (string) get_post_meta( (int) $source_id, MetaRegistry::SOURCE_PMC_ID, true ),
					'publisher_url' => (string) get_post_meta( (int) $source_id, MetaRegistry::SOURCE_PUBLISHER_URL, true ),
					'url'           => (string) get_post_meta( (int) $source_id, MetaRegistry::SOURCE_URL, true ),
					'pmid'          => (string) get_post_meta( (int) $source_id, MetaRegistry::SOURCE_PMID, true ),
				]
			);

			if ( null !== $canonical ) {
				$urls[] = $canonical;
			}
		}

		return $urls;
	}

	private function topic_count(): int {
		$terms = get_terms(
			[
				'taxonomy'   => MedicalTopicTaxonomy::SLUG,
				'hide_empty' => false,
				'fields'     => 'ids',
			]
		);

		return is_array( $terms ) ? count( $terms ) : 0;
	}
}
