<?php
/**
 * Çeviri grubu kimlik uzlaşması (M2.0).
 *
 * Bir çeviri grubundaki tüm nesneler TEK bir kimlik taşımalıdır. Hangisinin
 * kazanacağı deterministik olmak zorunda: aksi halde onarım rutini her
 * çalıştığında farklı sonuç üretir ve kaynak seçimi sürekli kayar.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\I18n;

use DLA\MedicalTrust\Identity\UidGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GroupIdentity {

	/**
	 * Grubun kanonik kimliğini seçer.
	 *
	 * SAF: WordPress fonksiyonu çağırmaz, test edilebilir.
	 *
	 * Öncelik:
	 *   1. Varsayılan dildeki üyenin geçerli kimliği
	 *   2. Geçerli kimliği olan en düşük ID'li üye
	 *   3. null — çağıran yeni kimlik üretir
	 *
	 * @param array<int,array{id:int,lang:string,uid:string}> $members
	 */
	public static function choose_canonical( array $members, string $default_lang ): ?string {
		$valid = [];

		foreach ( $members as $member ) {
			$uid = (string) ( $member['uid'] ?? '' );
			if ( '' === $uid || ! UidGenerator::is_valid_format( $uid ) ) {
				continue;
			}

			$valid[] = [
				'id'   => (int) ( $member['id'] ?? 0 ),
				'lang' => (string) ( $member['lang'] ?? '' ),
				'uid'  => $uid,
			];
		}

		if ( empty( $valid ) ) {
			return null;
		}

		foreach ( $valid as $member ) {
			if ( $member['lang'] === $default_lang ) {
				return $member['uid'];
			}
		}

		usort(
			$valid,
			static fn( array $a, array $b ): int => $a['id'] <=> $b['id']
		);

		return $valid[0]['uid'];
	}

	/**
	 * Grup içinde kimlik ayrışması var mı?
	 *
	 * SAF.
	 *
	 * @param array<int,array{id:int,lang:string,uid:string}> $members
	 */
	public static function has_divergence( array $members, string $canonical ): bool {
		foreach ( $members as $member ) {
			if ( (string) ( $member['uid'] ?? '' ) !== $canonical ) {
				return true;
			}
		}

		return false;
	}
}
