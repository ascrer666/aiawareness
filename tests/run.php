<?php
/**
 * M1 birim testleri — WordPress'siz çalışır.
 *
 *   php tests/run.php
 *
 * Kapsam: saf mantık (bağlantı politikası, kimlik biçimi, sanitize kuralları,
 * kontrollü listeler, politika çözümlemesi). WordPress'e bağlı yollar burada
 * DOĞRULANMAZ — onlar WP test suite gerektirir ve M2'ye aittir.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

require_once __DIR__ . '/bootstrap.php';

use DLA\MedicalTrust\Domain\Enum\DiscoveredVia;
use DLA\MedicalTrust\Domain\Enum\PublicationType;
use DLA\MedicalTrust\Domain\Enum\ReviewPolicy;
use DLA\MedicalTrust\Domain\Enum\SourceHealth;
use DLA\MedicalTrust\Domain\Enum\SourceType;
use DLA\MedicalTrust\Identity\UidGenerator;
use DLA\MedicalTrust\Meta\MetaRegistry;
use DLA\MedicalTrust\Settings\Settings;
use DLA\MedicalTrust\Support\Sanitizer;
use DLA\MedicalTrust\Support\UrlPolicy;

final class T {
	public static int $pass = 0;
	public static int $fail = 0;
	/** @var string[] */
	public static array $failures = [];
	private static string $group = '';

	public static function group( string $name ): void {
		self::$group = $name;
		echo PHP_EOL . '── ' . $name . PHP_EOL;
	}

	public static function is( string $what, $actual, $expected ): void {
		if ( $actual === $expected ) {
			self::$pass++;
			echo '  ok   ' . $what . PHP_EOL;

			return;
		}

		self::$fail++;
		$msg = sprintf(
			'%s / %s — beklenen: %s, gelen: %s',
			self::$group,
			$what,
			var_export( $expected, true ),
			var_export( $actual, true )
		);
		self::$failures[] = $msg;
		echo '  FAIL ' . $what . '  → beklenen ' . var_export( $expected, true )
			. ', gelen ' . var_export( $actual, true ) . PHP_EOL;
	}

	public static function true( string $what, bool $actual ): void {
		self::is( $what, $actual, true );
	}
}

/* ================================================================== */
T::group( 'Kimlik biçimi (UidGenerator)' );

T::is( 'format öneki birleştirir', UidGenerator::format( 'src', 'a1b2c3d4e5f6' ), 'src_a1b2c3d4e5f6' );
T::true( 'geçerli kaynak kimliği kabul edilir', UidGenerator::is_valid_format( 'src_a1b2c3d4e5f6' ) );
T::true( 'geçerli konu kimliği kabul edilir', UidGenerator::is_valid_format( 'top_000000000000' ) );
T::true( 'geçerli uzman kimliği kabul edilir', UidGenerator::is_valid_format( 'exp_ffffffffffff' ) );
T::is( 'bilinmeyen önek reddedilir', UidGenerator::is_valid_format( 'xxx_a1b2c3d4e5f6' ), false );
T::is( 'kısa kimlik reddedilir', UidGenerator::is_valid_format( 'src_a1b2c3' ), false );
T::is( 'büyük harf reddedilir', UidGenerator::is_valid_format( 'src_A1B2C3D4E5F6' ), false );

/* ================================================================== */
T::group( 'Kimlik temizleme (MetaRegistry)' );

T::is( 'geçerli kimlik korunur', MetaRegistry::sanitize_uid( 'top_0123456789ab' ), 'top_0123456789ab' );
T::is( 'büyük harf küçültülür', MetaRegistry::sanitize_uid( 'TOP_0123456789AB' ), 'top_0123456789ab' );
T::is( 'geçersiz kimlik boşaltılır', MetaRegistry::sanitize_uid( 'kotu-deger' ), '' );
T::is(
	'kimlik listesi süzülür',
	MetaRegistry::sanitize_uid_list( "top_0123456789ab\nbozuk\ntop_ba9876543210" ),
	[ 'top_0123456789ab', 'top_ba9876543210' ]
);
T::is(
	'kimlik listesinde tekrar elenir',
	MetaRegistry::sanitize_uid_list( "top_0123456789ab, top_0123456789ab" ),
	[ 'top_0123456789ab' ]
);
T::is( 'hash yalnızca 64 hex kabul eder', MetaRegistry::sanitize_hash( str_repeat( 'a', 64 ) ), str_repeat( 'a', 64 ) );
T::is( 'kısa hash reddedilir', MetaRegistry::sanitize_hash( 'abc' ), '' );

/* ================================================================== */
T::group( 'DOI / PMID / PMC normalleştirme' );

T::is( 'çıplak DOI', UrlPolicy::normalize_doi( '10.1097/PRS.0000000000009999' ), '10.1097/PRS.0000000000009999' );
T::is( 'doi.org URL indirgenir', UrlPolicy::normalize_doi( 'https://doi.org/10.1097/PRS.123' ), '10.1097/PRS.123' );
T::is( 'dx.doi.org URL indirgenir', UrlPolicy::normalize_doi( 'http://dx.doi.org/10.1097/PRS.123' ), '10.1097/PRS.123' );
T::is( 'doi: öneki temizlenir', UrlPolicy::normalize_doi( 'doi: 10.1097/PRS.123' ), '10.1097/PRS.123' );
T::is( 'geçersiz DOI null', UrlPolicy::normalize_doi( 'PRS.123' ), null );
T::is( 'boş DOI null', UrlPolicy::normalize_doi( '' ), null );

T::is( 'çıplak PMID', UrlPolicy::normalize_pmid( '34567890' ), '34567890' );
T::is( 'PubMed URL indirgenir', UrlPolicy::normalize_pmid( 'https://pubmed.ncbi.nlm.nih.gov/34567890/' ), '34567890' );
T::is( 'harf içeren PMID null', UrlPolicy::normalize_pmid( 'abc123' ), null );

T::is( 'PMC öneki korunur', UrlPolicy::normalize_pmc_id( 'PMC1234567' ), 'PMC1234567' );
T::is( 'çıplak sayıya PMC eklenir', UrlPolicy::normalize_pmc_id( '1234567' ), 'PMC1234567' );
T::is( 'PMC URL içinden çıkarılır', UrlPolicy::normalize_pmc_id( 'https://www.ncbi.nlm.nih.gov/pmc/articles/PMC7654321/' ), 'PMC7654321' );
T::is( 'geçersiz PMC null', UrlPolicy::normalize_pmc_id( 'abc' ), null );

/* ================================================================== */
T::group( 'Bağlantı politikası — kanonik öncelik (Addendum A §4)' );

$full = [
	'doi'           => '10.1097/PRS.123',
	'pmc_id'        => 'PMC1234567',
	'publisher_url' => 'https://journals.lww.com/article/123',
	'url'           => 'https://www.isaps.org/rhinoplasty/',
	'pmid'          => '34567890',
];

T::is( '1. sıra DOI', UrlPolicy::canonical( $full ), 'https://doi.org/10.1097/PRS.123' );
T::is( 'kanonik alan doi', UrlPolicy::canonical_field( $full ), 'doi' );

$no_doi = $full;
unset( $no_doi['doi'] );
T::is( '2. sıra PMC', UrlPolicy::canonical( $no_doi ), 'https://www.ncbi.nlm.nih.gov/pmc/articles/PMC1234567/' );

$no_pmc = $no_doi;
unset( $no_pmc['pmc_id'] );
T::is( '3. sıra yayıncı adresi', UrlPolicy::canonical( $no_pmc ), 'https://journals.lww.com/article/123' );

$no_pub = $no_pmc;
unset( $no_pub['publisher_url'] );
T::is( '4. sıra kurum adresi', UrlPolicy::canonical( $no_pub ), 'https://www.isaps.org/rhinoplasty/' );

$only_pmid = [ 'pmid' => '34567890' ];
T::is( '5. sıra PMID son çare', UrlPolicy::canonical( $only_pmid ), 'https://pubmed.ncbi.nlm.nih.gov/34567890/' );
T::is( 'hiçbiri yoksa null', UrlPolicy::canonical( [] ), null );
T::is( 'boş alanlar yok sayılır', UrlPolicy::canonical( [ 'doi' => '', 'url' => '' ] ), null );

/* ================================================================== */
T::group( 'Bağlantı politikası — yasak hedefler' );

T::is( 'PubMed arama sonucu', UrlPolicy::find_forbidden_reason( 'https://pubmed.ncbi.nlm.nih.gov/?term=rhinoplasty' ), 'search_result' );
T::is( 'Google Scholar arama', UrlPolicy::find_forbidden_reason( 'https://scholar.google.com/scholar?q=rhinoplasty' ), 'google_scholar' );
T::is( 'Scholar alan adı (tr)', UrlPolicy::find_forbidden_reason( 'https://scholar.google.com.tr/citations?user=x' ), 'google_scholar' );
T::is( 'kısaltılmış bağlantı', UrlPolicy::find_forbidden_reason( 'https://bit.ly/abc123' ), 'shortener' );
T::is( 'ftp şeması', UrlPolicy::find_forbidden_reason( 'ftp://example.org/file' ), 'scheme_not_allowed' );
T::is( 'javascript şeması', UrlPolicy::find_forbidden_reason( 'javascript:alert(1)' ), 'unparseable' );
T::is( '/search/ yolu', UrlPolicy::find_forbidden_reason( 'https://example.org/search/rhinoplasty' ), 'search_result' );
T::is( 'boş bağlantı', UrlPolicy::find_forbidden_reason( '' ), 'empty' );

T::is( 'DOI adresi geçerli', UrlPolicy::find_forbidden_reason( 'https://doi.org/10.1097/PRS.123' ), null );
T::is( 'PMC makalesi geçerli', UrlPolicy::find_forbidden_reason( 'https://www.ncbi.nlm.nih.gov/pmc/articles/PMC1234567/' ), null );
T::is( 'PubMed kayıt sayfası geçerli', UrlPolicy::find_forbidden_reason( 'https://pubmed.ncbi.nlm.nih.gov/34567890/' ), null );
T::is( 'ISAPS sayfası geçerli', UrlPolicy::find_forbidden_reason( 'https://www.isaps.org/procedures/rhinoplasty/' ), null );
T::is(
	'"results" yolu meşru sayılır',
	UrlPolicy::find_forbidden_reason( 'https://www.nhs.uk/conditions/rhinoplasty/results/' ),
	null
);

/* ================================================================== */
T::group( 'Kontrollü listeler' );

T::is( 'kaynak türü üç değer', SourceType::values(), [ 'academic', 'authority', 'publication' ] );
T::true( 'academic geçerli', SourceType::is_valid( 'academic' ) );
T::is( 'peer_reviewed artık tür değil', SourceType::is_valid( 'peer_reviewed' ), false );
T::is( 'her türün açıklaması var', count( SourceType::descriptions() ), 3 );

T::is( 'keşif kökeni dokuz değer', count( DiscoveredVia::values() ), 9 );
T::true( 'google_scholar köken olarak geçerli', DiscoveredVia::is_valid( 'google_scholar' ) );
T::is( 'geçersiz köken null olur', DiscoveredVia::coerce( 'bing' ), null );

$types   = PublicationType::values();
$weights = PublicationType::evidence_weights();
T::is( 'her belge türünün ağırlığı var', count( array_diff( $types, array_keys( $weights ) ) ), 0 );
T::is( 'sistematik derleme en yüksek', PublicationType::evidence_weight( 'systematic_review' ), 12 );
T::is( 'vaka serisi en düşük', PublicationType::evidence_weight( 'case_series' ), 2 );
T::is( 'bilinmeyen tür sıfır', PublicationType::evidence_weight( 'yok' ), 0 );
T::is( 'null tür sıfır', PublicationType::evidence_weight( null ), 0 );

T::is( 'sağlık varsayılanı unknown', SourceHealth::default(), 'unknown' );
T::is( 'politika varsayılanı standard', ReviewPolicy::default(), 'standard' );

/* ================================================================== */
T::group( 'İnceleme politikaları' );

$defaults = ReviewPolicy::factory_defaults();
T::is( 'stable 36 ay', $defaults['stable']['interval_months'], 36 );
T::is( 'standard 24 ay', $defaults['standard']['interval_months'], 24 );
T::is( 'volatile 12 ay', $defaults['volatile']['interval_months'], 12 );
T::true(
	'stabil aralık değişkenden uzun',
	$defaults['stable']['interval_months'] > $defaults['volatile']['interval_months']
);
T::is( 'çözümleme varsayılana düşer', Settings::policy( 'bilinmeyen' )['interval_months'], 24 );
T::is( 'null politika standard', Settings::policy( null )['interval_months'], 24 );
T::is( 'volatile çözümlenir', Settings::policy( 'volatile' )['max_source_age_years'], 5 );

/* ================================================================== */
T::group( 'Sanitizer sınırları' );

T::is( 'öncelik üst sınırda kırpılır', Sanitizer::int_in_range( 999, 0, 20, 0 ), 20 );
T::is( 'öncelik alt sınırda kırpılır', Sanitizer::int_in_range( -5, 0, 20, 0 ), 0 );
T::is( 'boş öncelik varsayılan', Sanitizer::int_in_range( '', 0, 20, 0 ), 0 );
T::is( 'boş bant null (devral)', Sanitizer::nullable_int_in_range( '', 0, 200 ), null );
T::is( 'bant kırpılır', Sanitizer::nullable_int_in_range( 500, 0, 200 ), 200 );

T::is( 'dil kodu iki harf', Sanitizer::language_code( 'EN' ), 'en' );
T::is( 'bölgeli dil kodu', Sanitizer::language_code( 'pt-br' ), 'pt-br' );
T::is( 'geçersiz dil kodu boş', Sanitizer::language_code( 'english' ), '' );

T::is( 'geçerli geçmiş tarih', Sanitizer::date( '2025-03-14' ), '2025-03-14' );
T::is( 'gelecek tarih reddedilir', Sanitizer::date( '2099-01-01' ), '' );
T::is( 'hatalı biçim reddedilir', Sanitizer::date( '14/03/2025' ), '' );
T::is( 'olmayan gün reddedilir', Sanitizer::date( '2025-02-30' ), '' );
T::is( 'gelecek izinliyse kabul', Sanitizer::date( '2099-01-01', true ), '2099-01-01' );

T::is( 'yayın yılı korunur', Sanitizer::publication_year( 2019 ), 2019 );
T::is( 'çok eski yıl reddedilir', Sanitizer::publication_year( 1500 ), 0 );
T::is( 'çok ileri yıl reddedilir', Sanitizer::publication_year( 2999 ), 0 );

T::is( 'dizi olmayan değer boş dizi', MetaRegistry::passthrough_array( 'metin' ), [] );

/* ================================================================== */
T::group( 'Köken skoru etkilemez — yapısal (Addendum A §3)' );

// discovered_via hicbir skorlama girdisinde yer almamali. M1'de ScoringPolicy
// henuz yok; bu test alanin kanit hiyerarsisi tablosuna sizmadigini dogrular.
T::is(
	'köken değerleri kanıt ağırlığı tablosunda yok',
	count( array_intersect( DiscoveredVia::values(), array_keys( PublicationType::evidence_weights() ) ) ),
	0
);
T::is(
	'köken değeri ağırlık üretmez',
	PublicationType::evidence_weight( DiscoveredVia::PUBMED ),
	0
);

/* ================================================================== */
echo PHP_EOL . str_repeat( '─', 60 ) . PHP_EOL;
printf( 'Toplam: %d   Geçen: %d   Başarısız: %d%s', T::$pass + T::$fail, T::$pass, T::$fail, PHP_EOL );

if ( T::$fail > 0 ) {
	echo PHP_EOL . 'Başarısız testler:' . PHP_EOL;
	foreach ( T::$failures as $failure ) {
		echo '  · ' . $failure . PHP_EOL;
	}
	exit( 1 );
}

exit( 0 );
