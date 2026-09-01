<?php
/**
 * Etkinleştirme / devre dışı bırakma.
 *
 * Kural: devre dışı bırakma HİÇBİR ŞEY SİLMEZ. Küratörlü bir kaynak
 * kütüphanesini kaza ile kaybetmek geri alınamaz (v0.1 §13).
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust;

use DLA\MedicalTrust\Capability\Capabilities;
use DLA\MedicalTrust\PostTypes\ExpertPostType;
use DLA\MedicalTrust\PostTypes\SourcePostType;
use DLA\MedicalTrust\Settings\Settings;
use DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy;
use DLA\MedicalTrust\Taxonomies\SourceTypeTaxonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Activation {

	public static function activate(): void {
		// Term ekleyebilmek için türlerin bu istekte kayıtlı olması gerekir.
		( new ExpertPostType() )->register_post_type();
		( new SourcePostType() )->register_post_type();
		( new MedicalTopicTaxonomy() )->register_taxonomy();
		( new SourceTypeTaxonomy() )->register_taxonomy();

		SourceTypeTaxonomy::ensure_terms();
		Capabilities::add_to_roles();

		// Var olan ayarlar korunur; yalnızca eksikler tamamlanır.
		$existing = get_option( Settings::OPTION, null );
		if ( ! is_array( $existing ) ) {
			add_option( Settings::OPTION, Settings::defaults(), '', true );
		}

		// Kütüphane sürümü YALNIZCA option hiç yoksa 1 olarak başlatılır.
		// Yeniden etkinleştirme mevcut sürümü asla sıfırlamaz veya düşürmez:
		// düşen bir sürüm, tüm sayfalarda bayat çözümleme cache'inin geçerli
		// sayılmasına yol açardı.
		$sentinel = '__dla_mt_missing__';
		$current  = get_option( Settings::OPTION_LIBRARY, $sentinel );

		if ( $sentinel === $current ) {
			add_option( Settings::OPTION_LIBRARY, 1, '', true );
		} elseif ( ! is_numeric( $current ) || (int) $current < 1 ) {
			// Bozuk veya geçersiz değer yalnızca YUKARI doğru onarılır.
			update_option( Settings::OPTION_LIBRARY, 1, true );
		}

		update_option( Settings::OPTION_DB, DB_VERSION, true );

		Settings::flush_cache();
	}

	/**
	 * Veri, yetenekler ve term'ler olduğu gibi bırakılır; yeniden
	 * etkinleştirme kayıpsız olsun diye.
	 */
	public static function deactivate(): void {
		Settings::flush_cache();
	}
}
