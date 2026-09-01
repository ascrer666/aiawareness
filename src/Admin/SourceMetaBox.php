<?php
/**
 * Kaynak düzenleme alanları + bağlantı politikası geri bildirimi.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Admin;

use DLA\MedicalTrust\Capability\Capabilities;
use DLA\MedicalTrust\Domain\Enum\DiscoveredVia;
use DLA\MedicalTrust\Domain\Enum\PublicationType;
use DLA\MedicalTrust\Meta\MetaRegistry;
use DLA\MedicalTrust\PostTypes\SourcePostType;
use DLA\MedicalTrust\Support\UrlPolicy;
use DLA\MedicalTrust\Taxonomies\SourceTypeTaxonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SourceMetaBox {

	private const NONCE_ACTION = 'dla_mt_save_source';
	private const NONCE_NAME   = 'dla_mt_source_nonce';
	private const NOTICE_KEY   = '_dla_mt_url_notices';

	public function register(): void {
		add_action( 'add_meta_boxes_' . SourcePostType::SLUG, [ $this, 'add_meta_box' ] );
		add_action( 'save_post_' . SourcePostType::SLUG, [ $this, 'save' ], 10, 2 );
		add_action( 'admin_notices', [ $this, 'render_notices' ] );
	}

	public function add_meta_box(): void {
		add_meta_box(
			'dla-mt-source',
			__( 'Kaynak Bilgileri', 'dla-medical-trust' ),
			[ $this, 'render' ],
			SourcePostType::SLUG,
			'normal',
			'high'
		);
	}

	public function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$get = static fn( string $key ): string => (string) get_post_meta( $post->ID, $key, true );

		$meta = [
			'doi'           => $get( MetaRegistry::SOURCE_DOI ),
			'pmc_id'        => $get( MetaRegistry::SOURCE_PMC_ID ),
			'publisher_url' => $get( MetaRegistry::SOURCE_PUBLISHER_URL ),
			'url'           => $get( MetaRegistry::SOURCE_URL ),
			'pmid'          => $get( MetaRegistry::SOURCE_PMID ),
		];

		echo '<table class="form-table" role="presentation"><tbody>';

		Field::section( __( 'Kimlikler ve bağlantılar', 'dla-medical-trust' ) );

		Field::readonly_link_row(
			__( 'Kanonik atıf hedefi', 'dla-medical-trust' ),
			UrlPolicy::canonical( $meta ),
			$this->canonical_hint( $meta )
		);

		Field::text(
			'dla_mt_doi',
			__( 'DOI', 'dla-medical-trust' ),
			$meta['doi'],
			__( 'Örn. 10.1097/PRS.0000000000009999 — tam URL yapıştırılırsa çıplak DOI\'ye indirgenir. Bağlantı önceliğinde 1. sıra.', 'dla-medical-trust' )
		);

		Field::text(
			'dla_mt_pmc_id',
			__( 'PMC ID', 'dla-medical-trust' ),
			$meta['pmc_id'],
			__( 'Serbest tam metin kaydı. Örn. PMC1234567. Bağlantı önceliğinde 2. sıra.', 'dla-medical-trust' )
		);

		Field::url(
			'dla_mt_publisher_url',
			__( 'Yayıncı / dergi adresi', 'dla-medical-trust' ),
			$meta['publisher_url'],
			__( 'Makalenin yayıncı sitesindeki sayfası. Bağlantı önceliğinde 3. sıra.', 'dla-medical-trust' )
		);

		Field::url(
			'dla_mt_url',
			__( 'Kurum adresi', 'dla-medical-trust' ),
			$meta['url'],
			__( 'Üniversite, mesleki kuruluş veya kamu otoritesi sayfası. Akademik ve otorite kaynakları için normal yol. Bağlantı önceliğinde 4. sıra.', 'dla-medical-trust' )
		);

		Field::text(
			'dla_mt_pmid',
			__( 'PMID', 'dla-medical-trust' ),
			$meta['pmid'],
			__( 'Saklanır ama bağlantı için son çaredir. Phase 2 PubMed eşleştirmesinde anahtar olarak kullanılacak.', 'dla-medical-trust' )
		);

		Field::section( __( 'Nitelik', 'dla-medical-trust' ) );

		Field::select(
			'dla_mt_publication_type',
			__( 'Belge türü', 'dla-medical-trust' ),
			$get( MetaRegistry::SOURCE_PUBLICATION_TYPE ),
			PublicationType::labels(),
			__( 'Kanıt düzeyi skorlamada ağırlık taşır.', 'dla-medical-trust' ),
			__( '— belirtilmedi —', 'dla-medical-trust' )
		);

		Field::checkbox(
			'dla_mt_peer_reviewed',
			__( 'Hakem denetimi', 'dla-medical-trust' ),
			(bool) get_post_meta( $post->ID, MetaRegistry::SOURCE_PEER_REVIEWED, true ),
			__( 'Bağımsız uzmanlar denetledi', 'dla-medical-trust' ),
			__( 'Kaynağın kategorisi değil, niteliğidir. Bir otorite kılavuzu da hakemli olabilir.', 'dla-medical-trust' )
		);

		Field::number(
			'dla_mt_priority',
			__( 'Editör önceliği', 'dla-medical-trust' ),
			(int) get_post_meta( $post->ID, MetaRegistry::SOURCE_PRIORITY, true ),
			0,
			20,
			__( 'Varsayılan 0 bırakın. Yükseltmek skoru artırır ve kaynağı öne çıkarır; ancak durum, slot uyumu ve minimum konu yakınlığı kısıtlarını hiçbir değerde atlayamaz.', 'dla-medical-trust' )
		);

		Field::section( __( 'Künye', 'dla-medical-trust' ) );

		Field::text( 'dla_mt_publisher', __( 'Yayıncı / kurum', 'dla-medical-trust' ), $get( MetaRegistry::SOURCE_PUBLISHER ) );
		Field::text( 'dla_mt_journal', __( 'Dergi', 'dla-medical-trust' ), $get( MetaRegistry::SOURCE_JOURNAL ) );
		Field::text( 'dla_mt_authors', __( 'Yazarlar', 'dla-medical-trust' ), $get( MetaRegistry::SOURCE_AUTHORS ) );

		Field::number(
			'dla_mt_pub_year',
			__( 'Yayın yılı', 'dla-medical-trust' ),
			(int) get_post_meta( $post->ID, MetaRegistry::SOURCE_PUB_YEAR, true ),
			1800,
			(int) current_time( 'Y' ) + 1
		);

		Field::text(
			'dla_mt_lang',
			__( 'Kaynağın dili', 'dla-medical-trust' ),
			$get( MetaRegistry::SOURCE_LANG ),
			__( 'ISO 639-1, örn. "en" veya "tr". Sitenin dili değil, kaynağın kendi dili.', 'dla-medical-trust' )
		);

		Field::section( __( 'İdari', 'dla-medical-trust' ) );

		Field::select(
			'dla_mt_discovered_via',
			__( 'Keşif kökeni', 'dla-medical-trust' ),
			$get( MetaRegistry::SOURCE_DISCOVERED_VIA ),
			DiscoveredVia::labels(),
			__( 'Kaynağı nerede bulduğunuz. Yalnızca idari kayıttır: sıralamayı ETKİLEMEZ ve ön yüzde gösterilmez. PubMed ve Scholar birer indekstir, kaynak değil.', 'dla-medical-trust' ),
			__( '— belirtilmedi —', 'dla-medical-trust' )
		);

		Field::text(
			'dla_mt_discovered_at',
			__( 'Kütüphaneye giriş tarihi', 'dla-medical-trust' ),
			$get( MetaRegistry::SOURCE_DISCOVERED_AT ),
			__( 'YYYY-AA-GG. Gelecek tarihler reddedilir.', 'dla-medical-trust' )
		);

		Field::text(
			'dla_mt_verified_at',
			__( 'Son doğrulama tarihi', 'dla-medical-trust' ),
			$get( MetaRegistry::SOURCE_VERIFIED_AT ),
			__( 'YYYY-AA-GG. Bağlantının elle kontrol edildiği tarih.', 'dla-medical-trust' )
		);

		Field::textarea(
			'dla_mt_relevance_note',
			__( 'İlgi notu', 'dla-medical-trust' ),
			$get( MetaRegistry::SOURCE_RELEVANCE_NOTE ),
			__( 'Bu kaynağın hangi konuya neden uygun olduğu. Yalnızca editör içindir.', 'dla-medical-trust' ),
			3
		);

		Field::readonly_row(
			__( 'Kaynak kimliği', 'dla-medical-trust' ),
			'' !== $get( MetaRegistry::SOURCE_UID ) ? $get( MetaRegistry::SOURCE_UID ) : __( 'İlk kayıtta üretilecek', 'dla-medical-trust' ),
			__( 'Kaynak seçiminin kararlılık anahtarı. Post ID değil bu kullanılır ki içerik taşımaları seçimleri kaydırmasın.', 'dla-medical-trust' )
		);

		echo '</tbody></table>';
	}

	private function canonical_hint( array $meta ): string {
		$field = UrlPolicy::canonical_field( $meta );

		if ( null === $field ) {
			return __( 'Hiçbir kimlik veya adres girilmedi. Bu haliyle kaynak eksik sayılır ve M2\'de seçime giremez.', 'dla-medical-trust' );
		}

		$labels = [
			'doi'           => __( 'DOI', 'dla-medical-trust' ),
			'pmc_id'        => __( 'PMC ID', 'dla-medical-trust' ),
			'publisher_url' => __( 'yayıncı adresi', 'dla-medical-trust' ),
			'url'           => __( 'kurum adresi', 'dla-medical-trust' ),
			'pmid'          => __( 'PMID', 'dla-medical-trust' ),
		];

		/* translators: %s: alan adı. */
		return sprintf( __( 'Bağlantı politikası uyarınca %s alanından üretildi.', 'dla-medical-trust' ), $labels[ $field ] ?? $field );
	}

	public function save( int $post_id, \WP_Post $post ): void {
		unset( $post );

		if ( ! Field::can_save( self::NONCE_NAME, self::NONCE_ACTION, Capabilities::MANAGE_SOURCES ) ) {
			return;
		}

		SourceTypeTaxonomy::save( $post_id );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Field::can_save doğruladı.
		$url_fields = [
			MetaRegistry::SOURCE_URL           => 'dla_mt_url',
			MetaRegistry::SOURCE_PUBLISHER_URL => 'dla_mt_publisher_url',
		];

		$notices = [];

		foreach ( $url_fields as $meta_key => $field ) {
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}

			$raw = trim( (string) wp_unslash( $_POST[ $field ] ) );

			if ( '' !== $raw ) {
				$result = UrlPolicy::validate( $raw );
				if ( ! $result['valid'] ) {
					// Reddedilen adres kaydedilmez ve kullanıcıya sebebi söylenir.
					$notices[] = UrlPolicy::reason_message( (string) $result['reason'] );
					continue;
				}
			}

			update_post_meta( $post_id, $meta_key, $raw );
		}

		$simple = [
			MetaRegistry::SOURCE_DOI              => 'dla_mt_doi',
			MetaRegistry::SOURCE_PMID             => 'dla_mt_pmid',
			MetaRegistry::SOURCE_PMC_ID           => 'dla_mt_pmc_id',
			MetaRegistry::SOURCE_PUBLISHER        => 'dla_mt_publisher',
			MetaRegistry::SOURCE_JOURNAL          => 'dla_mt_journal',
			MetaRegistry::SOURCE_AUTHORS          => 'dla_mt_authors',
			MetaRegistry::SOURCE_PUB_YEAR         => 'dla_mt_pub_year',
			MetaRegistry::SOURCE_LANG             => 'dla_mt_lang',
			MetaRegistry::SOURCE_PRIORITY         => 'dla_mt_priority',
			MetaRegistry::SOURCE_PUBLICATION_TYPE => 'dla_mt_publication_type',
			MetaRegistry::SOURCE_DISCOVERED_VIA   => 'dla_mt_discovered_via',
			MetaRegistry::SOURCE_DISCOVERED_AT    => 'dla_mt_discovered_at',
			MetaRegistry::SOURCE_VERIFIED_AT      => 'dla_mt_verified_at',
			MetaRegistry::SOURCE_RELEVANCE_NOTE   => 'dla_mt_relevance_note',
		];

		foreach ( $simple as $meta_key => $field ) {
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}

			update_post_meta( $post_id, $meta_key, wp_unslash( $_POST[ $field ] ) );
		}

		update_post_meta( $post_id, MetaRegistry::SOURCE_PEER_REVIEWED, isset( $_POST['dla_mt_peer_reviewed'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( '' === (string) get_post_meta( $post_id, MetaRegistry::SOURCE_HEALTH, true ) ) {
			update_post_meta( $post_id, MetaRegistry::SOURCE_HEALTH, \DLA\MedicalTrust\Domain\Enum\SourceHealth::UNKNOWN );
		}

		if ( ! empty( $notices ) ) {
			update_post_meta( $post_id, self::NOTICE_KEY, $notices );
		} else {
			delete_post_meta( $post_id, self::NOTICE_KEY );
		}
	}

	/**
	 * Reddedilen adresler için düzenleme ekranında uyarı.
	 */
	public function render_notices(): void {
		$screen = get_current_screen();
		if ( ! $screen instanceof \WP_Screen || SourcePostType::SLUG !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}

		$post_id = absint( $_GET['post'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 0 === $post_id ) {
			return;
		}

		$notices = get_post_meta( $post_id, self::NOTICE_KEY, true );
		if ( ! is_array( $notices ) || empty( $notices ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p><strong>';
		esc_html_e( 'Bazı adresler bağlantı politikasını geçemedi ve kaydedilmedi:', 'dla-medical-trust' );
		echo '</strong></p><ul style="list-style:disc;margin-left:20px">';

		foreach ( $notices as $notice ) {
			printf( '<li>%s</li>', esc_html( (string) $notice ) );
		}

		echo '</ul></div>';

		delete_post_meta( $post_id, self::NOTICE_KEY );
	}
}
