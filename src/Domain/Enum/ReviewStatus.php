<?php
/**
 * İnceleme durumu (v0.2 §5).
 *
 * DİKKAT: `freshness` (current/due) BURADA SAKLANMAZ — render anında
 * review_date + konu politikası aralığından türetilir. Cron'a bağımlı
 * olmamasının sebebi budur (v0.1 §12 senaryo 21).
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Domain\Enum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReviewStatus extends EnumBase {

	public const NONE     = 'none';
	public const REVIEWED = 'reviewed';

	public static function values(): array {
		return [ self::NONE, self::REVIEWED ];
	}

	public static function labels(): array {
		return [
			self::NONE     => __( 'İncelenmedi', 'dla-medical-trust' ),
			self::REVIEWED => __( 'Tıbbi olarak incelendi', 'dla-medical-trust' ),
		];
	}

	public static function default(): ?string {
		return self::NONE;
	}
}
