<?php
/**
 * Kanonik profil sayfasi icin icerik aramasi.
 *
 * Onceki secici sabit bir <select> idi ve iki yerde birden kortu:
 *  - Liste yalnizca AYARLARDAKI KAPSAM turlerinden (page, post) doluyordu;
 *    Avada portfolio, hizmet gibi ozel turler hic gorunmuyordu.
 *  - 300 kayit sinirindan sonrasi erisilemiyordu ve arama yoktu.
 *
 * Burada tur kisiti kapsam ayarindan BAGIMSIZDIR: profil sayfasi hangi
 * turde olursa olsun secilebilmeli. Kapsam ayari kutunun HANGI SAYFALARDA
 * gorunecegini belirler; profil hedefi bambaska bir sorudur.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Admin;

use DLA\MedicalTrust\Capability\Capabilities;
use DLA\MedicalTrust\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PostSearch {

	public const ACTION = 'dla_mt_post_search';
	public const NONCE  = 'dla_mt_post_search_nonce';

	private const LIMIT = 20;

	// Gozatma modunda amac daraltmak degil, tek ekranda toplu isaretlemektir;
	// bu nedenle sinir belirgin sekilde yuksek tutulur.
	private const BROWSE_LIMIT = 300;

	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, [ $this, 'handle' ] );
	}

	/**
	 * Profil hedefi olabilecek icerik turleri.
	 *
	 * Eklentinin kendi turleri ve ekler haric, herkese acik veya yonetim
	 * arayuzu olan her tur. Buna Avada portfolio/hizmet turleri de dahildir.
	 *
	 * @return string[]
	 */
	public static function profile_post_types(): array {
		return array_keys( Settings::selectable_post_types() );
	}

	/**
	 * Secili kaydin ekranda gosterilecek etiketi.
	 */
	public static function label_for( int $post_id ): string {
		if ( $post_id < 1 ) {
			return '';
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return '';
		}

		return self::compose_label( $post );
	}

	private static function compose_label( \WP_Post $post ): string {
		$type  = get_post_type_object( $post->post_type );
		$label = $type instanceof \WP_Post_Type ? (string) $type->labels->singular_name : $post->post_type;
		$title = '' !== trim( $post->post_title ) ? $post->post_title : __( '(baslıksız)', 'dla-medical-trust' );

		$suffix = 'publish' === $post->post_status
			? ''
			: ' — ' . __( 'YAYIMLANMAMIS', 'dla-medical-trust' );

		return $title . ' · ' . $label . ' (#' . $post->ID . ')' . $suffix;
	}

	/**
	 * Istekte bildirilen turleri gecerli kumeye indirger.
	 *
	 * Cagiran taraf turleri daraltabilir (ozet cubugu haric tutma listesi
	 * yalnizca sayfa/yazi gosterir) ama genisletemez: sonuc her zaman
	 * profile_post_types() icindedir.
	 *
	 * @return string[]
	 */
	public static function requested_post_types( string $raw ): array {
		$allowed = self::profile_post_types();

		if ( '' === trim( $raw ) ) {
			return $allowed;
		}

		$requested = array_filter( array_map( 'sanitize_key', explode( ',', $raw ) ) );
		$filtered  = array_values( array_intersect( $requested, $allowed ) );

		return [] === $filtered ? $allowed : $filtered;
	}
	public function handle(): void {
		if ( ! current_user_can( Capabilities::MANAGE_EXPERTS ) ) {
			wp_send_json_error( [ 'message' => __( 'Yetkiniz yok.', 'dla-medical-trust' ) ], 403 );
		}

		check_ajax_referer( self::ACTION, 'nonce' );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- check_ajax_referer dogruladi.
		$term   = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['term'] ) ) : '';
		$browse = isset( $_GET['browse'] ) && '1' === $_GET['browse'];
		$types  = self::requested_post_types( isset( $_GET['types'] ) ? wp_unslash( (string) $_GET['types'] ) : '' );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Gozatmada terim zorunlu degildir: kullanici hicbir sey yazmadan da
		// listeyi acip toplu isaretleyebilmeli.
		if ( ! $browse && mb_strlen( $term ) < 2 ) {
			wp_send_json_success( [] );
		}

		$args = [
			// Polylang'in o anki yonetici dili filtresi, ayarlarda secilecek
			// ana sayfayi gizlememeli. "lang" WordPress tarafinda zararsiz
			// bir ek sorgu degiskenidir; Polylang etkinken tum dilleri ister.
			'lang'                   => '',
			'post_type'              => $types,
			'post_status'            => [ 'publish', 'draft', 'private' ],
			'posts_per_page'         => $browse ? self::BROWSE_LIMIT : self::LIMIT,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		];

		if ( '' !== $term ) {
			$args['s'] = $term;
			// Genel metin aramasi, header/footer icinde uzman adi gecen yuzlerce
			// alakasiz sayfayi getiriyordu. Secilecek hedef adi ile bulunur;
			// bu nedenle yalnizca baslikta ara.
			$args['search_columns'] = [ 'post_title' ];
		}

		$query = new \WP_Query( $args );

		$results = [];

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$results[] = [
				'id'        => $post->ID,
				'label'     => self::compose_label( $post ),
				'published' => 'publish' === $post->post_status,
			];
		}

		wp_send_json_success( $results );
	}
}
