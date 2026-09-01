<?php
/**
 * Konu için schema.org tip ipucu (v0.1 §6).
 *
 * Bu eklenti bu değeri KULLANMAZ ve JSON-LD üretmez. Yalnızca saklar ve
 * M6'daki kontrat üzerinden schema eklentisine sunar.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Domain\Enum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SchemaTypeHint extends EnumBase {

	public const MEDICAL_PROCEDURE = 'MedicalProcedure';
	public const MEDICAL_CONDITION = 'MedicalCondition';
	public const MEDICAL_THERAPY   = 'MedicalTherapy';

	public static function values(): array {
		return [ self::MEDICAL_PROCEDURE, self::MEDICAL_CONDITION, self::MEDICAL_THERAPY ];
	}

	public static function labels(): array {
		return [
			self::MEDICAL_PROCEDURE => __( 'MedicalProcedure — işlem / ameliyat', 'dla-medical-trust' ),
			self::MEDICAL_CONDITION => __( 'MedicalCondition — durum / endikasyon', 'dla-medical-trust' ),
			self::MEDICAL_THERAPY   => __( 'MedicalTherapy — tedavi', 'dla-medical-trust' ),
		];
	}
}
