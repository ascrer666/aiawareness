<?php
/**
 * Çözümleyici yapılandırması.
 *
 * Ayarlardan okunur ama çözümleyiciye DEĞER olarak geçirilir; böylece
 * algoritma WordPress'siz test edilebilir.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Resolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ResolverConfig {

	/**
	 * `authority` slotunda tanınan kurumlar. Yayıncı adı veya kanonik
	 * bağlantının host'u eşleşirse +10.
	 */
	public const DEFAULT_AUTHORITY_PUBLISHERS = [
		'isaps',
		'asps',
		'euraps',
		'tprecd',
		'nhs',
		'fda',
		'nice',
		'titck',
		'mayo',
		'who',
		'ema',
		'cochrane',
		'saglik.gov.tr',
	];

	/**
	 * @param string[] $authority_publishers
	 */
	public function __construct(
		public int $min_topic_proximity = 55,
		public int $diversity_band = 10,
		public int $max_tier_size = 6,
		public int $current_year = 2026,
		public int $max_source_age_years = 10,
		public array $authority_publishers = self::DEFAULT_AUTHORITY_PUBLISHERS
	) {}

	public static function from_settings( int $max_source_age_years ): self {
		$settings = \DLA\MedicalTrust\Settings\Settings::all();

		/**
		 * Tanınan otorite kurumları listesi.
		 *
		 * @param string[] $publishers
		 */
		$publishers = apply_filters(
			'dla_mt/v1/authority_publishers',
			self::DEFAULT_AUTHORITY_PUBLISHERS
		);

		return new self(
			(int) $settings['min_topic_proximity'],
			(int) $settings['diversity_band'],
			(int) $settings['max_tier_size'],
			(int) current_time( 'Y' ),
			$max_source_age_years,
			is_array( $publishers ) ? array_map( 'strval', $publishers ) : self::DEFAULT_AUTHORITY_PUBLISHERS
		);
	}

	public function with_max_source_age( int $years ): self {
		$clone                       = clone $this;
		$clone->max_source_age_years = $years;

		return $clone;
	}
}
