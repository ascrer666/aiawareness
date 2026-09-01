<?php
/**
 * Bağlantı politikası (Addendum A §4).
 *
 * Atıf bağlantısı kanonik yayına gider — onu bulduğunuz arama sonucuna değil.
 *
 * Tasarım notu: desen kuralları (`find_forbidden_reason`, `canonical`,
 * kimlik normalleştirme) SAF tutulmuştur; WordPress fonksiyonu çağırmazlar,
 * bu sayede WordPress olmadan unit test edilebilirler (v0.1 §19).
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UrlPolicy {

	public const ALLOWED_SCHEMES = [ 'http', 'https' ];

	/**
	 * Arama/indeks servisleri. Bu host'larda sorgu dizesi varsa bağlantı
	 * bir belgeye değil bir arama sonucuna işaret ediyor demektir.
	 */
	private const INDEX_HOSTS = [
		'pubmed.ncbi.nlm.nih.gov',
		'www.ncbi.nlm.nih.gov',
		'ncbi.nlm.nih.gov',
		'europepmc.org',
		'www.semanticscholar.org',
		'semanticscholar.org',
		'www.researchgate.net',
		'researchgate.net',
	];

	private const SEARCH_PARAMS = [ 'term', 'q', 'query', 'search', 'searchterm', 'as_q' ];

	private const SHORTENER_HOSTS = [
		'bit.ly',
		'tinyurl.com',
		'goo.gl',
		't.co',
		'ow.ly',
		'is.gd',
		'buff.ly',
		'rebrand.ly',
		'cutt.ly',
		'shorturl.at',
		'rb.gy',
		'lnkd.in',
	];

	/**
	 * Yasaklı hedef mi? Değilse null, yasaklıysa gerekçe anahtarı döner.
	 *
	 * SAF: WordPress fonksiyonu çağırmaz.
	 */
	public static function find_forbidden_reason( string $url ): ?string {
		$url = trim( $url );

		if ( '' === $url ) {
			return 'empty';
		}

		// parse_url (WP'nin wp_parse_url'u degil): bu metodun WordPress'siz
		// test edilebilmesi icin cekirdek fonksiyona bagimli kalinmiyor.
		$parts = parse_url( $url );

		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return 'unparseable';
		}

		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		if ( ! in_array( $scheme, self::ALLOWED_SCHEMES, true ) ) {
			return 'scheme_not_allowed';
		}

		$host  = strtolower( $parts['host'] );
		$path  = strtolower( (string) ( $parts['path'] ?? '' ) );
		$query = (string) ( $parts['query'] ?? '' );

		// Google Scholar hiçbir zaman bağlantı hedefi olamaz — makale başına
		// kalıcı kanonik URL'i yoktur.
		if ( preg_match( '#^(www\.)?scholar\.google\.[a-z.]+$#', $host ) ) {
			return 'google_scholar';
		}

		if ( in_array( $host, self::SHORTENER_HOSTS, true ) ) {
			return 'shortener';
		}

		if ( '' !== $query && in_array( $host, self::INDEX_HOSTS, true ) ) {
			parse_str( $query, $args );
			foreach ( self::SEARCH_PARAMS as $param ) {
				if ( isset( $args[ $param ] ) ) {
					return 'search_result';
				}
			}
		}

		// Yalnızca "search" segmenti. "results" kasıtlı olarak dışarıda:
		// üniversite ve klinik çalışma sayfalarında meşru biçimde geçebiliyor.
		if ( preg_match( '#(^|/)search(/|$)#', $path ) ) {
			return 'search_result';
		}

		return null;
	}

	/**
	 * Gerekçe anahtarı => okunabilir mesaj.
	 */
	public static function reason_message( string $reason ): string {
		$messages = [
			'empty'              => __( 'Bağlantı boş.', 'dla-medical-trust' ),
			'unparseable'        => __( 'Bağlantı çözümlenemedi.', 'dla-medical-trust' ),
			'scheme_not_allowed' => __( 'Yalnızca http ve https bağlantıları kabul edilir.', 'dla-medical-trust' ),
			'google_scholar'     => __( 'Google Scholar bağlantı hedefi olamaz; kalıcı kanonik URL sunmaz. Yayının DOI, PMC veya yayıncı adresini kullanın. Scholar yalnızca "keşif kökeni" olarak kaydedilebilir.', 'dla-medical-trust' ),
			'shortener'          => __( 'Kısaltılmış bağlantılar kabul edilmez; hedefi gizler ve sessizce değiştirilebilir.', 'dla-medical-trust' ),
			'search_result'      => __( 'Arama sonucu adresi atıf hedefi olamaz. Kanonik yayın adresini kullanın.', 'dla-medical-trust' ),
			'blocked_host'       => __( 'Bu adrese izin verilmiyor.', 'dla-medical-trust' ),
		];

		return $messages[ $reason ] ?? __( 'Bağlantı reddedildi.', 'dla-medical-trust' );
	}

	/**
	 * Tam doğrulama. WordPress bağımlıdır (SSRF koruması dahil).
	 *
	 * @return array{valid:bool,url:string,reason:?string}
	 */
	public static function validate( string $url ): array {
		$url = trim( $url );

		if ( '' === $url ) {
			return [
				'valid'  => true,
				'url'    => '',
				'reason' => null,
			];
		}

		$reason = self::find_forbidden_reason( $url );
		if ( null !== $reason ) {
			return [
				'valid'  => false,
				'url'    => '',
				'reason' => $reason,
			];
		}

		$clean = esc_url_raw( $url, self::ALLOWED_SCHEMES );
		if ( '' === $clean ) {
			return [
				'valid'  => false,
				'url'    => '',
				'reason' => 'unparseable',
			];
		}

		// Özel/loopback host reddi — Phase 2 sağlık kontrolleri için SSRF koruması.
		if ( false === wp_http_validate_url( $clean ) ) {
			return [
				'valid'  => false,
				'url'    => '',
				'reason' => 'blocked_host',
			];
		}

		return [
			'valid'  => true,
			'url'    => $clean,
			'reason' => null,
		];
	}

	/**
	 * Kanonik atıf hedefi. Öncelik: DOI > PMC > publisher_url > url > PMID.
	 *
	 * SAF: WordPress fonksiyonu çağırmaz.
	 *
	 * @param array<string,string> $meta doi, pmc_id, publisher_url, url, pmid
	 */
	public static function canonical( array $meta ): ?string {
		$doi = self::normalize_doi( (string) ( $meta['doi'] ?? '' ) );
		if ( null !== $doi ) {
			return 'https://doi.org/' . $doi;
		}

		$pmc = self::normalize_pmc_id( (string) ( $meta['pmc_id'] ?? '' ) );
		if ( null !== $pmc ) {
			return 'https://www.ncbi.nlm.nih.gov/pmc/articles/' . $pmc . '/';
		}

		$publisher_url = trim( (string) ( $meta['publisher_url'] ?? '' ) );
		if ( '' !== $publisher_url ) {
			return $publisher_url;
		}

		$url = trim( (string) ( $meta['url'] ?? '' ) );
		if ( '' !== $url ) {
			return $url;
		}

		$pmid = self::normalize_pmid( (string) ( $meta['pmid'] ?? '' ) );
		if ( null !== $pmid ) {
			return 'https://pubmed.ncbi.nlm.nih.gov/' . $pmid . '/';
		}

		return null;
	}

	/**
	 * Kanonik hedefin hangi alandan geldiği — admin açıklaması için.
	 */
	public static function canonical_field( array $meta ): ?string {
		if ( null !== self::normalize_doi( (string) ( $meta['doi'] ?? '' ) ) ) {
			return 'doi';
		}
		if ( null !== self::normalize_pmc_id( (string) ( $meta['pmc_id'] ?? '' ) ) ) {
			return 'pmc_id';
		}
		if ( '' !== trim( (string) ( $meta['publisher_url'] ?? '' ) ) ) {
			return 'publisher_url';
		}
		if ( '' !== trim( (string) ( $meta['url'] ?? '' ) ) ) {
			return 'url';
		}
		if ( null !== self::normalize_pmid( (string) ( $meta['pmid'] ?? '' ) ) ) {
			return 'pmid';
		}

		return null;
	}

	public static function normalize_doi( string $doi ): ?string {
		$doi = trim( $doi );
		if ( '' === $doi ) {
			return null;
		}

		// Tam URL yapıştırılmışsa çıplak DOI'ye indirge.
		$doi = preg_replace( '#^https?://(dx\.)?doi\.org/#i', '', $doi );
		$doi = preg_replace( '#^doi:\s*#i', '', (string) $doi );
		$doi = trim( (string) $doi );

		return preg_match( '#^10\.\d{4,9}/\S+$#', $doi ) ? $doi : null;
	}

	public static function normalize_pmid( string $pmid ): ?string {
		$pmid = trim( $pmid );
		if ( '' === $pmid ) {
			return null;
		}

		$pmid = preg_replace( '#^https?://pubmed\.ncbi\.nlm\.nih\.gov/#i', '', $pmid );
		$pmid = trim( (string) $pmid, "/ \t\n\r" );

		return preg_match( '#^\d{1,9}$#', $pmid ) ? $pmid : null;
	}

	public static function normalize_pmc_id( string $pmc ): ?string {
		$pmc = strtoupper( trim( $pmc ) );
		if ( '' === $pmc ) {
			return null;
		}

		if ( preg_match( '#(PMC\d{1,9})#', $pmc, $m ) ) {
			return $m[1];
		}

		return preg_match( '#^\d{1,9}$#', $pmc ) ? 'PMC' . $pmc : null;
	}
}
