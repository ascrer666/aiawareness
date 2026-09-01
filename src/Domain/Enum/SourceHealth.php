<?php
/**
 * Kaynak bağlantı sağlığı.
 *
 * M1'de yalnızca alan olarak vardır ve daima `unknown` kalır — sağlık
 * kontrolü Phase 2 işidir. Alanın şimdi kaydedilme sebebi, Addendum A §2'deki
 * uygunluk kuralının (`health != broken`) M2'de migration gerektirmemesi.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Domain\Enum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SourceHealth extends EnumBase {

	public const UNKNOWN  = 'unknown';
	public const OK       = 'ok';
	public const REDIRECT = 'redirect';
	public const BROKEN   = 'broken';

	public static function values(): array {
		return [ self::UNKNOWN, self::OK, self::REDIRECT, self::BROKEN ];
	}

	public static function labels(): array {
		return [
			self::UNKNOWN  => __( 'Kontrol edilmedi', 'dla-medical-trust' ),
			self::OK       => __( 'Erişilebilir', 'dla-medical-trust' ),
			self::REDIRECT => __( 'Yönlendiriliyor', 'dla-medical-trust' ),
			self::BROKEN   => __( 'Bozuk', 'dla-medical-trust' ),
		];
	}

	public static function default(): ?string {
		return self::UNKNOWN;
	}
}
