<?php
/**
 * Ayarlar (v0.1 §6, v0.2 §7, Addendum A §1).
 *
 * Tek option, küçük ve autoload. Diğer her şey autoload edilmez.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Settings;

use DLA\MedicalTrust\Domain\Enum\ReviewPolicy;
use DLA\MedicalTrust\Support\Sanitizer;
use DLA\MedicalTrust\Support\UrlPolicy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {

	public const OPTION          = 'dla_mt_settings';
	public const OPTION_LIBRARY  = 'dla_mt_library_version';
	public const OPTION_DB       = 'dla_mt_db_version';

	/** @var array<string,mixed>|null */
	private static ?array $cache = null;

	/**
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return [
			'organization'              => [
				'name'     => '',
				'url'      => '',
				'logo_id'  => 0,
			],
			'review_policies'           => ReviewPolicy::factory_defaults(),
			'min_topic_proximity'       => 55,
			'diversity_band'            => 10,
			'max_tier_size'             => 6,
			'require_signoff_reference' => true,
			'eligible_post_types'       => [ 'page', 'post' ],
			'default_expert_id'         => 0,
			'editorial_board_page_id'  => 0,
			'accent_color'              => '',
			'show_updated_date'         => true,
			'show_review_date'          => false,
			'automatic_injection'       => false,
			'injection_position'        => 'after',
			'article_summary_links_enabled' => false,
			// Bos liste = kapsamdaki tum turler. Ozet cubugu icin ayri bir tur
			// listesi tutulur; kapsam ayari Trust Box icindir ve daraltilmasi
			// gerekmez.
			'summary_links_post_types'  => [],
			'summary_links_excluded_ids' => [],
			// Ana sayfa bir "makale" degildir; ozetlenecek bir metni yoktur.
			'summary_links_skip_front_page' => true,
			'google_preferred_source_enabled' => false,
			'google_preferred_source_position' => 'above_summary',
			'retain_data_on_uninstall'  => true,
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			$stored     = get_option( self::OPTION, [] );
			$stored     = is_array( $stored ) ? $stored : [];
			self::$cache = self::merge_defaults( $stored );
		}

		return self::$cache;
	}

	/**
	 * @return mixed
	 */
	public static function get( string $key, $fallback = null ) {
		$all = self::all();

		return $all[ $key ] ?? $fallback;
	}

	public static function update( array $values ): void {
		$clean = self::sanitize( $values );
		update_option( self::OPTION, $clean, true );
		self::$cache = $clean;
	}

	public static function flush_cache(): void {
		self::$cache = null;
	}

	/**
	 * Kayıtlı değerleri varsayılanlarla birleştirir; eksik anahtarlar
	 * varsayılandan gelir (ayar eklendiğinde migration gerekmez).
	 */
	private static function merge_defaults( array $stored ): array {
		$defaults = self::defaults();
		$out      = $defaults;

		foreach ( $stored as $key => $value ) {
			if ( ! array_key_exists( $key, $defaults ) ) {
				continue;
			}

			if ( is_array( $defaults[ $key ] ) && is_array( $value ) ) {
				// DIKKAT: yalnizca ANAHTARLI ayar gruplari (organization,
				// review_policies) birlestirilir. Duz listeler birlestirilirse
				// varsayilan ['page','post'] ile kayitli ['post','page'] uc uca
				// eklenip "page, post, post, page" gibi tekrarli bir liste
				// uretiliyordu; listelerde kayitli deger tek dogru kaynaktir.
				$out[ $key ] = self::is_keyed( $defaults[ $key ] )
					? array_merge( $defaults[ $key ], $value )
					: array_values( $value );
				continue;
			}

			$out[ $key ] = $value;
		}

		return $out;
	}

	/**
	 * Anahtarli ayar grubu mu, yoksa duz liste mi?
	 *
	 * SAF.
	 *
	 * @param array<mixed> $value
	 */
	private static function is_keyed( array $value ): bool {
		foreach ( array_keys( $value ) as $key ) {
			if ( ! is_int( $key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $input ): array {
		$current = self::all();
		$out     = $current;

		if ( isset( $input['organization'] ) && is_array( $input['organization'] ) ) {
			$org = $input['organization'];

			$url_result = UrlPolicy::validate( (string) ( $org['url'] ?? '' ) );

			$out['organization'] = [
				'name'    => Sanitizer::text( $org['name'] ?? '' ),
				'url'     => $url_result['valid'] ? $url_result['url'] : '',
				'logo_id' => absint( $org['logo_id'] ?? 0 ),
			];
		}

		if ( isset( $input['review_policies'] ) && is_array( $input['review_policies'] ) ) {
			$policies = ReviewPolicy::factory_defaults();

			foreach ( ReviewPolicy::values() as $slug ) {
				$given = $input['review_policies'][ $slug ] ?? [];

				$policies[ $slug ] = [
					'interval_months'      => Sanitizer::int_in_range(
						$given['interval_months'] ?? null,
						1,
						120,
						$policies[ $slug ]['interval_months']
					),
					'max_source_age_years' => Sanitizer::int_in_range(
						$given['max_source_age_years'] ?? null,
						1,
						50,
						$policies[ $slug ]['max_source_age_years']
					),
				];
			}

			$out['review_policies'] = $policies;
		}

		if ( array_key_exists( 'min_topic_proximity', $input ) ) {
			$out['min_topic_proximity'] = Sanitizer::int_in_range( $input['min_topic_proximity'], 0, 100, 55 );
		}

		if ( array_key_exists( 'diversity_band', $input ) ) {
			$out['diversity_band'] = Sanitizer::int_in_range( $input['diversity_band'], 0, 200, 10 );
		}

		if ( array_key_exists( 'max_tier_size', $input ) ) {
			$out['max_tier_size'] = Sanitizer::int_in_range( $input['max_tier_size'], 1, 50, 6 );
		}

		if ( array_key_exists( 'require_signoff_reference', $input ) ) {
			$out['require_signoff_reference'] = (bool) $input['require_signoff_reference'];
		}

		if ( array_key_exists( 'retain_data_on_uninstall', $input ) ) {
			$out['retain_data_on_uninstall'] = (bool) $input['retain_data_on_uninstall'];
		}

		if ( isset( $input['eligible_post_types'] ) ) {
			$types     = (array) $input['eligible_post_types'];
			$available = self::selectable_post_types();
			$clean     = [];

			foreach ( $types as $type ) {
				$type = sanitize_key( (string) $type );
				if ( isset( $available[ $type ] ) ) {
					$clean[] = $type;
				}
			}

			$out['eligible_post_types'] = array_values( array_unique( $clean ) );
		}

		if ( array_key_exists( 'default_expert_id', $input ) ) {
			$out['default_expert_id'] = Sanitizer::post_id_of_type(
				$input['default_expert_id'],
				\DLA\MedicalTrust\PostTypes\ExpertPostType::SLUG
			);
		}

		if ( array_key_exists( 'editorial_board_page_id', $input ) ) {
			$out['editorial_board_page_id'] = Sanitizer::content_post_id(
				$input['editorial_board_page_id'],
				array_keys( self::selectable_post_types() )
			);
		}

		if ( array_key_exists( 'accent_color', $input ) ) {
			$hex = sanitize_hex_color( trim( (string) $input['accent_color'] ) );
			$out['accent_color'] = is_string( $hex ) ? $hex : '';
		}

		if ( array_key_exists( 'show_updated_date', $input ) ) {
			$out['show_updated_date'] = (bool) $input['show_updated_date'];
		}

		if ( array_key_exists( 'show_review_date', $input ) ) {
			$out['show_review_date'] = (bool) $input['show_review_date'];
		}

		if ( array_key_exists( 'automatic_injection', $input ) ) {
			$out['automatic_injection'] = (bool) $input['automatic_injection'];
		}

		if ( array_key_exists( 'article_summary_links_enabled', $input ) ) {
			$out['article_summary_links_enabled'] = (bool) $input['article_summary_links_enabled'];
		}

		if ( isset( $input['summary_links_post_types'] ) ) {
			$types     = (array) $input['summary_links_post_types'];
			$available = self::selectable_post_types();
			$clean     = [];

			foreach ( $types as $type ) {
				$type = sanitize_key( (string) $type );
				if ( isset( $available[ $type ] ) ) {
					$clean[] = $type;
				}
			}

			$out['summary_links_post_types'] = array_values( array_unique( $clean ) );
		}

		if ( array_key_exists( 'summary_links_excluded_ids', $input ) ) {
			$out['summary_links_excluded_ids'] = Sanitizer::id_list( $input['summary_links_excluded_ids'] );
		}

		if ( array_key_exists( 'summary_links_skip_front_page', $input ) ) {
			$out['summary_links_skip_front_page'] = (bool) $input['summary_links_skip_front_page'];
		}
		if ( array_key_exists( 'google_preferred_source_enabled', $input ) ) {
			$out['google_preferred_source_enabled'] = (bool) $input['google_preferred_source_enabled'];
		}

		if ( array_key_exists( 'google_preferred_source_position', $input ) ) {
			$out['google_preferred_source_position'] = 'below_summary' === $input['google_preferred_source_position']
				? 'below_summary'
				: 'above_summary';
		}

		if ( array_key_exists( 'injection_position', $input ) ) {
			$out['injection_position'] = 'before' === $input['injection_position'] ? 'before' : 'after';
		}

		return $out;
	}

	/**
	 * Kapsama alınabilecek içerik türleri. Eklentinin kendi türleri hariç.
	 *
	 * @return array<string,string>
	 */
	public static function selectable_post_types(): array {
		$out     = [];
		$exclude = [ 'dla_expert', 'dla_source', 'attachment' ];

		// Avada'daki portfolio/hizmet gibi bazi gercek on-yuz turleri yonetim
		// arayuzunu kapatir. Herkese acik VEYA yonetim arayuzu olan turleri
		// dahil et; aksi halde bu kayitlar secici ve kapsamdan kaybolur.
		$types = array_merge(
			get_post_types( [ 'public' => true ], 'objects' ),
			get_post_types( [ 'show_ui' => true ], 'objects' )
		);

		foreach ( $types as $type ) {
			if ( ! $type instanceof \WP_Post_Type ) {
				continue;
			}

			if ( in_array( $type->name, $exclude, true ) ) {
				continue;
			}

			$out[ $type->name ] = $type->labels->singular_name ?? $type->name;
		}

		return $out;
	}

	/**
	 * @return string[]
	 */
	public static function eligible_post_types(): array {
		$types = self::get( 'eligible_post_types', [] );

		if ( ! is_array( $types ) ) {
			return [];
		}

		// array_unique: eski kurulumlarda kayitli deger tekrarli olabilir.
		return array_values( array_unique( array_filter( array_map( 'strval', $types ) ) ) );
	}

	/**
	 * Site geneli varsayilan icerik sorumlusu uzman.
	 *
	 * Sayfada ve konuda uzman yoksa devreye girer. Bu bir YAZARLIK veya
	 * TIBBI INCELEME iddiasi DEGILDIR; yalnizca icerigin tibbi sorumlusunu
	 * bildirir. Inceleme tarihi buradan asla turetilmez.
	 */
	public static function default_expert_id(): int {
		return (int) self::get( 'default_expert_id', 0 );
	}

	/** Globally configured editorial-board page; translated at render time. */
	public static function editorial_board_page_id(): int {
		return (int) self::get( 'editorial_board_page_id', 0 );
	}

	/**
	 * Icerik guncelleme tarihi ("Son guncelleme") gosterilsin mi?
	 *
	 * Kaynak: post_modified. Gercek bir olgudur, insan eylemi iddia etmez.
	 */
	/**
	 * Kutunun vurgu rengi. Bos ise stil sayfasindaki varsayilan gecerlidir.
	 */
	public static function accent_color(): string {
		$hex = (string) self::get( 'accent_color', '' );

		return 1 === preg_match( '/^#[0-9a-fA-F]{6}$/', $hex ) ? $hex : '';
	}

	public static function show_updated_date(): bool {
		return (bool) self::get( 'show_updated_date', true );
	}

	/**
	 * Tibbi inceleme tarihi gosterilsin mi?
	 *
	 * Varsayilan KAPALI. Yalnizca gercekten kaydedilmis bir inceleme varsa
	 * ve bu ayar acikken gorunur. Guncelleme tarihinden tamamen bagimsizdir.
	 */
	public static function show_review_date(): bool {
		return (bool) self::get( 'show_review_date', false );
	}

	public static function automatic_injection_enabled(): bool {
		return (bool) self::get( 'automatic_injection', false );
	}

	/**
	 * Sayfa basligindan sonra gorunen, harici yapay zeka ozet baglantilari.
	 * Varsayilan kapali: ziyaretciyi harici bir hizmete yonlendiren bir arayuz
	 * yalnizca site yoneticisi bilincli olarak etkinlestirdiginde gorunur.
	 */
	public static function article_summary_links_enabled(): bool {
		return (bool) self::get( 'article_summary_links_enabled', false );
	}

	/**
	 * Ozet cubugunun gorunecegi icerik turleri.
	 *
	 * Kapsam ayarinin ALT KUMESIDIR: kapsam disina cikan bir tur burada
	 * secili kalsa bile gecerli degildir. Bos liste "kapsamdaki tum turler"
	 * demektir; boylece ayar hic dokunulmadiginda davranis degismez.
	 *
	 * @return string[]
	 */
	public static function summary_links_post_types(): array {
		$eligible = self::eligible_post_types();
		$chosen   = self::get( 'summary_links_post_types', [] );

		if ( ! is_array( $chosen ) || [] === $chosen ) {
			return $eligible;
		}

		$chosen = array_values( array_intersect( array_map( 'strval', $chosen ), $eligible ) );

		return [] === $chosen ? $eligible : $chosen;
	}

	/**
	 * Ozet cubugundan haric tutulan icerikler.
	 *
	 * @return int[]
	 */
	public static function summary_links_excluded_ids(): array {
		return Sanitizer::id_list( self::get( 'summary_links_excluded_ids', [] ) );
	}

	public static function summary_links_skip_front_page(): bool {
		return (bool) self::get( 'summary_links_skip_front_page', true );
	}
	public static function google_preferred_source_enabled(): bool {
		return (bool) self::get( 'google_preferred_source_enabled', false );
	}

	/** @return 'above_summary'|'below_summary' */
	public static function google_preferred_source_position(): string {
		return 'below_summary' === self::get( 'google_preferred_source_position', 'above_summary' )
			? 'below_summary'
			: 'above_summary';
	}

	public static function injection_position(): string {
		return 'before' === self::get( 'injection_position', 'after' ) ? 'before' : 'after';
	}

	/**
	 * Bir politikanın çözülmüş değerleri.
	 *
	 * @return array{interval_months:int,max_source_age_years:int}
	 */
	public static function policy( ?string $slug ): array {
		$slug     = ReviewPolicy::coerce( $slug ) ?? ReviewPolicy::STANDARD;
		$policies = self::get( 'review_policies', ReviewPolicy::factory_defaults() );

		if ( isset( $policies[ $slug ] ) && is_array( $policies[ $slug ] ) ) {
			return [
				'interval_months'      => (int) ( $policies[ $slug ]['interval_months'] ?? 24 ),
				'max_source_age_years' => (int) ( $policies[ $slug ]['max_source_age_years'] ?? 10 ),
			];
		}

		return ReviewPolicy::factory_defaults()[ ReviewPolicy::STANDARD ];
	}

	/**
	 * Kaynak kütüphanesi sürüm sayacı.
	 *
	 * M2'de cache geçersiz kılma sinyali olarak kullanılacak. SEÇİME GİRMEZ —
	 * rendezvous hash seed'inde yer almaz (Addendum A §4).
	 */
	public static function library_version(): int {
		$version = get_option( self::OPTION_LIBRARY, 0 );

		return is_numeric( $version ) ? (int) $version : 0;
	}

	public static function bump_library_version(): int {
		$next = self::library_version() + 1;
		update_option( self::OPTION_LIBRARY, $next, true );

		return $next;
	}
}
