<?php
/**
 * Uzman düzenleme alanları.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Admin;

use DLA\MedicalTrust\Capability\Capabilities;
use DLA\MedicalTrust\Meta\MetaRegistry;
use DLA\MedicalTrust\PostTypes\ExpertPostType;
use DLA\MedicalTrust\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ExpertMetaBox {

	private const NONCE_ACTION = 'dla_mt_save_expert';
	private const NONCE_NAME   = 'dla_mt_expert_nonce';

	public function register(): void {
		add_action( 'add_meta_boxes_' . ExpertPostType::SLUG, [ $this, 'add_meta_box' ] );
		add_action( 'save_post_' . ExpertPostType::SLUG, [ $this, 'save' ], 10, 2 );
	}

	public function add_meta_box(): void {
		add_meta_box(
			'dla-mt-expert',
			__( 'Uzman Bilgileri', 'dla-medical-trust' ),
			[ $this, 'render' ],
			ExpertPostType::SLUG,
			'normal',
			'high'
		);
	}

	public function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$uid         = (string) get_post_meta( $post->ID, MetaRegistry::EXPERT_ENTITY_UID, true );
		$honorific   = (string) get_post_meta( $post->ID, MetaRegistry::EXPERT_HONORIFIC, true );
		$job_title   = (string) get_post_meta( $post->ID, MetaRegistry::EXPERT_JOB_TITLE, true );
		$credentials = (array) get_post_meta( $post->ID, MetaRegistry::EXPERT_CREDENTIALS, true );
		$sameas      = (array) get_post_meta( $post->ID, MetaRegistry::EXPERT_SAMEAS, true );
		$bio         = (string) get_post_meta( $post->ID, MetaRegistry::EXPERT_SHORT_BIO, true );
		$profile_id  = (int) get_post_meta( $post->ID, MetaRegistry::EXPERT_PROFILE_PAGE, true );

		echo '<table class="form-table" role="presentation"><tbody>';

		Field::text(
			'dla_mt_honorific',
			__( 'Unvan', 'dla-medical-trust' ),
			$honorific,
			__( 'Örn. "Op. Dr." — dile göre çevrilir.', 'dla-medical-trust' )
		);

		Field::text(
			'dla_mt_job_title',
			__( 'Uzmanlık başlığı', 'dla-medical-trust' ),
			$job_title,
			__( 'Örn. "Plastik, Rekonstrüktif ve Estetik Cerrahi Uzmanı".', 'dla-medical-trust' )
		);

		Field::textarea(
			'dla_mt_credentials',
			__( 'Üyelikler ve unvanlar', 'dla-medical-trust' ),
			implode( "\n", array_map( 'strval', $credentials ) ),
			__( 'Her satıra bir tane. Örn. TPRECD, EURAPS, ISAPS, ASPS.', 'dla-medical-trust' ),
			4
		);

		Field::post_select(
			'dla_mt_profile_page_id',
			__( 'Kanonik profil sayfası', 'dla-medical-trust' ),
			$profile_id,
			Settings::eligible_post_types(),
			__( 'URL değil post ID saklanır; slug değişirse bağlantı kırılmaz.', 'dla-medical-trust' )
		);

		Field::textarea(
			'dla_mt_sameas',
			__( 'Doğrulanabilir dış profiller', 'dla-medical-trust' ),
			implode( "\n", array_map( 'strval', $sameas ) ),
			__( 'Her satıra bir URL. Politikayı geçemeyen adresler kayıtta sessizce elenir.', 'dla-medical-trust' ),
			4
		);

		Field::textarea(
			'dla_mt_short_bio',
			__( 'Kısa biyografi', 'dla-medical-trust' ),
			$bio,
			__( 'Blokta gösterilecek 1–2 cümle. İzin verilen etiketler: p, br, strong, em, ul, ol, li, a.', 'dla-medical-trust' ),
			4
		);

		Field::readonly_row(
			__( 'Varlık kimliği', 'dla-medical-trust' ),
			'' !== $uid ? $uid : __( 'İlk kayıtta üretilecek', 'dla-medical-trust' ),
			__( 'Dil-nötr kalıcı kimlik. Bir kez üretilir, asla değişmez.', 'dla-medical-trust' )
		);

		echo '</tbody></table>';
	}

	public function save( int $post_id, \WP_Post $post ): void {
		unset( $post );

		if ( ! Field::can_save( self::NONCE_NAME, self::NONCE_ACTION, Capabilities::MANAGE_EXPERTS ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Field::can_save doğruladı.
		$map = [
			MetaRegistry::EXPERT_HONORIFIC    => 'dla_mt_honorific',
			MetaRegistry::EXPERT_JOB_TITLE    => 'dla_mt_job_title',
			MetaRegistry::EXPERT_CREDENTIALS  => 'dla_mt_credentials',
			MetaRegistry::EXPERT_SAMEAS       => 'dla_mt_sameas',
			MetaRegistry::EXPERT_SHORT_BIO    => 'dla_mt_short_bio',
			MetaRegistry::EXPERT_PROFILE_PAGE => 'dla_mt_profile_page_id',
		];

		foreach ( $map as $meta_key => $field ) {
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}

			// update_post_meta, register_post_meta'daki sanitize_callback'i çalıştırır.
			update_post_meta( $post_id, $meta_key, wp_unslash( $_POST[ $field ] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
