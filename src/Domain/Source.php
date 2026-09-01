<?php
/**
 * Kaynak — değişmez değer nesnesi.
 *
 * ────────────────────────────────────────────────────────────────────────
 *  KRİTİK: Bu nesnede `discovered_via` ALANI YOKTUR ve eklenmemelidir.
 *
 *  Addendum A §3: keşif kökeni idari bir kayıttır ve sıralamayı etkilemez.
 *  Bu kuralı belgeye değil TİP SİSTEMİNE bağlıyoruz — ScoringPolicy yalnızca
 *  bu nesneyi görür, dolayısıyla kökeni kullanması fiziksel olarak mümkün
 *  değildir. Alanı buraya eklemek kuralı sessizce ortadan kaldırır.
 * ────────────────────────────────────────────────────────────────────────
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Source {

	/**
	 * @param string[] $topic_uids
	 */
	public function __construct(
		public int $id,
		public string $uid,
		public string $title,
		public string $type,
		public ?string $publication_type,
		public bool $peer_reviewed,
		public int $priority,
		public int $pub_year,
		public string $health,
		public array $topic_uids,
		public ?string $canonical_url,
		public string $publisher,
		public string $journal,
		public string $lang
	) {}

	public function has_topic( string $uid ): bool {
		return in_array( $uid, $this->topic_uids, true );
	}

	/**
	 * Seçime girebilir mi? (Addendum A §2, 1. katı kısıt)
	 *
	 * Durum kontrolü repository'de yapılır (yalnızca `publish` yüklenir);
	 * burada kalan iki koşul: bozuk bağlantı ve kanonik hedefin varlığı.
	 */
	public function is_eligible(): bool {
		return \DLA\MedicalTrust\Domain\Enum\SourceHealth::BROKEN !== $this->health
			&& null !== $this->canonical_url
			&& '' !== $this->uid;
	}

	public function ineligibility_reason(): ?string {
		if ( \DLA\MedicalTrust\Domain\Enum\SourceHealth::BROKEN === $this->health ) {
			return 'health_broken';
		}

		if ( null === $this->canonical_url ) {
			return 'no_canonical_url';
		}

		if ( '' === $this->uid ) {
			return 'no_uid';
		}

		return null;
	}
}
