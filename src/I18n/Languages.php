<?php
/**
 * Adaptör seçimi. Çalışma anında tespit edilir; hiçbiri zorunlu değil.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\I18n;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Languages {

	private static ?LanguageAdapter $adapter = null;

	public static function adapter(): LanguageAdapter {
		if ( null === self::$adapter ) {
			self::$adapter = PolylangAdapter::is_available()
				? new PolylangAdapter()
				: new NullAdapter();

			/**
			 * WPML veya başka bir uygulama bu filtreyle takılabilir.
			 * MVP'de yalnızca Polylang uygulanmıştır (v0.2 C8).
			 *
			 * @param LanguageAdapter $adapter
			 */
			$filtered = apply_filters( 'dla_mt/v1/language_adapter', self::$adapter );

			if ( $filtered instanceof LanguageAdapter ) {
				self::$adapter = $filtered;
			}
		}

		return self::$adapter;
	}

	/**
	 * Test ve onarım rutinleri için sıfırlama.
	 */
	public static function reset(): void {
		self::$adapter = null;
	}
}
