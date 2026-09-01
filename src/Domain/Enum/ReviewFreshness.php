<?php
/**
 * İnceleme tazeliği; kalıcı durum değildir, her zaman tarihten türetilir.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Domain\Enum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReviewFreshness extends EnumBase {

	public const CURRENT = 'current';
	public const DUE     = 'due';

	public static function values(): array {
		return [ self::CURRENT, self::DUE ];
	}

	public static function labels(): array {
		return [ self::CURRENT => __( 'Güncel', 'dla-medical-trust' ), self::DUE => __( 'Yeniden inceleme zamanı geldi', 'dla-medical-trust' ) ];
	}
}
