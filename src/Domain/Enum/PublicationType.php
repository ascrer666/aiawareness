<?php
/**
 * Belge türü ve kanıt düzeyi ağırlığı (Addendum A §... / v0.2 §3).
 *
 * Ağırlıklar M2'de ScoringPolicy tarafından okunacak; M1'de yalnızca
 * veri olarak saklanır ve doğrulanır.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Domain\Enum;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PublicationType extends EnumBase {

	public const SYSTEMATIC_REVIEW   = 'systematic_review';
	public const META_ANALYSIS       = 'meta_analysis';
	public const RCT                 = 'rct';
	public const CLINICAL_GUIDELINE  = 'clinical_guideline';
	public const CONSENSUS_STATEMENT = 'consensus_statement';
	public const COHORT_STUDY        = 'cohort_study';
	public const TEXTBOOK            = 'textbook';
	public const NARRATIVE_REVIEW    = 'narrative_review';
	public const CASE_SERIES         = 'case_series';
	public const INSTITUTIONAL_PAGE  = 'institutional_page';

	public static function values(): array {
		return array_keys( self::evidence_weights() );
	}

	public static function labels(): array {
		return [
			self::SYSTEMATIC_REVIEW   => __( 'Sistematik derleme', 'dla-medical-trust' ),
			self::META_ANALYSIS       => __( 'Meta-analiz', 'dla-medical-trust' ),
			self::RCT                 => __( 'Randomize kontrollü çalışma', 'dla-medical-trust' ),
			self::CLINICAL_GUIDELINE  => __( 'Klinik kılavuz', 'dla-medical-trust' ),
			self::CONSENSUS_STATEMENT => __( 'Uzlaşı bildirisi', 'dla-medical-trust' ),
			self::COHORT_STUDY        => __( 'Kohort / vaka-kontrol çalışması', 'dla-medical-trust' ),
			self::TEXTBOOK            => __( 'Ders kitabı / referans eser', 'dla-medical-trust' ),
			self::NARRATIVE_REVIEW    => __( 'Derleme (sistematik olmayan)', 'dla-medical-trust' ),
			self::CASE_SERIES         => __( 'Vaka serisi / olgu sunumu', 'dla-medical-trust' ),
			self::INSTITUTIONAL_PAGE  => __( 'Kurumsal bilgi sayfası', 'dla-medical-trust' ),
		];
	}

	/**
	 * Kanıt hiyerarşisi ağırlıkları. M2 skorlama girdisi.
	 *
	 * @return array<string,int>
	 */
	public static function evidence_weights(): array {
		return [
			self::SYSTEMATIC_REVIEW   => 12,
			self::META_ANALYSIS       => 12,
			self::RCT                 => 10,
			self::CLINICAL_GUIDELINE  => 10,
			self::CONSENSUS_STATEMENT => 8,
			self::COHORT_STUDY        => 6,
			self::TEXTBOOK            => 6,
			self::NARRATIVE_REVIEW    => 4,
			self::CASE_SERIES         => 2,
			self::INSTITUTIONAL_PAGE  => 2,
		];
	}

	public static function evidence_weight( ?string $value ): int {
		if ( null === $value ) {
			return 0;
		}

		return self::evidence_weights()[ $value ] ?? 0;
	}
}
