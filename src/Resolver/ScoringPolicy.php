<?php
/**
 * Skorlama — DONDURULMUŞ formül (Addendum A §4).
 *
 *   score = topic_proximity
 *         + editor_priority        (0–20)
 *         + evidence_weight        (0–12, publication_type tablosu)
 *         + peer_reviewed ? 8 : 0
 *         + authority_bonus        (yalnızca authority slotu, +10)
 *         + recency_bonus          (max(0, 15 - (yıl farkı)))
 *         - staleness_penalty      (-30, politika yaş sınırı aşılmışsa)
 *
 * ────────────────────────────────────────────────────────────────────────
 *  Bu sınıf yalnızca Source domain nesnesini görür. Source'ta
 *  `discovered_via` alanı YOKTUR — keşif kökeninin skorlamaya ulaşması
 *  fiziksel olarak imkânsızdır (Addendum A §3).
 * ────────────────────────────────────────────────────────────────────────
 *
 * SAF: WordPress fonksiyonu çağırmaz.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Resolver;

use DLA\MedicalTrust\Domain\Enum\PublicationType;
use DLA\MedicalTrust\Domain\Enum\SourceType;
use DLA\MedicalTrust\Domain\Source;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ScoringPolicy {

	public const PEER_REVIEWED_BONUS = 8;
	public const AUTHORITY_BONUS     = 10;
	public const RECENCY_MAX         = 15;
	public const STALENESS_PENALTY   = 30;

	/**
	 * @return array{score:int,components:array<string,int>}
	 */
	public static function score( Source $source, int $proximity, string $slot, ResolverConfig $config ): array {
		$components = [
			'topic_proximity' => $proximity,
			'editor_priority' => max( 0, min( 20, $source->priority ) ),
			'evidence'        => PublicationType::evidence_weight( $source->publication_type ),
			'peer_reviewed'   => $source->peer_reviewed ? self::PEER_REVIEWED_BONUS : 0,
			'authority'       => self::authority_bonus( $source, $slot, $config ),
			'recency'         => self::recency_bonus( $source, $config ),
			'staleness'       => -self::staleness_penalty( $source, $config ),
		];

		return [
			'score'      => (int) array_sum( $components ),
			'components' => $components,
		];
	}

	private static function authority_bonus( Source $source, string $slot, ResolverConfig $config ): int {
		if ( SourceType::AUTHORITY !== $slot ) {
			return 0;
		}

		$haystack = strtolower( $source->publisher . ' ' . (string) $source->canonical_url );

		foreach ( $config->authority_publishers as $needle ) {
			$needle = strtolower( trim( (string) $needle ) );

			if ( '' !== $needle && str_contains( $haystack, $needle ) ) {
				return self::AUTHORITY_BONUS;
			}
		}

		return 0;
	}

	private static function recency_bonus( Source $source, ResolverConfig $config ): int {
		if ( $source->pub_year <= 0 ) {
			return 0;
		}

		$age = $config->current_year - $source->pub_year;

		return max( 0, self::RECENCY_MAX - max( 0, $age ) );
	}

	private static function staleness_penalty( Source $source, ResolverConfig $config ): int {
		if ( $source->pub_year <= 0 || $config->max_source_age_years <= 0 ) {
			return 0;
		}

		$age = $config->current_year - $source->pub_year;

		return $age > $config->max_source_age_years ? self::STALENESS_PENALTY : 0;
	}
}
