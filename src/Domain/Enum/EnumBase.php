<?php
/**
 * Kontrollü değer listeleri için ortak taban.
 *
 * PHP 8.0 tabanı hedeflendiği için yerel `enum` kullanılmıyor (PHP 8.1+).
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Domain\Enum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class EnumBase {

	/**
	 * Geçerli değerler.
	 *
	 * @return string[]
	 */
	abstract public static function values(): array;

	/**
	 * Değer => görünen etiket.
	 *
	 * @return array<string,string>
	 */
	abstract public static function labels(): array;

	/**
	 * Değer yoksa veya geçersizse dönülecek değer. null = "boş bırakılabilir".
	 */
	public static function default(): ?string {
		return null;
	}

	public static function is_valid( $value ): bool {
		return is_string( $value ) && in_array( $value, static::values(), true );
	}

	/**
	 * Geçersiz değeri sessizce varsayılana düşürür.
	 */
	public static function coerce( $value ): ?string {
		return static::is_valid( $value ) ? $value : static::default();
	}

	public static function label( string $value ): string {
		$labels = static::labels();

		return $labels[ $value ] ?? $value;
	}
}
