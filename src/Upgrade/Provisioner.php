<?php
/**
 * Çalışma anı sağlama (provisioning) ve güvenli yükseltme.
 *
 * SORUN: Yetenekler yalnızca `register_activation_hook` içinde veriliyordu.
 * Eklenti canlıya dosya kopyalayarak / git ile güncellendiğinde bu kanca HİÇ
 * çalışmaz. Administrator `dla_manage_experts` yeteneğini almadığı için:
 *
 *   - `dla_expert` CPT menüsü hiç görünmez (WordPress menüyü
 *     `cap->edit_posts` ile gizler),
 *   - dolayısıyla uzman / kaynak / konu oluşturulamaz,
 *   - dolayısıyla uzman ve konu açılır listeleri boş kalır,
 *   - dolayısıyla shortcode gösterilecek olgu bulamaz ve boş döner.
 *
 * Bildirilen tüm arayüz belirtileri bu tek nedenden türüyordu.
 *
 * ÇÖZÜM: Yeteneklerin varlığı her admin isteğinde ucuz biçimde doğrulanır
 * ve eksikse idempotent olarak tamamlanır. Veri silinmez, taşınmaz,
 * yeniden etkinleştirme gerekmez.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Upgrade;

use DLA\MedicalTrust\Capability\Capabilities;
use DLA\MedicalTrust\Seed\StarterLibrary;
use DLA\MedicalTrust\Settings\Settings;
use DLA\MedicalTrust\Taxonomies\SourceTypeTaxonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Provisioner {

	private const NOTICE_REPAIRED = 'dla_mt_caps_repaired';
	private const NOTICE_FAILED   = 'dla_mt_caps_failed';
	private const OPTION_CATALOG_SYNC_VERSION = 'dla_mt_catalog_sync_version';

	public function register(): void {
		add_action( 'admin_init', [ $this, 'maybe_provision' ], 5 );
		add_action( 'admin_notices', [ $this, 'render_notices' ] );
	}

	/**
	 * Ucuz kontrol, gerekirse onarım.
	 *
	 * `get_option` autoload'dan, `get_role` bellekteki roller dizisinden
	 * okur — normal durumda ek sorgu maliyeti yoktur.
	 */
	public function maybe_provision(): void {
		if ( ! $this->is_provisioned() ) {
			$this->provision();
		}

		if ( $this->catalog_sync_needed() ) {
			( new StarterLibrary() )->synchronize_and_publish();
			update_option( self::OPTION_CATALOG_SYNC_VERSION, StarterLibrary::CATALOG_VERSION, false );
		}
	}

	private function catalog_sync_needed(): bool {
		return StarterLibrary::CATALOG_VERSION !== (string) get_option( self::OPTION_CATALOG_SYNC_VERSION, '' );
	}

	public function is_provisioned(): bool {
		if ( (int) get_option( Settings::OPTION_DB, 0 ) !== \DLA\MedicalTrust\DB_VERSION ) {
			return false;
		}

		return $this->administrator_has_capabilities();
	}

	public function administrator_has_capabilities(): bool {
		$admin = get_role( 'administrator' );

		// Administrator rolü yoksa yapabileceğimiz bir şey yok; sonsuz
		// döngüye girmemek için "sağlandı" sayılır.
		if ( ! $admin instanceof \WP_Role ) {
			return true;
		}

		foreach ( Capabilities::role_grantable() as $cap ) {
			if ( ! $admin->has_cap( $cap ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Idempotent. Var olan hiçbir veriyi değiştirmez; yalnızca eksikleri
	 * tamamlar.
	 */
	public function provision(): void {
		$was_missing = ! $this->administrator_has_capabilities();

		Capabilities::add_to_roles();
		SourceTypeTaxonomy::ensure_terms();

		if ( ! is_array( get_option( Settings::OPTION, null ) ) ) {
			add_option( Settings::OPTION, Settings::defaults(), '', true );
		}

		$sentinel = '__dla_mt_missing__';
		$current  = get_option( Settings::OPTION_LIBRARY, $sentinel );

		if ( $sentinel === $current ) {
			add_option( Settings::OPTION_LIBRARY, 1, '', true );
		}

		update_option( Settings::OPTION_DB, \DLA\MedicalTrust\DB_VERSION, true );
		Settings::flush_cache();
		$this->refresh_current_user_capabilities();

		if ( ! $this->administrator_has_capabilities() ) {
			set_transient( self::NOTICE_FAILED, 1, 5 * MINUTE_IN_SECONDS );

			return;
		}

		if ( $was_missing ) {
			// Yönetici neden menünün birden ortaya çıktığını bilsin.
			set_transient( self::NOTICE_REPAIRED, 1, 5 * MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Yetenekler onarıldığında mevcut isteğin kullanıcı nesnesini tazeler.
	 *
	 * Gerekli, çünkü `admin_init` mevcut kullanıcı kurulduktan SONRA çalışır:
	 * `WP_User::allcaps` bayat kalırsa `admin_menu` (daha da sonra çalışır)
	 * menüyü yine gizler ve onarım ancak BİR SONRAKİ sayfa yüklemesinde
	 * görünür olur. Bu tazeleme, düzeltmenin aynı istekte etkili olmasını
	 * sağlar.
	 */
	private function refresh_current_user_capabilities(): void {
		$user = wp_get_current_user();

		if ( $user instanceof \WP_User && $user->ID > 0 ) {
			$user->get_role_caps();
		}
	}

	public function render_notices(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( get_transient( self::NOTICE_FAILED ) ) {
			delete_transient( self::NOTICE_FAILED );

			printf(
				'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'DLA Medical Trust:', 'dla-medical-trust' ),
				esc_html__( 'Yönetici rolüne gerekli yetkiler verilemedi. "Medical Trust" menüsü görünmeyecektir. Bir rol yönetimi eklentisi yetkileri kilitliyor olabilir; eklentiyi devre dışı bırakıp yeniden etkinleştirmeyi deneyin.', 'dla-medical-trust' )
			);

			return;
		}

		if ( get_transient( self::NOTICE_REPAIRED ) ) {
			delete_transient( self::NOTICE_REPAIRED );

			printf(
				'<div class="notice notice-success is-dismissible"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'DLA Medical Trust:', 'dla-medical-trust' ),
				esc_html__( 'Eksik yönetici yetkileri tamamlandı. "Medical Trust" menüsü artık görünür olmalı. Bu, eklentinin yeniden etkinleştirilmeden güncellendiği kurulumlarda beklenen bir onarımdır; hiçbir veri değiştirilmedi.', 'dla-medical-trust' )
			);
		}
	}
}
