<?php
/**
 * "Kutu neden görünmüyor?" tanı ekranı.
 *
 * Trust Box'ın görünmesi bir kontrol zincirine bağlı. Zincirin hangi
 * halkasında durduğunu tahmin etmek yerine burada tek tek gösteriyoruz.
 *
 * Salt okunur: hiçbir ayarı, meta'yı veya cache'i değiştirmez.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Admin;

use DLA\MedicalTrust\Capability\Capabilities;
use DLA\MedicalTrust\Integration\TrustComponent;
use DLA\MedicalTrust\Meta\MetaRegistry;
use DLA\MedicalTrust\PostTypes\ExpertPostType;
use DLA\MedicalTrust\Repository\TrustDataRepository;
use DLA\MedicalTrust\Settings\Settings;
use DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DiagnosticsPanel {

	private const SLUG = 'dla-mt-diagnostics';

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_page' ], 20 );
		add_action( 'admin_notices', [ $this, 'render_setup_notice' ] );
	}

	/**
	 * Eklenti çalışamaz durumdaysa bunu sessizce geçme.
	 *
	 * Yayımlanmış tek bir uzman yoksa Trust Box hiçbir sayfada görünemez —
	 * gösterilecek doğrulanmış bilgi olmadığı için. Kullanıcının bunu
	 * aramasına gerek kalmasın.
	 */
	public function render_setup_notice(): void {
		if ( ! current_user_can( Capabilities::EDIT_META ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen instanceof \WP_Screen ) {
			return;
		}

		// Uzman oluşturma ekranında uyarı göstermek anlamsız.
		if ( ExpertPostType::SLUG === $screen->post_type ) {
			return;
		}

		$relevant = in_array( $screen->post_type, Settings::eligible_post_types(), true )
			|| false !== strpos( (string) $screen->id, 'dla-mt' )
			|| 'dashboard' === $screen->id;

		if ( ! $relevant ) {
			return;
		}

		if ( $this->published_expert_count() > 0 ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s</p><p><a class="button button-primary" href="%3$s">%4$s</a> <a href="%5$s">%6$s</a></p></div>',
			esc_html__( 'DLA Medical Trust:', 'dla-medical-trust' ),
			esc_html__( 'Yayımlanmış hiçbir tıbbi uzman kaydı yok, bu yüzden Trust Box hiçbir sayfada görünemez. Bir uzman oluşturup YAYIMLAYIN (taslak yeterli değildir), sonra Ayarlar’dan "Varsayılan içerik sorumlusu" olarak seçin.', 'dla-medical-trust' ),
			esc_url( admin_url( 'post-new.php?post_type=' . ExpertPostType::SLUG ) ),
			esc_html__( 'Uzman oluştur', 'dla-medical-trust' ),
			esc_url( admin_url( 'edit.php?post_type=' . ExpertPostType::SLUG . '&page=' . self::SLUG ) ),
			esc_html__( 'Tanı ekranını aç', 'dla-medical-trust' )
		);
	}

	public function add_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . ExpertPostType::SLUG,
			__( 'Kutu neden görünmüyor?', 'dla-medical-trust' ),
			__( 'Tanı', 'dla-medical-trust' ),
			Capabilities::EDIT_META,
			self::SLUG,
			[ $this, 'render' ]
		);
	}

	public function render(): void {
		if ( ! current_user_can( Capabilities::EDIT_META ) ) {
			wp_die( esc_html__( 'Bu sayfaya erişim yetkiniz yok.', 'dla-medical-trust' ) );
		}

		echo '<div class="wrap">';
		printf( '<h1>%s</h1>', esc_html__( 'Kutu neden görünmüyor?', 'dla-medical-trust' ) );

		$this->render_global_status();
		$this->render_form();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- salt okunur tanı, veri değiştirmez.
		$post_id = isset( $_GET['dla_post'] ) ? absint( $_GET['dla_post'] ) : 0;

		if ( $post_id > 0 ) {
			$this->render_post_report( $post_id );
		}

		echo '</div>';
	}

	/* ------------------------------------------------------- global */

	private function render_global_status(): void {
		$expert_count = $this->published_expert_count();
		$default_id   = Settings::default_expert_id();
		$eligible     = Settings::eligible_post_types();

		printf( '<h2>%s</h2>', esc_html__( 'Site geneli kurulum', 'dla-medical-trust' ) );
		echo '<table class="widefat striped" style="max-width:900px"><tbody>';

		$this->row(
			__( 'Yayımlanmış uzman sayısı', 'dla-medical-trust' ),
			(string) $expert_count,
			$expert_count > 0,
			__( 'Hiç yayımlanmış uzman yok. Medical Trust → Tıbbi Uzmanlar altında bir uzman oluşturup YAYIMLAYIN (taslak yeterli değildir).', 'dla-medical-trust' )
		);

		$this->row(
			__( 'Varsayılan içerik sorumlusu', 'dla-medical-trust' ),
			$default_id > 0 ? (string) ExpertPostType::display_name( $default_id ) : __( 'seçilmedi', 'dla-medical-trust' ),
			$default_id > 0 && null !== ExpertPostType::display_name( $default_id ),
			__( 'Seçilmemiş. Ayarlar → Varsayılan içerik sorumlusu alanından bir uzman seçerseniz her sayfada tek tek uzman seçmeniz gerekmez.', 'dla-medical-trust' )
		);

		$this->row(
			__( 'Otomatik Trust Box yerleştirme', 'dla-medical-trust' ),
			Settings::automatic_injection_enabled() ? __( 'açık', 'dla-medical-trust' ) : __( 'kapalı', 'dla-medical-trust' ),
			Settings::automatic_injection_enabled(),
			__( 'Kapalı. Kutunun içerik sonunda kendiliğinden çıkması için Ayarlar’dan açın (veya sayfaya [dla_medical_trust] kısa kodunu koyun).', 'dla-medical-trust' )
		);

		$this->row(
			__( 'Kapsamdaki içerik türleri', 'dla-medical-trust' ),
			empty( $eligible ) ? __( 'hiçbiri', 'dla-medical-trust' ) : implode( ', ', $eligible ),
			! empty( $eligible ),
			__( 'Hiçbir içerik türü kapsamda değil. Ayarlar → Kapsamdaki içerik türleri altından en az "Sayfa"yı işaretleyin.', 'dla-medical-trust' )
		);

		$this->render_library_rows();

		$this->row(
			__( 'Yüklü eklenti sürümü', 'dla-medical-trust' ),
			\DLA\MedicalTrust\VERSION,
			true,
			''
		);

		echo '</tbody></table>';
	}

	/**
	 * Kaynak kutuphanesi ozeti.
	 *
	 * "0 aday" ile "5 kaynak var ama hepsi konusuz" arasindaki farki gorunur
	 * kilar; bu ikisi tani panelinde ayni gorunuyordu.
	 */
	private function render_library_rows(): void {
		$all = get_posts(
			[
				'post_type'              => \DLA\MedicalTrust\PostTypes\SourcePostType::SLUG,
				'post_status'            => [ 'publish', 'pending', 'private', 'draft' ],
				'numberposts'            => 500,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			]
		);

		$published  = 0;
		$awaiting   = 0;
		$incomplete = 0;
		$problems   = [];
		$per_slot   = [];

		foreach ( \DLA\MedicalTrust\Domain\Enum\SourceType::values() as $slot ) {
			$per_slot[ $slot ] = 0;
		}

		foreach ( $all as $source_id ) {
			$source_id = (int) $source_id;

			// Veri eksikligi ile "onay bekliyor" AYRI seylerdir: birincisi
			// duzeltilmesi gereken bir hata, ikincisi tasarim geregi bekleyen
			// bir adaydir. Ikisini tek sayida toplamak, kullaniciya olmayan
			// bir hata ariyormus gibi gosteriyordu.
			$missing_data = SourceMetaBox::missing_data_requirements( $source_id );
			$is_published = 'publish' === get_post_status( $source_id );

			if ( ! empty( $missing_data ) ) {
				++$incomplete;
				$problems[] = [
					'id'      => $source_id,
					'reason'  => implode( ' ', $missing_data ),
					'pending' => false,
				];

				continue;
			}

			if ( ! $is_published ) {
				++$awaiting;
				$problems[] = [
					'id'      => $source_id,
					'reason'  => __( 'Aday — yayimlanmayi bekliyor.', 'dla-medical-trust' ),
					'pending' => true,
				];

				continue;
			}

			++$published;
			$terms = get_the_terms( $source_id, \DLA\MedicalTrust\Taxonomies\SourceTypeTaxonomy::SLUG );

			if ( is_array( $terms ) && ! empty( $terms ) && isset( $per_slot[ $terms[0]->slug ] ) ) {
				++$per_slot[ $terms[0]->slug ];
			}
		}

		$this->row(
			__( 'Kütüphanedeki toplam kaynak', 'dla-medical-trust' ),
			(string) count( $all ),
			count( $all ) > 0,
			__( 'Kütüphane boş. Ayarlar → Başlangıç kaynak kütüphanesi bölümünden hazır otorite kaynaklarını tek tıkla oluşturabilirsiniz.', 'dla-medical-trust' )
		);

		$this->row(
			__( 'Kullanılabilir kaynak', 'dla-medical-trust' ),
			(string) $published,
			$published > 0,
			$awaiting > 0
				? __( 'Hiçbir kaynak seçime giremiyor — çünkü hepsi hâlâ ADAY durumunda. Aşağıdaki "Onay bekleyen aday" satırındaki düğmeye basmanız yeterli.', 'dla-medical-trust' )
				: __( 'Hiçbir kaynak seçime giremiyor. Aşağıdaki "Eksik kaynak" satırına bakın.', 'dla-medical-trust' )
		);

		// Onay bekleyen aday bir HATA DEGILDIR: kaynaklar bilincli olarak
		// pending olusturulur ve yayimlama karari editorundur. Bu yuzden
		// kirmizi carpi degil, dogrudan yapilacak isi gosteren bir satir.
		if ( $awaiting > 0 ) {
			printf(
				'<tr><td style="width:240px"><strong>%1$s</strong></td><td style="width:60px"><span style="color:#8a6d1f">&#9679;</span></td><td>%2$s<br><em>%3$s</em> &nbsp; <a class="button button-primary" href="%4$s">%5$s</a></td></tr>',
				esc_html__( 'Onay bekleyen aday', 'dla-medical-trust' ),
				esc_html( (string) $awaiting ),
				esc_html__( 'Bu kayıtların bilgileri tam; yalnızca sizin onayınızı bekliyorlar. Yayımlandıkları anda kutuda kaynak bölümü açılır.', 'dla-medical-trust' ),
				esc_url( admin_url( 'edit.php?post_type=' . ExpertPostType::SLUG . '&page=dla-mt-settings#dla-mt-seed' ) ),
				esc_html__( 'Ayarlar → adayları yayımla', 'dla-medical-trust' )
			);
		}

		$this->row(
			__( 'Eksik kaynak', 'dla-medical-trust' ),
			(string) $incomplete,
			0 === $incomplete,
			__( 'Bu kayıtlarda tür, konu veya adres eksik; düzeltilene kadar hiçbir sayfada görünmezler.', 'dla-medical-trust' )
		);

		$this->render_problem_sources( $problems );

		$slot_summary = [];
		foreach ( $per_slot as $slot => $count ) {
			$slot_summary[] = \DLA\MedicalTrust\Domain\Enum\SourceType::label( $slot ) . ': ' . $count;
		}

		$this->row(
			__( 'Slot bazında kullanılabilir', 'dla-medical-trust' ),
			implode( ' · ', $slot_summary ),
			true,
			''
		);
	}

	/**
	 * Secime giremeyen kayitlari ADIYLA listeler.
	 *
	 * Yalnizca bir sayi gostermek ("3 eksik") kullaniciyi hangi kaydin neden
	 * elendigini aramaya zorluyordu — sessiz elemenin son kalintisi.
	 *
	 * @param array<int,array{id:int,reason:string,pending:bool}> $problems
	 */
	private function render_problem_sources( array $problems ): void {
		if ( empty( $problems ) ) {
			return;
		}

		printf(
			'<tr><td colspan="3"><strong>%s</strong><ul style="list-style:disc;margin:6px 0 0 20px">',
			esc_html__( 'Şu anda seçime girmeyen kayıtlar', 'dla-medical-trust' )
		);

		foreach ( array_slice( $problems, 0, 20 ) as $problem ) {
			printf(
				'<li><a href="%1$s">%2$s</a> — <span style="color:%3$s">%4$s</span></li>',
				esc_url( (string) get_edit_post_link( $problem['id'] ) ),
				esc_html( (string) get_the_title( $problem['id'] ) ),
				$problem['pending'] ? '#8a6d1f' : '#8e3a44',
				esc_html( $problem['reason'] )
			);
		}

		echo '</ul></td></tr>';
	}

	/* --------------------------------------------------------- form */

	private function render_form(): void {
		printf( '<h2>%s</h2>', esc_html__( 'Tek bir sayfayı incele', 'dla-medical-trust' ) );
		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Sayfayı düzenlerken adres çubuğundaki post=12149 sayısını buraya yazın.', 'dla-medical-trust' )
		);

		printf( '<form method="get" action="%s">', esc_url( admin_url( 'edit.php' ) ) );
		printf( '<input type="hidden" name="post_type" value="%s">', esc_attr( ExpertPostType::SLUG ) );
		printf( '<input type="hidden" name="page" value="%s">', esc_attr( self::SLUG ) );
		printf(
			'<input type="number" name="dla_post" value="%s" class="regular-text" placeholder="12149"> ',
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- salt okunur.
			esc_attr( (string) ( isset( $_GET['dla_post'] ) ? absint( $_GET['dla_post'] ) : '' ) )
		);
		submit_button( __( 'İncele', 'dla-medical-trust' ), 'primary', 'submit', false );
		echo '</form>';
	}

	/* ------------------------------------------------------- rapor */

	private function render_post_report( int $post_id ): void {
		$post = get_post( $post_id );

		printf( '<h2>%s</h2>', esc_html__( 'Sayfa raporu', 'dla-medical-trust' ) );

		if ( ! $post instanceof \WP_Post ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'Bu ID ile bir içerik bulunamadı.', 'dla-medical-trust' )
			);

			return;
		}

		$repo     = new TrustDataRepository();
		$eligible = $repo->is_eligible_post( $post_id );

		echo '<table class="widefat striped" style="max-width:900px"><tbody>';

		$this->row( __( 'Başlık', 'dla-medical-trust' ), $post->post_title, true, '' );
		$this->row( __( 'İçerik türü', 'dla-medical-trust' ), $post->post_type, true, '' );

		$this->row(
			__( 'Kapsamda mı', 'dla-medical-trust' ),
			$eligible ? __( 'evet', 'dla-medical-trust' ) : __( 'HAYIR', 'dla-medical-trust' ),
			$eligible,
			__( 'Bu içerik türü Ayarlar’daki kapsam listesinde işaretli değil. Kutu bu yüzden hiç çalışmaz.', 'dla-medical-trust' )
		);

		$terms      = get_the_terms( $post_id, MedicalTopicTaxonomy::SLUG );
		$term_names = is_array( $terms ) ? wp_list_pluck( $terms, 'name' ) : [];

		$this->row(
			__( 'Tıbbi konu', 'dla-medical-trust' ),
			empty( $term_names ) ? __( 'yok', 'dla-medical-trust' ) : implode( ', ', $term_names ),
			true,
			''
		);
		printf(
			'<tr><td colspan="3"><em>%s</em></td></tr>',
			esc_html__( 'Not: konu yalnızca otomatik KAYNAK seçimi için gerekir. Konu olmadan da kutu görünebilir.', 'dla-medical-trust' )
		);

		// Uzman devralma zinciri.
		$page_expert_id = (int) get_post_meta( $post_id, MetaRegistry::PAGE_EXPERT_ID, true );
		$default_id     = Settings::default_expert_id();

		$this->row(
			__( '1) Sayfa uzmanı', 'dla-medical-trust' ),
			$page_expert_id > 0 ? (string) ( ExpertPostType::display_name( $page_expert_id ) ?? __( 'GEÇERSİZ kayıt', 'dla-medical-trust' ) ) : __( 'seçilmedi', 'dla-medical-trust' ),
			true,
			''
		);
		$this->row(
			__( '2) Konu varsayılan uzmanı', 'dla-medical-trust' ),
			$this->topic_expert_label( $terms ),
			true,
			''
		);
		$this->row(
			__( '3) Site geneli varsayılan', 'dla-medical-trust' ),
			$default_id > 0 ? (string) ( ExpertPostType::display_name( $default_id ) ?? __( 'GEÇERSİZ kayıt', 'dla-medical-trust' ) ) : __( 'seçilmedi', 'dla-medical-trust' ),
			true,
			''
		);

		$data = $repo->for_post( $post_id );

		$this->row(
			__( 'Gösterilecek uzman bulundu mu', 'dla-medical-trust' ),
			null !== $data && null !== $data->primary_expert ? (string) $data->primary_expert['name'] : __( 'HAYIR', 'dla-medical-trust' ),
			null !== $data && null !== $data->primary_expert,
			__( 'Zincirin üç halkası da boş. En hızlı çözüm: bir uzman yayımlayıp Ayarlar → Varsayılan içerik sorumlusu alanına atayın.', 'dla-medical-trust' )
		);

		if ( null !== $data && null !== $data->primary_expert ) {
			$expert_id = (int) $data->primary_expert['id'];
			$image_id  = (int) $data->primary_expert['image_id'];

			// Fotograf YANLIS uzman kaydina eklenmis olabilir; hangi kaydin
			// gosterildigi acikca yazilir ve dogrudan duzenleme bagi verilir.
			printf(
				'<tr><td style="width:240px"><strong>%1$s</strong></td><td style="width:60px">&#10004;</td><td>%2$s (ID %3$d) &nbsp; <a href="%4$s">%5$s</a></td></tr>',
				esc_html__( 'Gosterilen uzman kaydi', 'dla-medical-trust' ),
				esc_html( (string) $data->primary_expert['name'] ),
				$expert_id,
				esc_url( (string) get_edit_post_link( $expert_id ) ),
				esc_html__( 'bu kaydi duzenle', 'dla-medical-trust' )
			);

			$this->render_photo_rows( $expert_id, $image_id );
		}

		$html = ( new TrustComponent() )->render_for_post( $post_id );

		$this->row(
			__( 'Kutu çıktısı üretiliyor mu', 'dla-medical-trust' ),
			'' !== $html
				/* translators: %d: karakter sayısı. */
				? sprintf( __( 'EVET (%d karakter)', 'dla-medical-trust' ), strlen( $html ) )
				: __( 'HAYIR — boş', 'dla-medical-trust' ),
			'' !== $html,
			__( 'Gösterilecek doğrulanmış hiçbir bilgi olmadığı için kutu bilinçli olarak boş dönüyor.', 'dla-medical-trust' )
		);

		echo '</tbody></table>';

		if ( '' !== $html ) {
			printf( '<h3>%s</h3>', esc_html__( 'Bu sayfada üretilen kutu', 'dla-medical-trust' ) );
			printf(
				'<div class="notice notice-success"><p>%s</p></div>',
				esc_html__( 'Veri tarafı çalışıyor. Kutu yine de sitede görünmüyorsa sebep yerleştirmedir: Otomatik yerleştirmeyi açın ya da Avada layout’unda [dla_medical_trust] kısa kodunun Post Content alanında olduğundan emin olun.', 'dla-medical-trust' )
			);
			echo '<div style="max-width:900px;border:1px solid #dcdcde;padding:12px;background:#fff">';
			echo wp_kses_post( $html );
			echo '</div>';
		}
	}

	/**
	 * Portre zincirinin HER HALKASI ayri ayri raporlanir.
	 *
	 * "Fotograf: YOK" tek basina neyin bozuldugunu soylemiyordu; ham meta mi
	 * bos, ek mi silinmis, MIME turu mu gorsel degil, yoksa gorsel var da
	 * cekirdek mi markup uretemiyor — hepsi ayri sebep ve ayri cozum.
	 */
	private function render_photo_rows( int $expert_id, int $image_id ): void {
		$raw = get_post_meta( $expert_id, '_thumbnail_id', true );

		$this->row(
			__( 'Kayitli fotograf ID (ham meta)', 'dla-medical-trust' ),
			'' === (string) $raw ? __( 'bos', 'dla-medical-trust' ) : (string) $raw,
			'' !== (string) $raw && 0 !== (int) $raw,
			__( 'Uzman kaydinda _thumbnail_id yazili degil. Kaydi acip "Uzman fotografi" alanindaki "Gorsel sec" dugmesini kullanin ve Guncelle deyin.', 'dla-medical-trust' )
		);

		if ( $image_id < 1 ) {
			return;
		}

		$attachment = get_post( $image_id );

		$this->row(
			__( 'Ek kaydi mevcut mu', 'dla-medical-trust' ),
			$attachment instanceof \WP_Post
				? $attachment->post_type . ' / ' . ( '' !== $attachment->post_mime_type ? $attachment->post_mime_type : '?' )
				: __( 'BULUNAMADI', 'dla-medical-trust' ),
			$attachment instanceof \WP_Post && 'attachment' === $attachment->post_type,
			__( 'Bu ID ile bir ek bulunamadi ya da ek degil. Ortam kutuphanesinden gorseli yeniden secin.', 'dla-medical-trust' )
		);

		$is_image = wp_attachment_is_image( $image_id );

		$this->row(
			__( 'Ek bir gorsel mi', 'dla-medical-trust' ),
			$is_image ? __( 'evet', 'dla-medical-trust' ) : __( 'HAYIR', 'dla-medical-trust' ),
			$is_image,
			__( 'Ekin MIME turu gorsel degil (orn. PDF veya SVG). Portre icin PNG/JPG/WebP kullanin.', 'dla-medical-trust' )
		);

		$url = wp_get_attachment_image_url( $image_id, 'medium' );
		$url = is_string( $url ) && '' !== $url ? $url : (string) wp_get_attachment_url( $image_id );

		$this->row(
			__( 'Gorsel adresi uretiliyor mu', 'dla-medical-trust' ),
			'' !== $url ? $url : __( 'HAYIR — bos', 'dla-medical-trust' ),
			'' !== $url,
			__( 'Ekin dosyasi sunucuda bulunamiyor ya da metadata kaydi eksik. Gorseli ortam kutuphanesine yeniden yukleyin.', 'dla-medical-trust' )
		);

		if ( '' !== $url ) {
			printf(
				'<tr><td style="width:240px"><strong>%1$s</strong></td><td style="width:60px">&#10004;</td>'
				. '<td><img src="%2$s" alt="" style="width:72px;height:72px;object-fit:cover;border-radius:50%%;border:1px solid #dcdcde"></td></tr>',
				esc_html__( 'Portre onizlemesi', 'dla-medical-trust' ),
				esc_url( $url )
			);
		}
	}

	/**
	 * @param \WP_Term[]|false|\WP_Error $terms
	 */
	private function topic_expert_label( $terms ): string {
		if ( ! is_array( $terms ) || empty( $terms ) ) {
			return __( 'konu yok', 'dla-medical-trust' );
		}

		foreach ( $terms as $term ) {
			$expert_id = (int) get_term_meta( $term->term_id, MetaRegistry::TOPIC_DEFAULT_EXPERT, true );

			if ( $expert_id > 0 ) {
				$name = ExpertPostType::display_name( $expert_id );

				if ( null !== $name ) {
					return $name . ' (' . $term->name . ')';
				}
			}
		}

		return __( 'atanmadı', 'dla-medical-trust' );
	}

	private function published_expert_count(): int {
		$experts = get_posts(
			[
				'post_type'              => ExpertPostType::SLUG,
				'post_status'            => 'publish',
				'numberposts'            => 50,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		return count( $experts );
	}

	private function row( string $label, string $value, bool $ok, string $hint ): void {
		printf(
			'<tr><td style="width:240px"><strong>%1$s</strong></td><td style="width:60px">%2$s</td><td>%3$s%4$s</td></tr>',
			esc_html( $label ),
			$ok ? '<span style="color:#2c6b47">&#10004;</span>' : '<span style="color:#8e3a44">&#10006;</span>',
			esc_html( $value ),
			! $ok && '' !== $hint ? '<br><em style="color:#8e3a44">' . esc_html( $hint ) . '</em>' : ''
		);
	}
}
