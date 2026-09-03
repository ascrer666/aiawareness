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
use DLA\MedicalTrust\Support\UrlPolicy;
use DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy;
use DLA\MedicalTrust\Taxonomies\SourceTypeTaxonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class StarterLibrary {

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
	 * Planı uygular. Kayıtlar `pending` durumunda oluşturulur.
	 *
	 * @return array{created:int,skipped:int,topics:int,titles:array<int,string>}
	 */
	public function install(): array {
		$plan   = $this->plan();
		$titles = [];
		$today  = current_time( 'Y-m-d' );

		foreach ( $plan as $entry ) {
			$post_id = wp_insert_post(
				[
					'post_type'   => SourcePostType::SLUG,
					'post_title'  => (string) $entry['title'],
					'post_status' => 'pending',
				],
				true
			);

			if ( is_wp_error( $post_id ) || 0 === (int) $post_id ) {
				continue;
			}

			$post_id = (int) $post_id;

			update_post_meta( $post_id, MetaRegistry::SOURCE_URL, (string) $entry['url'] );
			update_post_meta( $post_id, MetaRegistry::SOURCE_PUBLISHER, (string) $entry['publisher'] );
			update_post_meta( $post_id, MetaRegistry::SOURCE_PUBLICATION_TYPE, PublicationType::INSTITUTIONAL_PAGE );
			update_post_meta( $post_id, MetaRegistry::SOURCE_DISCOVERED_VIA, (string) $entry['via'] );
			update_post_meta( $post_id, MetaRegistry::SOURCE_DISCOVERED_AT, $today );
			update_post_meta( $post_id, MetaRegistry::SOURCE_VERIFIED_AT, $today );
			update_post_meta( $post_id, MetaRegistry::SOURCE_HEALTH, SourceHealth::UNKNOWN );
			update_post_meta( $post_id, MetaRegistry::SOURCE_PEER_REVIEWED, false );
			update_post_meta( $post_id, MetaRegistry::SOURCE_LANG, 'en' );

			wp_set_object_terms( $post_id, [ (string) $entry['type'] ], SourceTypeTaxonomy::SLUG, false );
			wp_set_object_terms( $post_id, array_map( 'intval', (array) $entry['term_ids'] ), MedicalTopicTaxonomy::SLUG, false );

			$titles[] = (string) $entry['title'];
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

	public function pending_count(): int {
		return count(
			get_posts(
				[
					'post_type'              => SourcePostType::SLUG,
					'post_status'            => 'pending',
					'numberposts'            => 200,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				]
			)
		);
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
