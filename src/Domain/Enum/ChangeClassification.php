<?php
/**
 * M5'in zorunlu, ön-seçimsiz değişiklik beyanı için kapalı liste.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Domain\Enum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ChangeClassification extends EnumBase {

	public const MINOR_EDIT              = 'minor_edit';
	public const MEDICAL_CONTENT_UPDATE  = 'medical_content_update';

	public static function values(): array {
		return [ self::MINOR_EDIT, self::MEDICAL_CONTENT_UPDATE ];
	}

	public static function labels(): array {
		return [
			self::MINOR_EDIT             => __( 'Küçük düzeltme', 'dla-medical-trust' ),
			self::MEDICAL_CONTENT_UPDATE => __( 'Tıbbi veya içerik güncellemesi', 'dla-medical-trust' ),
		];
	}
}
