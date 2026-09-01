<?php
/**
 * Kullanıcı bazlı inceleme yetkisi (v0.2 §6).
 *
 * Bu yetenek hiçbir role toplu verilmez. Doktor WordPress'e girmediği için
 * v0.1'deki rol tabanlı koruma düştü; yerine gelen koruma budur.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Admin;

use DLA\MedicalTrust\Capability\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UserProfileFields {

	private const NONCE_ACTION = 'dla_mt_save_user_caps';
	private const NONCE_NAME   = 'dla_mt_user_caps_nonce';

	public function register(): void {
		add_action( 'show_user_profile', [ $this, 'render' ] );
		add_action( 'edit_user_profile', [ $this, 'render' ] );
		add_action( 'personal_options_update', [ $this, 'save' ] );
		add_action( 'edit_user_profile_update', [ $this, 'save' ] );
	}

	public function render( \WP_User $user ): void {
		// Yalnızca kullanıcı yönetme yetkisi olanlar bu ayarı görebilir ve
		// değiştirebilir. Kullanıcı kendi yetkisini veremez.
		if ( ! current_user_can( 'promote_users' ) ) {
			return;
		}

		$granted = Capabilities::has_direct_review_capability( $user->ID );

		printf( '<h2>%s</h2>', esc_html__( 'Medical Trust yetkileri', 'dla-medical-trust' ) );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		echo '<table class="form-table" role="presentation"><tbody><tr>';
		printf( '<th scope="row">%s</th><td>', esc_html__( 'Tıbbi inceleme kaydı', 'dla-medical-trust' ) );

		printf(
			'<label><input type="checkbox" name="dla_mt_grant_review" value="1"%1$s> %2$s</label>',
			checked( $granted, true, false ),
			esc_html__( 'Bu kullanıcı tıbbi inceleme kaydı oluşturabilir', 'dla-medical-trust' )
		);

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Bu yetki kasıtlı olarak hiçbir role toplu verilmez — Administrator dahil. Yalnızca doktorla gerçekten temas eden, onayı birinci elden alan kişilere verin. İçeriği yazan editörler bu yetkiye sahip olmamalıdır; aksi halde "içerik editi" ile "tıbbi inceleme" ayrımı ortadan kalkar.', 'dla-medical-trust' )
		);

		echo '</td></tr></tbody></table>';
	}

	public function save( int $user_id ): void {
		if ( ! current_user_can( 'promote_users' ) ) {
			return;
		}

		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_NAME ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- yukarıda doğrulandı.
		$granted = isset( $_POST['dla_mt_grant_review'] );

		Capabilities::set_review_capability( $user_id, $granted );
	}
}
