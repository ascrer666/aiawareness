<?php
/**
 * Dil-nötr kalıcı kimlikler (v0.2 §11, Addendum A §5).
 *
 * Neden gerekli:
 *   - topic_uid  : kaynak çözümlemesi term_id'ye değil buna göre yapılır;
 *                  çok dil eklentisinden bağımsızlık sağlar.
 *   - entity_uid : uzman varlığı dört dilde tek @id taşır.
 *   - source_uid : rendezvous hash girdisi. Post ID kullanılsaydı staging'den
 *                  canlıya geçişte tüm sayfaların kaynak seçimi kayardı.
 *
 * Kural: bir kez üretilir, ASLA değişmez.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Identity;

use DLA\MedicalTrust\Meta\MetaRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UidGenerator {

	public const PREFIX_EXPERT = 'exp';
	public const PREFIX_TOPIC  = 'top';
	public const PREFIX_SOURCE = 'src';
	public const PREFIX_GROUP  = 'grp';

	private const RANDOM_BYTES = 6;
	private const MAX_ATTEMPTS = 6;

	public function register(): void {
		add_action( 'save_post_dla_expert', [ $this, 'ensure_expert_uid' ], 5, 3 );
		add_action( 'save_post_dla_source', [ $this, 'ensure_source_uid' ], 5, 3 );
		add_action( 'created_dla_medical_topic', [ $this, 'ensure_topic_uid' ], 5, 2 );

		// Sayfa grubu kimliği: rendezvous seed'inin kararlılık anahtarı.
		// Post ID kullanılsaydı bir çevirinin silinmesi kalan çevirilerin
		// kaynak seçimini kaydırırdı.
		foreach ( \DLA\MedicalTrust\Settings\Settings::eligible_post_types() as $post_type ) {
			add_action( 'save_post_' . $post_type, [ $this, 'ensure_page_group_uid' ], 5, 3 );
		}
	}

	/**
	 * SAF: biçim üretimi. Test edilebilir.
	 */
	public static function format( string $prefix, string $random_hex ): string {
		return $prefix . '_' . $random_hex;
	}

	public static function is_valid_format( string $uid ): bool {
		return 1 === preg_match( '#^(exp|top|src|grp)_[0-9a-f]{12}$#', $uid );
	}

	private static function random_hex(): string {
		return bin2hex( random_bytes( self::RANDOM_BYTES ) );
	}

	public function ensure_expert_uid( int $post_id, $post, bool $update ): void {
		unset( $post, $update );
		$this->ensure_post_uid( $post_id, MetaRegistry::EXPERT_ENTITY_UID, self::PREFIX_EXPERT, 'expert' );
	}

	public function ensure_source_uid( int $post_id, $post, bool $update ): void {
		unset( $post, $update );
		$this->ensure_post_uid( $post_id, MetaRegistry::SOURCE_UID, self::PREFIX_SOURCE, 'source' );
	}

	private function ensure_post_uid( int $post_id, string $meta_key, string $prefix, string $object_type ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || 'auto-draft' === get_post_status( $post_id ) ) {
			return;
		}

		$existing = (string) get_post_meta( $post_id, $meta_key, true );
		if ( '' !== $existing ) {
			return; // Asla üzerine yazılmaz.
		}

		update_post_meta( $post_id, $meta_key, $this->mint( $prefix, $object_type, $post_id, $meta_key, false ) );
	}

	public function ensure_page_group_uid( int $post_id, $post, bool $update ): void {
		unset( $post, $update );
		$this->ensure_post_uid( $post_id, MetaRegistry::PAGE_GROUP_UID, self::PREFIX_GROUP, 'page_group' );
	}

	public function ensure_topic_uid( int $term_id, int $tt_id = 0 ): void {
		unset( $tt_id );

		$existing = (string) get_term_meta( $term_id, MetaRegistry::TOPIC_UID, true );
		if ( '' !== $existing ) {
			return;
		}

		update_term_meta( $term_id, MetaRegistry::TOPIC_UID, $this->mint( self::PREFIX_TOPIC, 'topic', $term_id, MetaRegistry::TOPIC_UID, true ) );
	}

	/**
	 * Kimlik üretir.
	 *
	 * `dla_mt_pre_generate_uid` filtresi null olmayan bir dize döndürürse o
	 * kullanılır. M2'deki PolylangAdapter, bir çeviri oluşturulduğunda kaynak
	 * terimin UID'sini devrettirmek için bu kancayı kullanacak.
	 */
	public function mint( string $prefix, string $object_type, int $object_id, string $meta_key, bool $is_term ): string {
		/**
		 * @param string|null $uid         Devralınacak kimlik veya null.
		 * @param string      $object_type expert|source|topic
		 * @param int         $object_id
		 */
		$inherited = apply_filters( 'dla_mt_pre_generate_uid', null, $object_type, $object_id );

		if ( is_string( $inherited ) && self::is_valid_format( $inherited ) ) {
			return $inherited;
		}

		for ( $i = 0; $i < self::MAX_ATTEMPTS; $i++ ) {
			$uid = self::format( $prefix, self::random_hex() );

			if ( ! $this->uid_exists( $uid, $meta_key, $is_term ) ) {
				return $uid;
			}
		}

		// Çakışma pratikte imkânsız; yine de sessizce bozuk kimlik üretmemek için
		// daha geniş bir rastgelelikle son bir deneme yapılır.
		return self::format( $prefix, bin2hex( random_bytes( self::RANDOM_BYTES ) ) );
	}

	private function uid_exists( string $uid, string $meta_key, bool $is_term ): bool {
		global $wpdb;

		if ( $is_term ) {
			$found = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
					$meta_key,
					$uid
				)
			);
		} else {
			$found = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
					$meta_key,
					$uid
				)
			);
		}

		return null !== $found;
	}
}
