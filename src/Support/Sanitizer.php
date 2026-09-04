<?php
/**
 * Girdi temizleme (v0.1 §13).
 *
 * Kural: veritabanına giren her değer burada temizlenir. Çıkışta ayrıca
 * escape edilir — çift katman kasıtlıdır.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Support;

use DateTimeImmutable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Sanitizer {

	/**
	 * Uzman yorumu ve kısa biyografide izin verilen etiketler.
	 * Dar tutulmuştur; başka her şey silinir.
	 */
	public static function allowed_html(): array {
		return [
			'p'      => [],
			'br'     => [],
			'strong' => [],
			'em'     => [],
			'ul'     => [],
			'ol'     => [],
			'li'     => [],
			'a'      => [
				'href'  => true,
				'rel'   => true,
				'title' => true,
			],
		];
	}

	public static function text( $value ): string {
		return sanitize_text_field( (string) $value );
	}

	public static function textarea( $value ): string {
		return sanitize_textarea_field( (string) $value );
	}

	/**
	 * Kısıtlı HTML — giriş katmanı.
	 */
	public static function restricted_html( $value ): string {
		return wp_kses( (string) $value, self::allowed_html() );
	}

	public static function bool( $value ): bool {
		return (bool) $value;
	}

	public static function int_in_range( $value, int $min, int $max, int $default = 0 ): int {
		if ( '' === $value || null === $value ) {
			return $default;
		}

		$int = (int) $value;

		return max( $min, min( $max, $int ) );
	}

	/**
	 * Boş bırakılabilen tam sayı. Boşsa null döner (miras/devral anlamında).
	 */
	public static function nullable_int_in_range( $value, int $min, int $max ): ?int {
		if ( '' === $value || null === $value ) {
			return null;
		}

		return max( $min, min( $max, (int) $value ) );
	}

	/**
	 * Y-m-d tarihi. Geçersiz, gelecek veya makul alt sınırın altındaki
	 * tarihler reddedilir (v0.1 §13, v0.2 §9).
	 */
	public static function date( $value, bool $allow_future = false ): string {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		if ( false === $date || $date->format( 'Y-m-d' ) !== $value ) {
			return '';
		}

		$year = (int) $date->format( 'Y' );
		if ( $year < 1900 ) {
			return '';
		}

		if ( ! $allow_future ) {
			$today = new DateTimeImmutable( current_time( 'Y-m-d' ) );
			if ( $date > $today ) {
				return '';
			}
		}

		return $value;
	}

	/**
	 * Yayın yılı. 1800 ile içinde bulunulan yıl + 1 arası
	 * (baskı öncesi yayınlar için bir yıl tolerans).
	 */
	public static function publication_year( $value ): int {
		if ( '' === $value || null === $value ) {
			return 0;
		}

		$year = (int) $value;
		$max  = (int) current_time( 'Y' ) + 1;

		if ( $year < 1800 || $year > $max ) {
			return 0;
		}

		return $year;
	}

	/**
	 * Post ID referansı — varlık VE post type doğrulanır.
	 * Bir uzman alanına kaynak ID'si yazılamaz.
	 */
	public static function post_id_of_type( $value, string $post_type ): int {
		$id = absint( $value );
		if ( 0 === $id ) {
			return 0;
		}

		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || $post->post_type !== $post_type ) {
			return 0;
		}

		return $id;
	}

	/**
	 * Yayımlanabilir bir içerik sayfası referansı (uzman profil sayfası).
	 */
	public static function content_post_id( $value, array $allowed_types ): int {
		$id = absint( $value );
		if ( 0 === $id ) {
			return 0;
		}

		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, $allowed_types, true ) ) {
			return 0;
		}

		return $id;
	}

	/**
	 * @return string[]
	 */
	public static function string_list( $value ): array {
		if ( is_string( $value ) ) {
			$value = preg_split( '/\r\n|\r|\n/', $value ) ?: [];
		}

		if ( ! is_array( $value ) ) {
			return [];
		}

		$out = [];
		foreach ( $value as $item ) {
			$item = sanitize_text_field( (string) $item );
			if ( '' !== $item ) {
				$out[] = $item;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Post ID listesi.
	 *
	 * Girdi hem dizi (JS destekli secici) hem de virgul/satir ile ayrilmis
	 * metin (JS calismadiginda elle yazilan liste) olabilir. Sifir ve
	 * tekrarlar elenir, sira korunur.
	 *
	 * SAF.
	 *
	 * @return int[]
	 */
	public static function id_list( $value ): array {
		if ( ! is_array( $value ) ) {
			$value = preg_split( '/[^0-9]+/', (string) $value ) ?: [];
		}

		$out = [];

		foreach ( $value as $item ) {
			$id = absint( $item );
			if ( $id > 0 ) {
				$out[] = $id;
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Doğrulanmış URL listesi. Politikayı geçemeyenler sessizce elenir.
	 *
	 * @return string[]
	 */
	public static function url_list( $value ): array {
		$items = is_array( $value ) ? $value : preg_split( '/\r\n|\r|\n/', (string) $value );
		$out   = [];

		foreach ( (array) $items as $item ) {
			$item = trim( (string) $item );
			if ( '' === $item ) {
				continue;
			}

			$result = UrlPolicy::validate( $item );
			if ( $result['valid'] && '' !== $result['url'] ) {
				$out[] = $result['url'];
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Kontrollü liste doğrulaması. Eşleşmezse varsayılana düşer.
	 *
	 * @param class-string<\DLA\MedicalTrust\Domain\Enum\EnumBase> $enum
	 */
	public static function enum( $value, string $enum ): ?string {
		return $enum::coerce( is_string( $value ) ? trim( $value ) : $value );
	}

	/**
	 * Dil kodu — ISO 639-1, isteğe bağlı bölge eki.
	 */
	public static function language_code( $value ): string {
		$value = strtolower( trim( (string) $value ) );

		return preg_match( '#^[a-z]{2}(-[a-z]{2})?$#', $value ) ? $value : '';
	}
}
