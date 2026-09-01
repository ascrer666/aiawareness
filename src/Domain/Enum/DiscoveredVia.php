<?php
/**
 * İdari keşif kökeni (Addendum A §3).
 *
 * KRİTİK: Bu değer sıralamayı etkilemez. Alan, ScoringPolicy'ye geçirilecek
 * veri nesnesine hiçbir zaman eklenmemelidir — kural belgeyle değil,
 * DTO'nun şekliyle korunur.
 *
 * Depolama: kontrollü post meta enum'u. Taxonomy DEĞİLDİR; ters yönlü
 * sorgulanmadığı için term ilişkisi gerekmiyor.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Domain\Enum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DiscoveredVia extends EnumBase {

	public const PUBMED               = 'pubmed';
	public const PMC                  = 'pmc';
	public const GOOGLE_SCHOLAR       = 'google_scholar';
	public const DOI                  = 'doi';
	public const PUBLISHER            = 'publisher';
	public const PROFESSIONAL_SOCIETY = 'professional_society';
	public const UNIVERSITY           = 'university';
	public const GOVERNMENT           = 'government';
	public const MANUAL               = 'manual';

	public static function values(): array {
		return [
			self::PUBMED,
			self::PMC,
			self::GOOGLE_SCHOLAR,
			self::DOI,
			self::PUBLISHER,
			self::PROFESSIONAL_SOCIETY,
			self::UNIVERSITY,
			self::GOVERNMENT,
			self::MANUAL,
		];
	}

	public static function labels(): array {
		return [
			self::PUBMED               => __( 'PubMed indeksi', 'dla-medical-trust' ),
			self::PMC                  => __( 'PubMed Central', 'dla-medical-trust' ),
			self::GOOGLE_SCHOLAR       => __( 'Google Scholar', 'dla-medical-trust' ),
			self::DOI                  => __( 'DOI çözümlemesi', 'dla-medical-trust' ),
			self::PUBLISHER            => __( 'Yayıncı / dergi sitesi', 'dla-medical-trust' ),
			self::PROFESSIONAL_SOCIETY => __( 'Mesleki kuruluş', 'dla-medical-trust' ),
			self::UNIVERSITY           => __( 'Üniversite / akademik kurum', 'dla-medical-trust' ),
			self::GOVERNMENT           => __( 'Kamu sağlık otoritesi', 'dla-medical-trust' ),
			self::MANUAL               => __( 'Elle eklendi', 'dla-medical-trust' ),
		];
	}

	public static function default(): ?string {
		return null;
	}
}
