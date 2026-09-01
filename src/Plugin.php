<?php
/**
 * Tek giriş noktası — bileşenleri kaydeder, başka iş yapmaz.
 *
 * M1 + M2 + M3 + M4 kapsamı: depolama, çözümleme, korumalı inceleme çekirdeği
 * ve tema bağımsız Trust Box render'ı. M6, aynı kanonik gerçekleri salt-okunur
 * veri kontratı olarak verir; JSON-LD ve ikinci bir schema motoru bilinçli olarak YOKTUR.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust;

use DLA\MedicalTrust\Admin\ExpertMetaBox;
use DLA\MedicalTrust\Admin\MedicalContentList;
use DLA\MedicalTrust\Admin\PageMedicalMetaBox;
use DLA\MedicalTrust\Admin\ResolverExplanationPanel;
use DLA\MedicalTrust\Admin\ReadinessPanel;
use DLA\MedicalTrust\Admin\ReviewWorkflowMetaBox;
use DLA\MedicalTrust\Admin\SettingsPage;
use DLA\MedicalTrust\Admin\SourceMetaBox;
use DLA\MedicalTrust\Admin\TopicTermFields;
use DLA\MedicalTrust\Admin\UserProfileFields;
use DLA\MedicalTrust\I18n\IdentitySync;
use DLA\MedicalTrust\Identity\UidGenerator;
use DLA\MedicalTrust\Integration\TrustComponent;
use DLA\MedicalTrust\Meta\MetaRegistry;
use DLA\MedicalTrust\PostTypes\ExpertPostType;
use DLA\MedicalTrust\PostTypes\SourcePostType;
use DLA\MedicalTrust\Repository\TopicRepository;

use DLA\MedicalTrust\Review\ReviewMetaGuard;
use DLA\MedicalTrust\Settings\Settings;
use DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy;
use DLA\MedicalTrust\Taxonomies\SourceTypeTaxonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	public function boot(): void {
		// --- Depolama ---
		( new ExpertPostType() )->register();
		( new SourcePostType() )->register();
		( new MedicalTopicTaxonomy() )->register();
		( new SourceTypeTaxonomy() )->register();

		add_action(
			'init',
			static function (): void {
				( new MetaRegistry() )->register();
			},
			10
		);

		// --- Kimlik (M2.0) ---
		add_action(
			'init',
			static function (): void {
				( new UidGenerator() )->register();
			},
			11
		);
		( new IdentitySync() )->register();
		( new ReviewMetaGuard() )->register();
		( new TrustComponent() )->register();
		require_once DLA_MT_DIR . 'src/Integration/template-tags.php';
		require_once DLA_MT_DIR . 'src/Contract/template-tags.php';

		// --- Kütüphane sürüm sayacı ---
		// YALNIZCA cache geçersiz kılma sinyali. Seçime GİRMEZ.
		add_action( 'save_post_' . SourcePostType::SLUG, [ $this, 'bump_library_version' ], 20 );
		add_action( 'transition_post_status', [ $this, 'bump_on_status_change' ], 20, 3 );
		add_action( 'deleted_post', [ $this, 'bump_on_delete' ], 20, 2 );
		add_action( 'set_object_terms', [ $this, 'bump_on_terms_changed' ], 20, 6 );
		add_action( 'created_' . MedicalTopicTaxonomy::SLUG, [ $this, 'bump_library_version' ], 20 );
		add_action( 'edited_' . MedicalTopicTaxonomy::SLUG, [ $this, 'bump_library_version' ], 20 );
		add_action( 'delete_' . MedicalTopicTaxonomy::SLUG, [ $this, 'bump_library_version' ], 20 );

		// --- Admin ---
		if ( is_admin() ) {
			( new ExpertMetaBox() )->register();
			( new SourceMetaBox() )->register();
			( new TopicTermFields() )->register();
			( new SettingsPage() )->register();
			( new UserProfileFields() )->register();
			( new PageMedicalMetaBox() )->register();
			( new ReviewWorkflowMetaBox() )->register();
			( new MedicalContentList() )->register();
			( new ReadinessPanel() )->register();
			( new ResolverExplanationPanel() )->register();
		}
	}

	public function bump_library_version(): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		Settings::bump_library_version();
		TopicRepository::flush_memo();
	}

	/**
	 * Kaynak durumu değiştiğinde (yayımlandı / emekliye ayrıldı / çöpe atıldı)
	 * uygunluk değişir; cache geçersiz kılınmalıdır.
	 */
	public function bump_on_status_change( string $new_status, string $old_status, $post ): void {
		if ( ! $post instanceof \WP_Post || SourcePostType::SLUG !== $post->post_type ) {
			return;
		}

		if ( $new_status === $old_status ) {
			return;
		}

		$this->bump_library_version();
	}

	/**
	 * @param int|string $post_id
	 */
	public function bump_on_delete( $post_id, $post = null ): void {
		if ( $post instanceof \WP_Post && SourcePostType::SLUG !== $post->post_type ) {
			return;
		}

		$this->bump_library_version();
	}

	/**
	 * Bir kaynağın konu veya tür ataması değiştiğinde aday havuzu değişir.
	 *
	 * @param int|string $object_id
	 * @param mixed      $terms
	 * @param mixed      $tt_ids
	 * @param mixed      $old_tt_ids
	 */
	public function bump_on_terms_changed( $object_id, $terms, $tt_ids, string $taxonomy, $append, $old_tt_ids ): void {
		unset( $terms, $append );

		if ( ! in_array( $taxonomy, [ MedicalTopicTaxonomy::SLUG, SourceTypeTaxonomy::SLUG ], true ) ) {
			return;
		}

		// Gerçekten değişmediyse sayaç artırılmaz — toplu içe aktarmada
		// gereksiz geçersiz kılma yapılmasın.
		$before = array_map( 'intval', (array) $old_tt_ids );
		$after  = array_map( 'intval', (array) $tt_ids );
		sort( $before );
		sort( $after );

		if ( $before === $after ) {
			return;
		}

		unset( $object_id );

		$this->bump_library_version();
	}
}
