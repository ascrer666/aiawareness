<?php
/**
 * Çeviri kimlik senkronizasyonu (M2.0).
 *
 * Üç katmanlı savunma — oluşturma kancasına TEK BAŞINA güvenilmez:
 *
 *   1. Oluşturma anı  : `dla_mt_pre_generate_uid` filtresi, çeviri ekranındaki
 *                       kaynak nesneden ("from_post" / "from_tag") kimliği devralır.
 *   2. Bağlanma anı   : Polylang çeviri ilişkisini kaydettiğinde (`pll_save_post`,
 *                       `pll_save_term`) grup normalize edilir. Polylang ilişkiyi
 *                       çoğu zaman nesne oluşturulduktan SONRA kurar; 1. katman
 *                       tek başına bu yüzden yetmez.
 *   3. Onarım         : IdentityRepair, geçmişte ayrışmış grupları tarar ve düzeltir.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\I18n;

use DLA\MedicalTrust\Identity\UidGenerator;
use DLA\MedicalTrust\Meta\MetaRegistry;
use DLA\MedicalTrust\PostTypes\ExpertPostType;
use DLA\MedicalTrust\Settings\Settings;
use DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class IdentitySync {

	public function register(): void {
		add_filter( 'dla_mt_pre_generate_uid', [ $this, 'inherit_from_source_object' ], 10, 3 );

		// Polylang ilişkiyi kaydettikten sonra grubu normalize et.
		add_action( 'pll_save_post', [ $this, 'sync_post_group' ], 20, 3 );
		add_action( 'pll_save_term', [ $this, 'sync_term_group' ], 20, 3 );
	}

	/* ------------------------------------------------------ 1. katman */

	/**
	 * Çeviri ekle ekranında kaynak nesnenin kimliğini devralır.
	 *
	 * @param string|null $uid
	 * @return string|null
	 */
	public function inherit_from_source_object( $uid, string $object_type, int $object_id ) {
		if ( null !== $uid ) {
			return $uid;
		}

		unset( $object_id );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- yalnızca okuma; yazma yetkisi çağıran tarafta doğrulanır.
		if ( 'topic' === $object_type && isset( $_GET['from_tag'] ) ) {
			$from = absint( $_GET['from_tag'] );

			if ( $from > 0 ) {
				$source = (string) get_term_meta( $from, MetaRegistry::TOPIC_UID, true );

				return UidGenerator::is_valid_format( $source ) ? $source : null;
			}
		}

		if ( in_array( $object_type, [ 'expert', 'page_group' ], true ) && isset( $_GET['from_post'] ) ) {
			$from = absint( $_GET['from_post'] );

			if ( $from > 0 ) {
				$key    = 'expert' === $object_type ? MetaRegistry::EXPERT_ENTITY_UID : MetaRegistry::PAGE_GROUP_UID;
				$source = (string) get_post_meta( $from, $key, true );

				return UidGenerator::is_valid_format( $source ) ? $source : null;
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return null;
	}

	/* ------------------------------------------------------ 2. katman */

	public function sync_post_group( int $post_id, $post = null, $translations = null ): void {
		unset( $translations );

		$post_type = $post instanceof \WP_Post ? $post->post_type : (string) get_post_type( $post_id );

		if ( ExpertPostType::SLUG === $post_type ) {
			$this->normalize_post_group( $post_id, MetaRegistry::EXPERT_ENTITY_UID, UidGenerator::PREFIX_EXPERT, 'expert' );

			return;
		}

		if ( in_array( $post_type, Settings::eligible_post_types(), true ) ) {
			$this->normalize_post_group( $post_id, MetaRegistry::PAGE_GROUP_UID, UidGenerator::PREFIX_GROUP, 'page_group' );
		}
	}

	public function sync_term_group( int $term_id, $taxonomy = null, $translations = null ): void {
		unset( $translations );

		$taxonomy = is_string( $taxonomy ) ? $taxonomy : (string) get_term_field( 'taxonomy', $term_id );

		if ( MedicalTopicTaxonomy::SLUG !== $taxonomy ) {
			return;
		}

		$this->normalize_term_group( $term_id );
	}

	/* ---------------------------------------------------- normalize */

	/**
	 * @return array{changed:int,uid:string}
	 */
	public function normalize_post_group( int $post_id, string $meta_key, string $prefix, string $object_type ): array {
		$adapter = Languages::adapter();
		$group   = $adapter->post_translations( $post_id );

		$members = [];
		foreach ( $group as $lang => $id ) {
			$members[] = [
				'id'   => (int) $id,
				'lang' => (string) $lang,
				'uid'  => (string) get_post_meta( (int) $id, $meta_key, true ),
			];
		}

		$canonical = GroupIdentity::choose_canonical( $members, $adapter->default_language() );

		if ( null === $canonical ) {
			$canonical = ( new UidGenerator() )->mint( $prefix, $object_type, $post_id, $meta_key, false );
		}

		$changed = 0;
		foreach ( $members as $member ) {
			if ( $member['uid'] === $canonical ) {
				continue;
			}

			update_post_meta( $member['id'], $meta_key, $canonical );
			++$changed;
		}

		return [
			'changed' => $changed,
			'uid'     => $canonical,
		];
	}

	/**
	 * @return array{changed:int,uid:string,replaced:array<string,string>}
	 */
	public function normalize_term_group( int $term_id ): array {
		$adapter = Languages::adapter();
		$group   = $adapter->term_translations( $term_id );

		$members = [];
		foreach ( $group as $lang => $id ) {
			$members[] = [
				'id'   => (int) $id,
				'lang' => (string) $lang,
				'uid'  => (string) get_term_meta( (int) $id, MetaRegistry::TOPIC_UID, true ),
			];
		}

		$canonical = GroupIdentity::choose_canonical( $members, $adapter->default_language() );

		if ( null === $canonical ) {
			$canonical = ( new UidGenerator() )->mint(
				UidGenerator::PREFIX_TOPIC,
				'topic',
				$term_id,
				MetaRegistry::TOPIC_UID,
				true
			);
		}

		$changed  = 0;
		$replaced = [];

		foreach ( $members as $member ) {
			if ( $member['uid'] === $canonical ) {
				continue;
			}

			if ( '' !== $member['uid'] && UidGenerator::is_valid_format( $member['uid'] ) ) {
				// Eski kimliğe yapılan atıflar sonradan yeniden yazılmalı.
				$replaced[ $member['uid'] ] = $canonical;
			}

			update_term_meta( $member['id'], MetaRegistry::TOPIC_UID, $canonical );
			++$changed;
		}

		return [
			'changed'  => $changed,
			'uid'      => $canonical,
			'replaced' => $replaced,
		];
	}
}
