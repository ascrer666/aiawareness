<?php
/**
 * M4 presentation input. This object contains resolved facts only; it does
 * not decide source selection, review validity, or visibility.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TrustData {

	/**
	 * @param array<string,mixed>|null $author_expert
	 * @param array<string,mixed>|null $reviewer_expert
	 * @param array<string,mixed>|null $primary_expert
	 * @param array<int,array<string,mixed>> $sources
	 */
	public function __construct(
		public int $post_id,
		public string $author_mode,
		public ?array $author_expert,
		public ?array $reviewer_expert,
		public ?array $primary_expert,
		public ?string $review_date,
		public ?string $review_freshness,
		public string $review_status,
		public string $review_validity,
		public string $commentary,
		public array $sources,
		/**
		 * Gorunen uzmanin nereden geldigi:
		 *   reviewer | author  -> sayfaya ozel, dogrulanmis iddia
		 *   topic | site       -> devralinan icerik sorumlusu; YAZARLIK veya
		 *                         INCELEME iddiasi TASIMAZ.
		 */
		public string $primary_expert_source = '',
		/**
		 * Sayfanin post_modified tarihi (Y-m-d) veya null.
		 *
		 * Bu bir TIBBI INCELEME tarihi DEGILDIR ve onun yerine gecemez;
		 * sablonda kendi etiketiyle ("Son guncelleme") ayri satirda gosterilir.
		 */
		public ?string $updated_date = null
	) {}

	/**
	 * Gorunen uzman devralma zincirinden mi geldi?
	 */
	public function primary_expert_is_inherited(): bool {
		return in_array( $this->primary_expert_source, [ 'topic', 'site' ], true );
	}

	public function has_visible_facts(): bool {
		return null !== $this->primary_expert
			|| null !== $this->author_expert
			|| null !== $this->reviewer_expert
			|| '' !== $this->commentary
			|| ! empty( $this->sources );
	}

	public function has_valid_review(): bool {
		return null !== $this->reviewer_expert && null !== $this->review_date;
	}
}
