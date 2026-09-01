<?php
/**
 * Ayarlar ekranı.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Admin;

use DLA\MedicalTrust\Capability\Capabilities;
use DLA\MedicalTrust\Domain\Enum\ReviewPolicy;
use DLA\MedicalTrust\PostTypes\ExpertPostType;
use DLA\MedicalTrust\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsPage {

	private const SLUG         = 'dla-mt-settings';
	private const NONCE_ACTION = 'dla_mt_save_settings';
	private const NONCE_NAME   = 'dla_mt_settings_nonce';

	private const REPAIR_ACTION = 'dla_mt_repair_identities';
	private const REPAIR_NONCE  = 'dla_mt_repair_nonce';
	private const REPAIR_NOTICE = 'dla_mt_repair_report';

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_page' ] );
		add_action( 'admin_post_dla_mt_save_settings', [ $this, 'handle_save' ] );
		add_action( 'admin_post_' . self::REPAIR_ACTION, [ $this, 'handle_repair' ] );
	}

	public function add_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . ExpertPostType::SLUG,
			__( 'Medical Trust Ayarları', 'dla-medical-trust' ),
			__( 'Ayarlar', 'dla-medical-trust' ),
			Capabilities::MANAGE_SOURCES,
			self::SLUG,
			[ $this, 'render' ]
		);
	}

	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE_SOURCES ) ) {
			wp_die( esc_html__( 'Bu sayfaya erişim yetkiniz yok.', 'dla-medical-trust' ) );
		}

		$settings = Settings::all();
		$org      = (array) $settings['organization'];
		$policies = (array) $settings['review_policies'];

		echo '<div class="wrap">';
		printf( '<h1>%s</h1>', esc_html__( 'Medical Trust Ayarları', 'dla-medical-trust' ) );

		if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html__( 'Ayarlar kaydedildi.', 'dla-medical-trust' )
			);
		}

		printf( '<form method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		echo '<input type="hidden" name="action" value="dla_mt_save_settings">';

		/* ------------------------------------------------ Organizasyon */
		printf( '<h2>%s</h2>', esc_html__( 'Organizasyon', 'dla-medical-trust' ) );
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'İçeriği editoryal ekip hazırladığında yazar olarak bu varlık bildirilir. Tıbbi inceleme her durumda uzmana aittir.', 'dla-medical-trust' )
		);

		echo '<table class="form-table" role="presentation"><tbody>';
		Field::text( 'organization_name', __( 'Kurum adı', 'dla-medical-trust' ), (string) ( $org['name'] ?? '' ) );
		Field::url( 'organization_url', __( 'Kurum adresi', 'dla-medical-trust' ), (string) ( $org['url'] ?? '' ) );
		Field::number( 'organization_logo_id', __( 'Logo ek ID', 'dla-medical-trust' ), (int) ( $org['logo_id'] ?? 0 ), 0, PHP_INT_MAX );
		echo '</tbody></table>';

		/* --------------------------------------------------- Politika */
		printf( '<h2>%s</h2>', esc_html__( 'İnceleme politikaları', 'dla-medical-trust' ) );
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Konular bu politikalardan birine bağlanır. Konu başına serbest sayı girmek yerine adlandırılmış politika kullanılır; böylece tutarlılık korunur.', 'dla-medical-trust' )
		);

		echo '<table class="form-table" role="presentation"><tbody>';

		foreach ( ReviewPolicy::values() as $slug ) {
			$policy = is_array( $policies[ $slug ] ?? null ) ? $policies[ $slug ] : ReviewPolicy::factory_defaults()[ $slug ];

			printf( '<tr><th scope="row">%s</th><td>', esc_html( ReviewPolicy::label( $slug ) ) );

			printf(
				'<label>%1$s <input type="number" name="policy_%2$s_interval" value="%3$d" min="1" max="120" step="1" class="small-text"></label> &nbsp; ',
				esc_html__( 'İnceleme aralığı (ay)', 'dla-medical-trust' ),
				esc_attr( $slug ),
				(int) $policy['interval_months']
			);

			printf(
				'<label>%1$s <input type="number" name="policy_%2$s_age" value="%3$d" min="1" max="50" step="1" class="small-text"></label>',
				esc_html__( 'Kaynak yaş sınırı (yıl)', 'dla-medical-trust' ),
				esc_attr( $slug ),
				(int) $policy['max_source_age_years']
			);

			echo '</td></tr>';
		}

		echo '</tbody></table>';

		/* -------------------------------------------------- Çözümleme */
		printf( '<h2>%s</h2>', esc_html__( 'Kaynak çözümleme', 'dla-medical-trust' ) );
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Bu değerler M2\'deki çözümleyici tarafından kullanılacak. M1\'de yalnızca saklanır.', 'dla-medical-trust' )
		);

		echo '<table class="form-table" role="presentation"><tbody>';

		Field::number(
			'min_topic_proximity',
			__( 'Minimum konu yakınlığı', 'dla-medical-trust' ),
			(int) $settings['min_topic_proximity'],
			0,
			100,
			__( 'Bu tabanın altındaki hiçbir kaynak seçilmez — slot boş kalacak olsa bile. Editör önceliği dahil hiçbir puan bu kısıtı atlayamaz.', 'dla-medical-trust' )
		);

		Field::number(
			'diversity_band',
			__( 'Çeşitlilik bandı', 'dla-medical-trust' ),
			(int) $settings['diversity_band'],
			0,
			200,
			__( 'En yüksek skora bu kadar yakın adaylar aynı seçim havuzuna girer ve sayfalar arasında deterministik olarak dağıtılır. Daraltmak çeşitliliği azaltır, genişletmek kalite eşiğini gevşetir.', 'dla-medical-trust' )
		);

		Field::number(
			'max_tier_size',
			__( 'Azami havuz boyutu', 'dla-medical-trust' ),
			(int) $settings['max_tier_size'],
			1,
			50,
			__( 'Bant geniş kaldığında havuzu skor sırasına göre kırpar. Kalitenin sessizce sulanmasına karşı emniyet valfi.', 'dla-medical-trust' )
		);

		echo '</tbody></table>';

		/* ------------------------------------------------------ Kapsam */
		printf( '<h2>%s</h2>', esc_html__( 'Kapsam ve iş akışı', 'dla-medical-trust' ) );

		echo '<table class="form-table" role="presentation"><tbody>';

		printf( '<tr><th scope="row">%s</th><td><fieldset>', esc_html__( 'Kapsamdaki içerik türleri', 'dla-medical-trust' ) );

		$eligible = Settings::eligible_post_types();
		foreach ( Settings::selectable_post_types() as $type => $label ) {
			printf(
				'<label style="display:block;margin-bottom:4px"><input type="checkbox" name="eligible_post_types[]" value="%1$s"%2$s> %3$s</label>',
				esc_attr( $type ),
				checked( in_array( $type, $eligible, true ), true, false ),
				esc_html( (string) $label )
			);
		}

		printf(
			'</fieldset><p class="description">%s</p></td></tr>',
			esc_html__( 'Yalnızca gerçekten tıbbi içerik barındıran türleri seçin. Sinyalin seyrelmemesi için kapsamı dar tutmak kasıtlıdır.', 'dla-medical-trust' )
		);

		Field::checkbox(
			'require_signoff_reference',
			__( 'Onay dayanağı', 'dla-medical-trust' ),
			(bool) $settings['require_signoff_reference'],
			__( 'İnceleme kaydında onay dayanağı zorunlu olsun', 'dla-medical-trust' ),
			__( 'Doktor sisteme girmediğinde kaydın tek dayanağı kaydeden kişinin beyanıdır. Kısa bir referans ("12.03.2026 tarihli e-posta onayı") o beyanı denetlenebilir kılar. Ekip doldurmayacaksa kapatın — çöp dolan zorunlu alan hiç olmamasından kötüdür.', 'dla-medical-trust' )
		);

		Field::checkbox(
			'automatic_injection',
			__( 'Otomatik Trust Box yerleştirme', 'dla-medical-trust' ),
			(bool) ( $settings['automatic_injection'] ?? false ),
			__( 'Uygun tıbbi içeriğin the_content çıktısına Trust Box ekle', 'dla-medical-trust' ),
			__( 'Varsayılan kapalıdır. Avada Global Layout içinde shortcode kullanılıyorsa kapalı bırakın; böylece çift kutu oluşmaz.', 'dla-medical-trust' )
		);

		Field::select(
			'injection_position',
			__( 'Otomatik yerleştirme konumu', 'dla-medical-trust' ),
			(string) ( $settings['injection_position'] ?? 'after' ),
			[
				'after'  => __( 'İçerikten sonra', 'dla-medical-trust' ),
				'before' => __( 'İçerikten önce', 'dla-medical-trust' ),
			],
			__( 'Yalnızca otomatik yerleştirme açık olduğunda kullanılır.', 'dla-medical-trust' )
		);

		Field::checkbox(
			'retain_data_on_uninstall',
			__( 'Kaldırma davranışı', 'dla-medical-trust' ),
			(bool) $settings['retain_data_on_uninstall'],
			__( 'Eklenti silindiğinde veriler korunsun', 'dla-medical-trust' ),
			__( 'Varsayılan açık. Küratörlü bir kaynak kütüphanesini kaza ile silmek geri alınamaz bir kayıptır.', 'dla-medical-trust' )
		);

		echo '</tbody></table>';

		submit_button();
		echo '</form>';

		$this->render_repair_section();

		echo '</div>';
	}

	/**
	 * Kimlik onarımı — çeviri gruplarında ayrışmış UID'leri düzeltir.
	 */
	private function render_repair_section(): void {
		printf( '<hr><h2>%s</h2>', esc_html__( 'Çeviri kimlik onarımı', 'dla-medical-trust' ) );

		printf(
			'<p class="description" style="max-width:70ch">%s</p>',
			esc_html__( 'Bir çeviri grubundaki tüm konular aynı konu kimliğini, tüm uzmanlar aynı varlık kimliğini taşımalıdır. Bu araç ayrışmış grupları tarar, düzeltir ve değişen kimliklere yapılan atıfları yeniden yazar. Çalıştırmak güvenlidir: ikinci çalıştırma hiçbir şey değiştirmez.', 'dla-medical-trust' )
		);

		$report = get_transient( self::REPAIR_NOTICE );

		if ( is_array( $report ) ) {
			delete_transient( self::REPAIR_NOTICE );
			$this->render_repair_report( $report );
		}

		printf( '<form method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
		wp_nonce_field( self::REPAIR_ACTION, self::REPAIR_NONCE );
		printf( '<input type="hidden" name="action" value="%s">', esc_attr( self::REPAIR_ACTION ) );
		submit_button( __( 'Kimlikleri tara ve onar', 'dla-medical-trust' ), 'secondary', 'submit', false );
		echo '</form>';
	}

	/**
	 * @param array<string,mixed> $report
	 */
	private function render_repair_report( array $report ): void {
		$rows = [
			__( 'Taranan konu grubu', 'dla-medical-trust' )       => (int) ( $report['topics_scanned'] ?? 0 ),
			__( 'Düzeltilen konu kimliği', 'dla-medical-trust' )  => (int) ( $report['topics_fixed'] ?? 0 ),
			__( 'Taranan uzman grubu', 'dla-medical-trust' )      => (int) ( $report['experts_scanned'] ?? 0 ),
			__( 'Düzeltilen uzman kimliği', 'dla-medical-trust' ) => (int) ( $report['experts_fixed'] ?? 0 ),
			__( 'Taranan sayfa grubu', 'dla-medical-trust' )      => (int) ( $report['pages_scanned'] ?? 0 ),
			__( 'Düzeltilen sayfa kimliği', 'dla-medical-trust' ) => (int) ( $report['pages_fixed'] ?? 0 ),
			__( 'Yeniden yazılan atıf', 'dla-medical-trust' )     => (int) ( $report['references_rewritten'] ?? 0 ),
		];

		echo '<div class="notice notice-success"><p><strong>';
		esc_html_e( 'Onarım tamamlandı.', 'dla-medical-trust' );
		echo '</strong></p><table class="widefat striped" style="margin:0 0 10px"><tbody>';

		foreach ( $rows as $label => $value ) {
			printf( '<tr><td><strong>%s</strong></td><td>%d</td></tr>', esc_html( (string) $label ), (int) $value );
		}

		echo '</tbody></table>';

		if ( ! empty( $report['library_version_bumped'] ) ) {
			printf(
				'<p>%s</p>',
				esc_html__( 'Kimlikler değiştiği için kütüphane sürümü artırıldı; çözümleme cache\'i tembel olarak yeniden hesaplanacak.', 'dla-medical-trust' )
			);
		}

		echo '</div>';
	}

	public function handle_repair(): void {
		if ( ! current_user_can( Capabilities::MANAGE_SOURCES ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'dla-medical-trust' ) );
		}

		check_admin_referer( self::REPAIR_ACTION, self::REPAIR_NONCE );

		$report = ( new \DLA\MedicalTrust\I18n\IdentityRepair() )->run();

		set_transient( self::REPAIR_NOTICE, $report, 60 );

		wp_safe_redirect(
			add_query_arg(
				[
					'post_type' => ExpertPostType::SLUG,
					'page'      => self::SLUG,
				],
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	public function handle_save(): void {
		if ( ! current_user_can( Capabilities::MANAGE_SOURCES ) ) {
			wp_die( esc_html__( 'Bu işlem için yetkiniz yok.', 'dla-medical-trust' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- check_admin_referer doğruladı.
		$policies = [];
		foreach ( ReviewPolicy::values() as $slug ) {
			$policies[ $slug ] = [
				'interval_months'      => $_POST[ 'policy_' . $slug . '_interval' ] ?? null,
				'max_source_age_years' => $_POST[ 'policy_' . $slug . '_age' ] ?? null,
			];
		}

		Settings::update(
			[
				'organization'              => [
					'name'    => wp_unslash( $_POST['organization_name'] ?? '' ),
					'url'     => wp_unslash( $_POST['organization_url'] ?? '' ),
					'logo_id' => $_POST['organization_logo_id'] ?? 0,
				],
				'review_policies'           => $policies,
				'min_topic_proximity'       => $_POST['min_topic_proximity'] ?? 55,
				'diversity_band'            => $_POST['diversity_band'] ?? 10,
				'max_tier_size'             => $_POST['max_tier_size'] ?? 6,
				'eligible_post_types'       => (array) ( $_POST['eligible_post_types'] ?? [] ),
				'require_signoff_reference' => isset( $_POST['require_signoff_reference'] ),
				'automatic_injection'       => isset( $_POST['automatic_injection'] ),
				'injection_position'        => wp_unslash( $_POST['injection_position'] ?? 'after' ),
				'retain_data_on_uninstall'  => isset( $_POST['retain_data_on_uninstall'] ),
			]
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		wp_safe_redirect(
			add_query_arg(
				[
					'post_type' => ExpertPostType::SLUG,
					'page'      => self::SLUG,
					'updated'   => '1',
				],
				admin_url( 'edit.php' )
			)
		);
		exit;
	}
}
