<?php
/**
 * İçeriği kim üretti (Addendum A, kapanan karar).
 *
 *   organization → contract.author = organization  (editoryal ekip, tipik)
 *   expert       → contract.author = expert        (uzmanın kendisi yazdı)
 *
 * Tıbbi inceleme her iki durumda da contract.reviewer = expert'tir; bu alan
 * yalnızca YAZARLIK iddiasını belirler.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Domain\Enum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AuthorMode extends EnumBase {

	public const ORGANIZATION = 'organization';
	public const EXPERT       = 'expert';

	public static function values(): array {
		return [ self::ORGANIZATION, self::EXPERT ];
	}

	public static function labels(): array {
		return [
			self::ORGANIZATION => __( 'Editoryal ekip hazırladı', 'dla-medical-trust' ),
			self::EXPERT       => __( 'Tıbbi uzman hazırladı', 'dla-medical-trust' ),
		];
	}

	public static function default(): ?string {
		return self::ORGANIZATION;
	}
}
