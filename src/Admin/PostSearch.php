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

	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, [ $this, 'handle' ] );
	}

	/**
	 * Profil hedefi olabilecek icerik turleri.
	 *
	 * Eklentinin kendi turleri ve ekler haric, arayuzu olan her tur.
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

	public function handle(): void {
		if ( ! current_user_can( Capabilities::MANAGE_EXPERTS ) ) {
			wp_send_json_error( [ 'message' => __( 'Yetkiniz yok.', 'dla-medical-trust' ) ], 403 );
		}

		check_ajax_referer( self::ACTION, 'nonce' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- check_ajax_referer dogruladi.
		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['term'] ) ) : '';

		if ( mb_strlen( $term ) < 2 ) {
			wp_send_json_success( [] );
		}

		$query = new \WP_Query(
			[
				// Polylang'in o anki yonetici dili filtresi, ayarlarda secilecek
				// ana sayfayi gizlememeli. "lang" WordPress tarafinda zararsiz
				// bir ek sorgu degiskenidir; Polylang etkinken tum dilleri ister.
				'lang'                   => '',
				'post_type'              => self::profile_post_types(),
				'post_status'            => [ 'publish', 'draft', 'private' ],
				's'                      => $term,
				'posts_per_page'         => self::LIMIT,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

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
