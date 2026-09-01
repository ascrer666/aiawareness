<?php
/**
 * Adlandırılmış inceleme politikaları (v0.2 §7).
 *
 * Konu başına serbest sayı girmek yerine adlandırılmış politika; aralık
 * değerleri ayarlardan düzenlenebilir, slug sabittir.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Domain\Enum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReviewPolicy extends EnumBase {

	public const STABLE   = 'stable';
	public const STANDARD = 'standard';
	public const VOLATILE = 'volatile';

	public static function values(): array {
		return [ self::STABLE, self::STANDARD, self::VOLATILE ];
	}

	public static function labels(): array {
		return [
			self::STABLE   => __( 'Stabil — yerleşik cerrahi bilgi', 'dla-medical-trust' ),
			self::STANDARD => __( 'Standart — çoğu tedavi sayfası', 'dla-medical-trust' ),
			self::VOLATILE => __( 'Değişken — cihaz ve teknoloji tabanlı', 'dla-medical-trust' ),
		];
	}

	public static function default(): ?string {
		return self::STANDARD;
	}

	/**
	 * Ayarlar boşsa kullanılacak fabrika değerleri.
	 *
	 * @return array<string,array{interval_months:int,max_source_age_years:int}>
	 */
	public static function factory_defaults(): array {
		return [
			self::STABLE   => [
				'interval_months'      => 36,
				'max_source_age_years' => 15,
			],
			self::STANDARD => [
				'interval_months'      => 24,
				'max_source_age_years' => 10,
			],
			self::VOLATILE => [
				'interval_months'      => 12,
				'max_source_age_years' => 5,
			],
		];
	}
}
