<?php
/**
 * Kaynağın arkasında kim duruyor? (Addendum A §3)
 *
 * Taxonomy `dla_source_type` term slug'ları bu değerlerle birebir aynıdır.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Domain\Enum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SourceType extends EnumBase {

	public const ACADEMIC    = 'academic';
	public const AUTHORITY   = 'authority';
	public const PUBLICATION = 'publication';

	public static function values(): array {
		return [ self::ACADEMIC, self::AUTHORITY, self::PUBLICATION ];
	}

	public static function labels(): array {
		return [
			self::ACADEMIC    => __( 'Akademik / Bilimsel Kaynak', 'dla-medical-trust' ),
			self::AUTHORITY   => __( 'Tıbbi Otorite / Mesleki Kuruluş', 'dla-medical-trust' ),
			self::PUBLICATION => __( 'Bilimsel Yayın', 'dla-medical-trust' ),
		];
	}

	/**
	 * Editörün yanlış sınıflandırmasını önlemek için kaynak düzenleme
	 * ekranında görünen açıklamalar (v0.1 §7).
	 *
	 * @return array<string,string>
	 */
	public static function descriptions(): array {
		return [
			self::ACADEMIC    => __( 'Üniversite ve akademik kurum yayınları, ders kitabı, referans eser, tıp fakültesi materyali.', 'dla-medical-trust' ),
			self::AUTHORITY   => __( 'Mesleki kuruluş veya sağlık otoritesi: ASPS, ISAPS, EURAPS, TPRECD, NHS, FDA, NICE, TİTCK.', 'dla-medical-trust' ),
			self::PUBLICATION => __( 'Hakemli dergi makalesi, sistematik derleme, meta-analiz, klinik çalışma.', 'dla-medical-trust' ),
		];
	}
}
