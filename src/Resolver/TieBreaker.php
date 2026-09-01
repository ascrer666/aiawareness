<?php
/**
 * Rendezvous hashing (Addendum A §4 — DONDURULMUŞ).
 *
 * Modulo yerine rendezvous kullanılmasının sebebi: aday kümesi büyüdüğünde
 * modulo TÜM sayfaların seçimini kaydırır. Rendezvous'ta yalnızca yeni
 * adayın kazandığı sayfalar değişir.
 *
 * Seed:  seed_key | slot | source_uid
 *
 * `library_version` seed'de YOKTUR ve olmamalıdır. O yalnızca cache
 * geçersiz kılma sinyalidir; seçime katılırsa ilgisiz bir kaynak
 * düzenlemesi tüm sitenin atıflarını karıştırır.
 *
 * SAF: WordPress fonksiyonu çağırmaz.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Resolver;

use DLA\MedicalTrust\Domain\Source;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TieBreaker {

	public static function weight( string $seed_key, string $slot, string $source_uid ): int {
		return (int) crc32( $seed_key . '|' . $slot . '|' . $source_uid );
	}

	/**
	 * En yüksek ağırlıklı adayı seçer. Eşitlikte en düşük UID kazanır —
	 * sonuç her koşulda deterministiktir.
	 *
	 * @param Source[] $tier
	 */
	public static function pick( string $seed_key, string $slot, array $tier ): ?Source {
		$best        = null;
		$best_weight = null;

		foreach ( $tier as $source ) {
			$weight = self::weight( $seed_key, $slot, $source->uid );

			if ( null === $best_weight
				|| $weight > $best_weight
				|| ( $weight === $best_weight && $source->uid < $best->uid ) ) {
				$best        = $source;
				$best_weight = $weight;
			}
		}

		return $best;
	}
}
