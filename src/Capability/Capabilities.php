<?php
/**
 * Yetenekler ve yetkilendirme (v0.2 §6).
 *
 * KRİTİK KURAL: `dla_review_medical_content` HİÇBİR ROLE toplu verilmez —
 * Administrator'e de. Kullanıcı bazında atanır.
 *
 * Gerekçe: doktor WordPress'e girmediği için v0.1'deki "Editor rolünde
 * inceleme yetkisi yok" koruması düştü. Yerine gelen koruma budur; olmadan
 * içeriği yazan kişi aynı ekranda incelemeyi de onaylar ve sistem tek
 * kullanıcılı bir onay kutusuna dönüşür.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Capability;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Capabilities {

	public const MANAGE_SOURCES = 'dla_manage_sources';
	public const MANAGE_EXPERTS = 'dla_manage_experts';
	public const EDIT_META      = 'dla_edit_medical_meta';
	public const REVIEW         = 'dla_review_medical_content';

	/**
	 * Rollere toplu verilebilecek yetenekler.
	 *
	 * @return string[]
	 */
	public static function role_grantable(): array {
		return [ self::MANAGE_SOURCES, self::MANAGE_EXPERTS, self::EDIT_META ];
	}

	/**
	 * Yalnızca kullanıcı bazında verilebilenler.
	 *
	 * @return string[]
	 */
	public static function user_only(): array {
		return [ self::REVIEW ];
	}

	/**
	 * @return string[]
	 */
	public static function all(): array {
		return array_merge( self::role_grantable(), self::user_only() );
	}

	public static function labels(): array {
		return [
			self::MANAGE_SOURCES => __( 'Kaynak kütüphanesini yönet', 'dla-medical-trust' ),
			self::MANAGE_EXPERTS => __( 'Uzman kayıtlarını yönet', 'dla-medical-trust' ),
			self::EDIT_META      => __( 'Sayfa tıbbi bilgilerini düzenle', 'dla-medical-trust' ),
			self::REVIEW         => __( 'Tıbbi inceleme kaydı oluştur', 'dla-medical-trust' ),
		];
	}

	/**
	 * Etkinleştirmede çalışır. Yalnızca role verilebilir yetenekler dağıtılır.
	 */
	/**
	 * Meta yetenekleri tek yönetim yeteneğine çevirir.
	 *
	 * `map_for()` üç meta yeteneğe benzersiz ad verdiği için, WordPress'in
	 * onları çözebilmesi bu filtreye bağlıdır.
	 */
	public static function register_meta_cap_filter(): void {
		add_filter( 'map_meta_cap', [ self::class, 'map_meta_cap' ], 10, 2 );
	}

	/**
	 * @param string[] $caps
	 * @return string[]
	 */
	public static function map_meta_cap( $caps, $cap ): array {
		$map = [
			'edit_dla_expert'   => self::MANAGE_EXPERTS,
			'read_dla_expert'   => self::MANAGE_EXPERTS,
			'delete_dla_expert' => self::MANAGE_EXPERTS,
			'edit_dla_source'   => self::MANAGE_SOURCES,
			'read_dla_source'   => self::MANAGE_SOURCES,
			'delete_dla_source' => self::MANAGE_SOURCES,
		];

		if ( isset( $map[ $cap ] ) ) {
			return [ $map[ $cap ] ];
		}

		return is_array( $caps ) ? $caps : [ (string) $caps ];
	}

	public static function add_to_roles(): void {
		$admin = get_role( 'administrator' );
		if ( $admin instanceof \WP_Role ) {
			foreach ( self::role_grantable() as $cap ) {
				$admin->add_cap( $cap );
			}
		}

		$editor = get_role( 'editor' );
		if ( $editor instanceof \WP_Role ) {
			// Editör sayfa tıbbi bilgilerini düzenleyebilir ama inceleme
			// KAYDEDEMEZ. Ayrım kasıtlıdır.
			$editor->add_cap( self::EDIT_META );
		}
	}

	/**
	 * Kaldırma sırasında çalışır.
	 */
	public static function remove_from_roles(): void {
		foreach ( wp_roles()->role_objects as $role ) {
			foreach ( self::all() as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}

	/**
	 * Bir kullanıcıya inceleme yetkisi ver / geri al.
	 */
	public static function set_review_capability( int $user_id, bool $granted ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return false;
		}

		if ( $granted ) {
			$user->add_cap( self::REVIEW, true );
		} else {
			$user->remove_cap( self::REVIEW );
		}

		return true;
	}

	/**
	 * Yeteneğin kullanıcıya DOĞRUDAN verilip verilmediği.
	 * Rolden miras alınan bir değer bu kontrolü geçmez.
	 */
	public static function has_direct_review_capability( int $user_id ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return false;
		}

		return ! empty( $user->caps[ self::REVIEW ] );
	}

	/**
	 * İnceleme yetkisi olan kullanıcılar. Denetim ve admin ekranı için.
	 *
	 * @return \WP_User[]
	 */
	public static function reviewers(): array {
		$users = get_users(
			[
				'fields'  => 'all',
				'orderby' => 'display_name',
				'number'  => 200,
			]
		);

		return array_values(
			array_filter(
				$users,
				static fn( \WP_User $user ): bool => ! empty( $user->caps[ self::REVIEW ] )
			)
		);
	}

	/**
	 * CPT kayıtlarında kullanılacak yetenek eşlemesi.
	 *
	 * Tek bir özel yetenek, o içerik türünün tüm işlemlerini kapatır.
	 *
	 * @return array<string,string>
	 */
	/**
	 * CPT yetenek eşlemesi.
	 *
	 * ────────────────────────────────────────────────────────────────────
	 *  DİKKAT — buradaki en kolay hata:
	 *
	 *  `edit_post` / `read_post` / `delete_post` META yeteneklerdir. Üçünü de
	 *  primitive `dla_manage_experts` yeteneğine eşlerseniz, `map_meta_cap`
	 *  açıkken WordPress `$post_type_meta_caps['dla_manage_experts']` kaydını
	 *  oluşturur ve bu primitive yeteneği kalıcı olarak "meta yetenek" sayar.
	 *
	 *  Sonuç: `current_user_can( 'dla_manage_experts' )` nesne ID'si olmadan
	 *  çağrıldığında `do_not_allow` döner. WordPress CPT menüsünü tam olarak
	 *  bu çağrıyla gösterdiği için MENÜ HİÇBİR KURULUMDA GÖRÜNMEZ.
	 *
	 *  Bu yüzden meta yetenekler AYRI adlar alır ve `map_meta_cap` filtresiyle
	 *  (bkz. register_meta_cap_filter) tek yönetim yeteneğine çevrilir.
	 * ────────────────────────────────────────────────────────────────────
	 *
	 * @param string $singular Meta yetenek adlarının kökü, örn. `dla_expert`.
	 * @return array<string,string>
	 */
	public static function map_for( string $cap, string $singular ): array {
		return [
			// Meta yetenekler — benzersiz adlar; filtre çevirir.
			'edit_post'              => 'edit_' . $singular,
			'read_post'              => 'read_' . $singular,
			'delete_post'            => 'delete_' . $singular,

			// Primitive yetenekler — hepsi tek yönetim yeteneğine bağlı.
			'edit_posts'             => $cap,
			'edit_others_posts'      => $cap,
			'publish_posts'          => $cap,
			'read_private_posts'     => $cap,
			'delete_posts'           => $cap,
			'delete_private_posts'   => $cap,
			'delete_published_posts' => $cap,
			'delete_others_posts'    => $cap,
			'edit_private_posts'     => $cap,
			'edit_published_posts'   => $cap,
			'create_posts'           => $cap,
		];
	}
}
