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

	private const SEED_ACTION    = 'dla_mt_seed_sources';
	private const SEED_NONCE     = 'dla_mt_seed_nonce';
	private const SEED_NOTICE    = 'dla_mt_seed_report';
	private const PUBLISH_ACTION      = 'dla_mt_publish_pending_sources';
	private const SEED_PUBLISH_ACTION = 'dla_mt_seed_and_publish_sources';

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'admin_post_dla_mt_save_settings', [ $this, 'handle_save' ] );
		add_action( 'admin_post_' . self::REPAIR_ACTION, [ $this, 'handle_repair' ] );
		add_action( 'admin_post_' . self::SEED_ACTION, [ $this, 'handle_seed' ] );
		add_action( 'admin_post_' . self::PUBLISH_ACTION, [ $this, 'handle_publish_pending' ] );
		add_action( 'admin_post_' . self::SEED_PUBLISH_ACTION, [ $this, 'handle_seed_and_publish' ] );
	}

	/**
	 * Ayarlar sayfasindaki aranabilir icerik secicisini yukler.
	 *
	 * Bu kontrol uzman ekraninda da kullanilir; ayni dosya orada medya
	 * secicisini, burada ise yalnizca post aramasini baslatir.
	 */
	public function enqueue( string $hook ): void {
		if ( false === strpos( $hook, self::SLUG ) ) {
			return;
		}

		$script_path = DLA_MT_DIR . 'assets/js/dla-mt-admin-media.js';

		wp_enqueue_script(
			'dla-mt-admin-media',
			DLA_MT_URL . 'assets/js/dla-mt-admin-media.js',
			[],
			is_file( $script_path ) ? (string) filemtime( $script_path ) : \DLA\MedicalTrust\VERSION,
			true
		);

		wp_localize_script(
			'dla-mt-admin-media',
			'dlaMtPostSearch',
			[
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'action'    => PostSearch::ACTION,
				'nonce'     => wp_create_nonce( PostSearch::ACTION ),
				'noResults' => __( 'Eşleşen içerik bulunamadı.', 'dla-medical-trust' ),
				'error'     => __( 'Arama başarısız oldu; sayfayı yenileyip tekrar deneyin.', 'dla-medical-trust' ),
			]
		);
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
			'<p class="description" style="max-width:70ch">%s</p>',
			esc_html__( 'Bu bolum KLINIGI tanimlar, doktoru DEGIL. Icerigi editoryal ekip hazirladiginda yazar olarak bu kurum bildirilir. Doktorun adi, unvani ve FOTOGRAFI ayri yerdedir: Medical Trust -> Tibbi Uzmanlar.', 'dla-medical-trust' )
		);

		echo '<table class="form-table" role="presentation"><tbody>';

		Field::text(
			'organization_name',
			__( 'Kurum adi', 'dla-medical-trust' ),
			(string) ( $org['name'] ?? '' ),
			__( 'Klinigin / muayenehanenin adi. Buraya doktorun adini YAZMAYIN — doktor bilgisi uzman kaydindan gelir.', 'dla-medical-trust' )
		);

		Field::url(
			'organization_url',
			__( 'Kurum adresi', 'dla-medical-trust' ),
			(string) ( $org['url'] ?? '' ),
			__( 'Klinigin web adresi.', 'dla-medical-trust' )
		);

		$this->render_logo_field( (int) ( $org['logo_id'] ?? 0 ) );

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

		Field::color(
			'accent_color',
			__( 'Vurgu rengi', 'dla-medical-trust' ),
			(string) ( $settings['accent_color'] ?? '' ),
			__( 'Kutunun kenar cizgisi ve baglanti rengi. Bos birakirsaniz varsayilan kullanilir. Temanizin ana rengini girerek kutuyu siteye uydurabilirsiniz.', 'dla-medical-trust' )
		);

		Field::checkbox(
			'show_updated_date',
			__( 'Guncelleme tarihi', 'dla-medical-trust' ),
			(bool) ( $settings['show_updated_date'] ?? true ),
			__( 'Kutuda "Son guncelleme" tarihini goster', 'dla-medical-trust' ),
			__( 'Sayfanin WordPress guncelleme tarihinden otomatik gelir; hicbir veri girisi gerektirmez. Bu bir TIBBI INCELEME tarihi degildir ve kutuda ayri bir satirda, kendi etiketiyle gosterilir.', 'dla-medical-trust' )
		);

		Field::checkbox(
			'show_review_date',
			__( 'Tibbi inceleme tarihi', 'dla-medical-trust' ),
			(bool) ( $settings['show_review_date'] ?? false ),
			__( 'Kayitli tibbi inceleme tarihini goster', 'dla-medical-trust' ),
			__( 'Varsayilan kapali. Yalnizca yetkili bir kullanicinin kaydettigi GERCEK inceleme varsa gorunur; sistem bu tarihi asla kendisi uretmez. Guncelleme tarihinden tamamen bagimsizdir.', 'dla-medical-trust' )
		);

		Field::post_select(
			'default_expert_id',
			__( 'Varsayılan içerik sorumlusu', 'dla-medical-trust' ),
			(int) ( $settings['default_expert_id'] ?? 0 ),
			[ ExpertPostType::SLUG ],
			__( 'Sayfada ve konuda uzman seçilmemişse Trust Box bu uzmanı gösterir; böylece her sayfayı tek tek doldurmanız gerekmez. Devralma sırası: sayfa → konu → site geneli. Bu bir yazarlık veya tıbbi inceleme iddiası DEĞİLDİR; yalnızca içeriğin tıbbi sorumlusunu bildirir ve hiçbir inceleme tarihi üretmez.', 'dla-medical-trust' )
		);

		$this->render_default_expert_portrait( (int) ( $settings['default_expert_id'] ?? 0 ) );

		Field::post_search(
			'editorial_board_page_id',
			__( 'Yayın kurulu sayfası', 'dla-medical-trust' ),
			(int) ( $settings['editorial_board_page_id'] ?? 0 ),
			__( 'Trust Box altındaki editoryal bilgilendirme bandında bağlantı olarak gösterilir. Sayfa adıyla arayın veya post ID girin. Polylang çevirisi varsa ziyaretçinin dilindeki sayfaya otomatik gider; seçilmezse bant gösterilmez.', 'dla-medical-trust' )
		);

		Field::checkbox(
			'automatic_injection',
			__( 'Otomatik Trust Box yerleştirme', 'dla-medical-trust' ),
			(bool) ( $settings['automatic_injection'] ?? false ),
			__( 'Uygun tıbbi içeriğin the_content çıktısına Trust Box ekle', 'dla-medical-trust' ),
			__( 'Varsayılan kapalıdır. Avada Global Layout içinde shortcode kullanılıyorsa kapalı bırakın; böylece çift kutu oluşmaz.', 'dla-medical-trust' )
		);

		Field::checkbox(
			'article_summary_links_enabled',
			__( 'Yapay zekâ ile makale özeti', 'dla-medical-trust' ),
			(bool) ( $settings['article_summary_links_enabled'] ?? false ),
			__( 'Makale başlığının altında özet bağlantılarını göster', 'dla-medical-trust' ),
			__( 'Varsayılan kapalıdır. Açıldığında kapsamda seçili tekil sayfa, yazı ve portfolio içeriklerinde; içerikten önce minimal bir ChatGPT, Grok, Perplexity, Claude ve Gemini çubuğu görünür.', 'dla-medical-trust' )
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

		$this->render_seed_section();
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



	/**
	 * Kurum logosu alani — onizlemeli.
	 *
	 * Ham bir sayi kutusu, yanlis ek ID girildiginde hicbir geri bildirim
	 * vermiyordu; kullanicilar buraya doktor fotografinin ID sini giriyordu.
	 * Artik girilen ID nin neye karsilik geldigi aninda gorunuyor.
	 */
	private function render_logo_field( int $logo_id ): void {
		Field::number(
			'organization_logo_id',
			__( 'Kurum logosu (ek ID)', 'dla-medical-trust' ),
			$logo_id,
			0,
			PHP_INT_MAX,
			__( 'Klinigin LOGOSU. Trust Box icinde GOSTERILMEZ; yalnizca schema eklentisine kurum bilgisi olarak aktarilir. Doktor fotografi icin bu alani kullanmayin.', 'dla-medical-trust' )
		);

		if ( $logo_id < 1 ) {
			return;
		}

		$attachment = get_post( $logo_id );
		$is_image   = $attachment instanceof \WP_Post && 'attachment' === $attachment->post_type && wp_attachment_is_image( $logo_id );

		printf( '<tr><th scope="row">%s</th><td>', esc_html__( 'Girilen ek onizlemesi', 'dla-medical-trust' ) );

		if ( ! $is_image ) {
			printf(
				'<span style="color:#8e3a44">%s</span>',
				esc_html__( 'Bu ID bir gorsel ekine ait degil.', 'dla-medical-trust' )
			);
		} else {
			echo wp_get_attachment_image( $logo_id, 'thumbnail', false, [ 'style' => 'max-width:90px;height:auto;border:1px solid #dcdcde' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image API output.
			printf(
				'<p class="description">%s <a href="%s">%s</a></p>',
				esc_html( (string) $attachment->post_title ),
				esc_url( (string) get_edit_post_link( $logo_id ) ),
				esc_html__( 'ek kaydini ac', 'dla-medical-trust' )
			);
		}

		echo '</td></tr>';
	}

	/** The organization logo is deliberately not a substitute for the expert portrait. */
	private function render_default_expert_portrait( int $expert_id ): void {
		if ( $expert_id < 1 || ! ExpertPostType::is_valid_published_expert( $expert_id ) ) {
			return;
		}

		$image_id = (int) get_post_thumbnail_id( $expert_id );
		printf( '<tr><th scope="row">%s</th><td>', esc_html__( 'Trust Box portre onizlemesi', 'dla-medical-trust' ) );

		if ( $image_id > 0 && wp_attachment_is_image( $image_id ) ) {
			echo wp_get_attachment_image( $image_id, 'thumbnail', false, [ 'style' => 'width:72px;height:72px;object-fit:cover;border-radius:50%;border:1px solid #dcdcde' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core image API output.
		} else {
			echo '<span style="color:#8e3a44">' . esc_html__( 'Portre secilmedi; Trust Box fotosuz gorunecek.', 'dla-medical-trust' ) . '</span>';
		}

		printf(
			'<p class="description">%1$s <a href="%2$s">%3$s</a></p>',
			esc_html__( 'Portre, Ayarlardaki kurum logosundan degil bu uzmanın “Uzman fotografi” alanindan gelir.', 'dla-medical-trust' ),
			esc_url( (string) get_edit_post_link( $expert_id ) ),
			esc_html__( 'Uzman kaydini duzenle', 'dla-medical-trust' )
		);

		echo '</td></tr>';
	}

	/**
	 * Baslangic kaynak kutuphanesi.
	 */
	private function render_seed_section(): void {
		$library = new \DLA\MedicalTrust\Seed\StarterLibrary();
		$plan    = $library->plan();
		$pending = $library->pending_count();

		// id: tani ekranindaki "adaylari yayimla" baglantisinin hedefi.
		printf( '<hr><h2 id="dla-mt-seed">%s</h2>', esc_html__( 'Baslangic kaynak kutuphanesi', 'dla-medical-trust' ) );

		printf(
			'<p class="description" style="max-width:70ch">%s</p>',
			esc_html__( 'Kaynak secimi motoru calisiyor, ancak kutuphane bosken secebilecegi hicbir kayit yok. Bu arac, konularinizla eslesen GERCEK ve adresleri dogrulanmis mesleki kurulus (ASPS, ISAPS, NHS) ve klinik kurum (Cleveland Clinic) kaynaklarini olusturur.', 'dla-medical-trust' )
		);

		printf(
			'<p class="description" style="max-width:70ch"><strong>%s</strong> %s</p>',
			esc_html__( 'Hakemli bilimsel yayin uretilmez.', 'dla-medical-trust' ),
			esc_html__( 'Bir konuya gercekten uygun yayin secmek kuratorluk ister; DOI uydurmak sistemin amacini bozar. "Bilimsel Yayin" slotu bos kalir ve kutu onsuz gorunur.', 'dla-medical-trust' )
		);

		$report = get_transient( self::SEED_NOTICE );

		if ( is_array( $report ) ) {
			delete_transient( self::SEED_NOTICE );
			$this->render_seed_report( $report );
		}

		printf(
			'<p><strong>%s</strong></p>',
			esc_html(
				sprintf(
					/* translators: 1: olusturulacak kayit, 2: bekleyen aday. */
					__( 'Olusturulabilecek yeni kayit: %1$d · Yayim bekleyen aday: %2$d', 'dla-medical-trust' ),
					count( $plan ),
					$pending
				)
			)
		);

		if ( ! empty( $plan ) ) {
			echo '<ul style="list-style:disc;margin-left:20px">';
			foreach ( $plan as $entry ) {
				printf(
					'<li>%1$s <em>(%2$s)</em></li>',
					esc_html( (string) $entry['title'] ),
					esc_html( \DLA\MedicalTrust\Domain\Enum\SourceType::label( (string) $entry['type'] ) )
				);
			}
			echo '</ul>';
		} elseif ( 0 === $pending ) {
			printf(
				'<p><em>%s</em></p>',
				esc_html__( 'Eslesen yeni kaynak yok. Konulariniz katalogdaki anahtar kelimelerle eslesmiyorsa once Tibbi Konular olusturun.', 'dla-medical-trust' )
			);
		}

		// Tek tusla bitiren yol ONDE durur. Iki asamali "once olustur, sonra
		// yayimla" akisi, kaynaklarin tamami zaten bizim dogruladigimiz
		// kuratorlu katalogdan geldigi icin gereksiz bir tur yaratiyordu;
		// dugmeye basmak onay eyleminin kendisidir.
		if ( ! empty( $plan ) || $pending > 0 ) {
			printf( '<form method="post" action="%s" style="display:inline-block;margin-right:8px">', esc_url( admin_url( 'admin-post.php' ) ) );
			wp_nonce_field( self::SEED_PUBLISH_ACTION, self::SEED_NONCE );
			printf( '<input type="hidden" name="action" value="%s">', esc_attr( self::SEED_PUBLISH_ACTION ) );
			submit_button( __( 'Katalogu simdi eslestir ve yayimla', 'dla-medical-trust' ), 'primary', 'submit', false );
			echo '</form>';
		}

		if ( ! empty( $plan ) ) {
			printf( '<form method="post" action="%s" style="display:inline-block;margin-right:8px">', esc_url( admin_url( 'admin-post.php' ) ) );
			wp_nonce_field( self::SEED_ACTION, self::SEED_NONCE );
			printf( '<input type="hidden" name="action" value="%s">', esc_attr( self::SEED_ACTION ) );
			submit_button( __( 'Yalnizca aday olarak olustur', 'dla-medical-trust' ), 'secondary', 'submit', false );
			echo '</form>';
		}

		if ( $pending > 0 ) {
			printf( '<form method="post" action="%s" style="display:inline-block">', esc_url( admin_url( 'admin-post.php' ) ) );
			wp_nonce_field( self::PUBLISH_ACTION, self::SEED_NONCE );
			printf( '<input type="hidden" name="action" value="%s">', esc_attr( self::PUBLISH_ACTION ) );
			submit_button(
				sprintf(
					/* translators: %d: bekleyen aday sayisi. */
					__( 'Bekleyen %d adayi yayimla', 'dla-medical-trust' ),
					$pending
				),
				'secondary',
				'submit',
				false
			);
			echo '</form>';

			printf(
				'<p class="description">%s <a href="%s">%s</a></p>',
				esc_html__( 'Adaylar yayimlanana kadar hicbir sayfada gorunmez. Once tek tek gozden gecirmek isterseniz:', 'dla-medical-trust' ),
				esc_url( admin_url( 'edit.php?post_status=pending&post_type=' . \DLA\MedicalTrust\PostTypes\SourcePostType::SLUG ) ),
				esc_html__( 'bekleyen kaynaklari ac', 'dla-medical-trust' )
			);
		}
	}

	/**
	 * @param array<string,mixed> $report
	 */
	private function render_seed_report( array $report ): void {
		echo '<div class="notice notice-success"><p><strong>';
		echo esc_html(
			sprintf(
				/* translators: %d: olusturulan kayit sayisi. */
				__( '%d aday kaynak olusturuldu.', 'dla-medical-trust' ),
				(int) ( $report['created'] ?? 0 )
			)
		);
		echo '</strong></p>';

		if ( ! empty( $report['titles'] ) && is_array( $report['titles'] ) ) {
			echo '<ul style="list-style:disc;margin-left:20px">';
			foreach ( $report['titles'] as $title ) {
				printf( '<li>%s</li>', esc_html( (string) $title ) );
			}
			echo '</ul>';
		}

		$published = (int) ( $report['published'] ?? 0 );

		printf(
			'<p>%s</p></div>',
			$published > 0
				? esc_html(
					sprintf(
						/* translators: %d: yayimlanan kayit sayisi. */
						__( '%d kaynak yayimlandi ve secime hazir. Konusu eslesen sayfalarda kaynak bolumu artik dolu gelir.', 'dla-medical-trust' ),
						$published
					)
				)
				: esc_html__( 'Kayitlar "aday" durumundadir ve yayimlanana kadar hicbir sayfada gorunmez.', 'dla-medical-trust' )
		);
	}

	public function handle_seed(): void {
		if ( ! current_user_can( Capabilities::MANAGE_SOURCES ) ) {
			wp_die( esc_html__( 'Bu islem icin yetkiniz yok.', 'dla-medical-trust' ) );
		}

		check_admin_referer( self::SEED_ACTION, self::SEED_NONCE );

		$report = ( new \DLA\MedicalTrust\Seed\StarterLibrary() )->install();
		set_transient( self::SEED_NOTICE, $report, 60 );

		$this->redirect_back();
	}

	/**
	 * Olustur + yayimla, tek istekte.
	 *
	 * Katalog kuratorludur ve her adresi dogrulanmistir; dugmeye basmak
	 * editorun onayidir. Kaynaklar yine de KAYIT olarak olusur, dolayisiyla
	 * her biri sonradan tek tek duzenlenebilir veya geri cekilebilir.
	 */
	public function handle_seed_and_publish(): void {
		if ( ! current_user_can( Capabilities::MANAGE_SOURCES ) ) {
			wp_die( esc_html__( 'Bu islem icin yetkiniz yok.', 'dla-medical-trust' ) );
		}

		check_admin_referer( self::SEED_PUBLISH_ACTION, self::SEED_NONCE );

		$report = ( new \DLA\MedicalTrust\Seed\StarterLibrary() )->synchronize_and_publish();
		set_transient( self::SEED_NOTICE, $report, 60 );

		$this->redirect_back();
	}

	public function handle_publish_pending(): void {
		if ( ! current_user_can( Capabilities::MANAGE_SOURCES ) ) {
			wp_die( esc_html__( 'Bu islem icin yetkiniz yok.', 'dla-medical-trust' ) );
		}

		check_admin_referer( self::PUBLISH_ACTION, self::SEED_NONCE );

		$published = ( new \DLA\MedicalTrust\Seed\StarterLibrary() )->publish_pending();
		set_transient( self::SEED_NOTICE, [ 'created' => $published, 'titles' => [] ], 60 );

		$this->redirect_back();
	}

	private function redirect_back(): void {
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
				'default_expert_id'         => $_POST['default_expert_id'] ?? 0,
				'editorial_board_page_id'  => $_POST['editorial_board_page_id'] ?? 0,
				'accent_color'              => wp_unslash( $_POST['accent_color'] ?? '' ),
				'show_updated_date'         => isset( $_POST['show_updated_date'] ),
				'show_review_date'          => isset( $_POST['show_review_date'] ),
				'automatic_injection'       => isset( $_POST['automatic_injection'] ),
				'injection_position'        => wp_unslash( $_POST['injection_position'] ?? 'after' ),
				'article_summary_links_enabled' => isset( $_POST['article_summary_links_enabled'] ),
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
