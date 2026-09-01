<?php
/**
 * İçerik geçerliliği — inceleme tazeliğinden BAĞIMSIZ eksen (v0.2 §5).
 *
 * "time expired != content invalidated": süre dolması tarihi gizlemez,
 * içeriğin esaslı değişmesi gizler.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Domain\Enum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReviewValidity extends EnumBase {

	public const VALID      = 'valid';
	public const SUPERSEDED = 'superseded';

	public static function values(): array {
		return [ self::VALID, self::SUPERSEDED ];
	}

	public static function labels(): array {
		return [
			self::VALID      => __( 'Geçerli', 'dla-medical-trust' ),
			self::SUPERSEDED => __( 'İçerik inceleme sonrası değişti', 'dla-medical-trust' ),
		];
	}

	public static function default(): ?string {
		return self::VALID;
	}
}
