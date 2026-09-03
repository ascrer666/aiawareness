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
use DLA\MedicalTrust\Domain\ReviewVisibility;
use DLA\MedicalTrust\Domain\TrustData;
use DLA\MedicalTrust\Meta\MetaRegistry;
use DLA\MedicalTrust\PostTypes\ExpertPostType;
use DLA\MedicalTrust\Review\ReviewService;
use DLA\MedicalTrust\Resolver\SelectionCache;
use DLA\MedicalTrust\Settings\Settings;
use DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy;

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

	/**
	 * Sunum uygunluğu.
	 *
	 * DİKKAT: burada `ReviewPageRepository::find()` KULLANILMAZ. O depo
	 * "bu sayfa tıbbi inceleme kaydedebilir mi" sorusunu cevaplar ve konu
	 * ZORUNLU tutar — çünkü inceleme politikası konudan gelir. Sunum ise
	 * farklı bir sorudur: gösterilecek doğrulanmış bir olgu var mı?
	 *
	 * İkisini birbirine bağlamak, geçerli bir yayımlanmış uzmanı olan ama
	 * henüz konusu olmayan sayfada bloğun hiç render edilmemesine yol
	 * açıyordu.
	 */
	public function is_eligible_post( int $post_id ): bool {
		$post = get_post( $post_id );

		return $post instanceof \WP_Post
			&& in_array( $post->post_type, Settings::eligible_post_types(), true );
	}

	/**
	 * Varlık dosyasının yüklenmesi gerekiyor mu? Ucuz kontrol: tam
	 * hidrasyon yapmadan, gösterilebilecek bir olgu ihtimali var mı.
	 */
	public function is_medical_post( int $post_id ): bool {
		if ( ! $this->is_eligible_post( $post_id ) ) {
			return false;
		}

		if ( (int) get_post_meta( $post_id, MetaRegistry::PAGE_EXPERT_ID, true ) > 0 ) {
			return true;
		}

		if ( (int) get_post_meta( $post_id, MetaRegistry::PAGE_REVIEWER_EXPERT_ID, true ) > 0 ) {
			return true;
		}

		if ( '' !== trim( (string) get_post_meta( $post_id, MetaRegistry::PAGE_COMMENTARY, true ) ) ) {
			return true;
		}

		$terms = get_the_terms( $post_id, MedicalTopicTaxonomy::SLUG );

		return is_array( $terms ) && ! empty( $terms );
	}

	public function for_post( int $post_id ): ?TrustData {
		if ( ! $this->is_eligible_post( $post_id ) ) {
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
		if ( ! ReviewVisibility::is_applicable( $status, $validity, $reviewer, $review_date ) ) {
			$reviewer    = null;
			$review_date = '';
		}

		$flags = get_post_meta( $post_id, MetaRegistry::PAGE_DISPLAY_FLAGS, true );
		$flags = is_array( $flags ) ? $flags : [];
		$show_commentary = ! array_key_exists( 'show_commentary', $flags ) || (bool) $flags['show_commentary'];
		$show_sources = ! array_key_exists( 'show_sources', $flags ) || (bool) $flags['show_sources'];

		// Uzman değerlendirmesi TIBBİ İNCELEME KAYDINA BAĞLI DEĞİLDİR.
		//
		// Eskiden `null !== $reviewer &&` koşulu vardı: inceleme kaydı olmayan
		// sayfada editörün yazdığı metin sessizce yok sayılıyordu. Alan admin'de
		// "isteğe bağlı" diye etiketli olduğu ve hiçbir uyarı basılmadığı için
		// bu, doldurulan verinin kaybolması gibi görünüyordu.
		//
		// Metin içerik sahibine aittir; eklenti onu uydurmaz, yalnızca gösterir.
		// Atıf sorumluluğu admin alanındaki yardım metniyle açıkça bildirilir.
		$commentary = $show_commentary ? (string) get_post_meta( $post_id, MetaRegistry::PAGE_COMMENTARY, true ) : '';

		// Görünen uzman için devralma zinciri. Sayfa başına seçim yapmayı
		// gereksiz kılar; hiçbir aşamada YAZARLIK veya İNCELEME iddiası
		// üretmez — yalnızca içeriğin tıbbi sorumlusunu bildirir.
		$primary        = $reviewer ?? $author;
		$primary_source = null !== $reviewer ? 'reviewer' : ( null !== $author ? 'author' : '' );

		if ( null === $primary ) {
			$topic_expert = $this->expert( $this->topic_default_expert_id( $post_id ) );

			if ( null !== $topic_expert ) {
				$primary        = $topic_expert;
				$primary_source = 'topic';
			} else {
				$site_expert = $this->expert( Settings::default_expert_id() );

				if ( null !== $site_expert ) {
					$primary        = $site_expert;
					$primary_source = 'site';
				}
			}
		}

		// Konusu olmayan sayfada kaynak çözümlemesi hiç çalıştırılmaz:
		// gereksiz konu grafiği kurulumundan ve sorgudan kaçınılır.
		$has_topic = has_term( '', MedicalTopicTaxonomy::SLUG, $post_id );

		// İçerik güncelleme tarihi: WordPress'in tuttuğu GERÇEK bir olgu.
		// Hiçbir insan eylemi iddia etmez ve tıbbi inceleme tarihinin yerine
		// GEÇMEZ — şablonda kendi etiketiyle, ayrı satırda gösterilir.
		// post_modified zaten site yerel saatinde saklanir; get_post_modified_time()
		// yerine dogrudan alan okunur. Boylece wp_timezone() / gmt_offset gibi
		// secenek aramalari render yolunda hic calismaz.
		$updated_date = Settings::show_updated_date()
			? substr( (string) get_post_field( 'post_modified', $post_id ), 0, 10 )
			: '';

		$data = new TrustData(
			$post_id,
			$author_mode,
			$author,
			$reviewer,
			$primary,
			'' !== $review_date && Settings::show_review_date() ? $review_date : null,
			'' !== $review_date && Settings::show_review_date() ? $this->reviews->freshness_for_post( $post_id ) : null,
			$status,
			$validity,
			$commentary,
			$show_sources && $has_topic ? $this->selected_sources( $post_id ) : [],
			$primary_source,
			'' !== $updated_date ? $updated_date : null
		);

		return $data->has_visible_facts() ? $data : null;
	}

	/**
	 * Birincil konunun varsayilan uzmani. Konu meta'si M1'den beri vardi ama
	 * sunumda hic kullanilmiyordu; her sayfada uzman secmeyi gereksiz kilar.
	 */
	private function topic_default_expert_id( int $post_id ): int {
		$terms = get_the_terms( $post_id, MedicalTopicTaxonomy::SLUG );

		if ( ! is_array( $terms ) || empty( $terms ) ) {
			return 0;
		}

		$primary_uid = (string) get_post_meta( $post_id, MetaRegistry::PAGE_PRIMARY_TOPIC_UID, true );
		$fallback    = 0;

		foreach ( $terms as $term ) {
			$expert_id = (int) get_term_meta( $term->term_id, MetaRegistry::TOPIC_DEFAULT_EXPERT, true );

			if ( $expert_id < 1 ) {
				continue;
			}

			if ( '' !== $primary_uid
				&& $primary_uid === (string) get_term_meta( $term->term_id, MetaRegistry::TOPIC_UID, true ) ) {
				return $expert_id;
			}

			if ( 0 === $fallback ) {
				$fallback = $expert_id;
			}
		}

		return $fallback;
	}

	/** @return array<string,mixed>|null */
	private function expert( int $expert_id ): ?array {
		if ( ! ExpertPostType::is_valid_published_expert( $expert_id ) ) {
			return null;
		}
		$expert = get_post( $expert_id );
		$profile_id = (int) get_post_meta( $expert_id, MetaRegistry::EXPERT_PROFILE_PAGE, true );
		$profile    = get_post( $profile_id );
		$profile_url = $profile instanceof \WP_Post && 'publish' === $profile->post_status ? (string) get_permalink( $profile_id ) : '';
		$name = ExpertPostType::compose_name(
			(string) get_post_meta( $expert_id, MetaRegistry::EXPERT_HONORIFIC, true ),
			(string) $expert->post_title
		);

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
		$ids = array_filter( $this->selection_cache->get( $post_id ) );

		// Secilmis kaynak yoksa konu grafigi hic kurulmaz.
		if ( empty( $ids ) ) {
			return [];
		}

		$graph  = $this->topics->graph();
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
