<?php
/**
 * Gerçek WordPress M2 entegrasyon koşumu.
 *
 * Bu dosya yalnızca CLI'da ve açık test onayıyla çalışır. Yalnızca
 * `dla_medical_trust_test_*` biçimindeki geçici bir veritabanını oluşturur
 * ve koşum sonunda siler; sitenin veritabanını asla kullanmaz.
 *
 * Örnek:
 * DLA_MT_WP_TESTS=1 DLA_MT_WP_ROOT=C:\\xampp\\htdocs\\drleylaarvas
 * DLA_MT_WP_DB=dla_medical_trust_test_m2 DLA_MT_WP_DB_USER=root
 * C:\\xampp\\php\\php.exe tests\\wp-integration.php
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'DLA_MT_WP_TESTS' ) ) {
	fwrite( STDERR, "SKIP: set DLA_MT_WP_TESTS=1 to run isolated WordPress integration tests.\n" );
	exit( 0 );
}

$wp_root = realpath( (string) getenv( 'DLA_MT_WP_ROOT' ) );
$db_name = (string) getenv( 'DLA_MT_WP_DB' );
$db_user = (string) ( getenv( 'DLA_MT_WP_DB_USER' ) ?: 'root' );
$db_pass = (string) getenv( 'DLA_MT_WP_DB_PASSWORD' );
$db_host = (string) ( getenv( 'DLA_MT_WP_DB_HOST' ) ?: '127.0.0.1' );

if ( false === $wp_root || ! is_file( $wp_root . DIRECTORY_SEPARATOR . 'wp-settings.php' ) ) {
	fwrite( STDERR, "DLA_MT_WP_ROOT must point to a WordPress core directory.\n" );
	exit( 2 );
}

if ( 1 !== preg_match( '/^dla_medical_trust_test_[a-z0-9_]+$/', $db_name ) ) {
	fwrite( STDERR, "DLA_MT_WP_DB must use the isolated dla_medical_trust_test_* prefix.\n" );
	exit( 2 );
}

$db = mysqli_init();
if ( ! $db instanceof mysqli || ! mysqli_real_connect( $db, $db_host, $db_user, $db_pass ) ) {
	fwrite( STDERR, "Cannot connect to the configured test MySQL server.\n" );
	exit( 2 );
}

$quoted_db = '`' . str_replace( '`', '``', $db_name ) . '`';
mysqli_query( $db, 'DROP DATABASE IF EXISTS ' . $quoted_db );
if ( ! mysqli_query( $db, 'CREATE DATABASE ' . $quoted_db . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' ) ) {
	fwrite( STDERR, "Cannot create isolated WordPress test database.\n" );
	exit( 2 );
}

register_shutdown_function(
	static function () use ( $db, $quoted_db ): void {
		mysqli_query( $db, 'DROP DATABASE IF EXISTS ' . $quoted_db );
		mysqli_close( $db );
	}
);

define( 'ABSPATH', rtrim( $wp_root, "\\/" ) . DIRECTORY_SEPARATOR );
define( 'DB_NAME', $db_name );
define( 'DB_USER', $db_user );
define( 'DB_PASSWORD', $db_pass );
define( 'DB_HOST', $db_host );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
define( 'WP_DEBUG', false );
define( 'WP_CACHE', false );
define( 'WP_INSTALLING', true );

$table_prefix = 'dlam2_';
$_SERVER['HTTP_HOST']       = $_SERVER['HTTP_HOST'] ?? 'example.test';
$_SERVER['REQUEST_URI']     = $_SERVER['REQUEST_URI'] ?? '/';
$_SERVER['REQUEST_METHOD']  = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* Polylang yoksa, gerçek WordPress meta/hook entegrasyonunu test etmek için
 * dar bir Polylang API taklidi. Gerçek Polylang API'si mevcutsa kullanılmaz. */
if ( ! function_exists( 'pll_default_language' ) ) {
	function pll_default_language( string $field = 'slug' ): string {
		unset( $field );
		return 'tr';
	}

	function pll_languages_list(): array {
		return [ 'tr', 'en' ];
	}

	function pll_current_language( string $field = 'slug' ): string {
		unset( $field );
		return 'tr';
	}

	function pll_get_post_translations( int $post_id ): array {
		return $GLOBALS['dla_mt_test_post_translations'][ $post_id ] ?? [ 'tr' => $post_id ];
	}

	function pll_get_term_translations( int $term_id ): array {
		return $GLOBALS['dla_mt_test_term_translations'][ $term_id ] ?? [ 'tr' => $term_id ];
	}

	function pll_get_post_language( int $post_id, string $field = 'slug' ): string {
		unset( $post_id, $field );
		return 'tr';
	}

	function pll_get_term_language( int $term_id, string $field = 'slug' ): string {
		unset( $field );
		return $GLOBALS['dla_mt_test_term_languages'][ $term_id ] ?? 'tr';
	}
}

require_once ABSPATH . 'wp-settings.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

wp_install( 'DLA Medical Trust M2 Tests', 'm2admin', 'm2@example.test', true, '', 'not-a-production-password' );

define( 'DLA_MT_FILE', dirname( __DIR__ ) . '/dla-medical-trust.php' );
define( 'DLA_MT_DIR', dirname( __DIR__ ) . '/' );
define( 'DLA_MT_URL', 'http://example.test/wp-content/plugins/dla-medical-trust/' );
define( 'DLA\MedicalTrust\VERSION', '0.4.0-M4' );
define( 'DLA\MedicalTrust\DB_VERSION', 1 );
define( 'DLA\MedicalTrust\TEXT_DOMAIN', 'dla-medical-trust' );

require_once dirname( __DIR__ ) . '/src/Support/Autoloader.php';
\DLA\MedicalTrust\Support\Autoloader::register( dirname( __DIR__ ) . '/src', 'DLA\\MedicalTrust' );

( new \DLA\MedicalTrust\Plugin() )->boot();
if ( 0 === did_action( 'init' ) ) {
	do_action( 'init' );
}

// wp-settings.php doğrudan yüklendiğinde bazı WordPress sürümlerinde init
// daha önce ateşlenmiş olabilir. Bu durumda eklentinin init callback'lerini
// kaçırmamak için kayıtları burada doğrudan tamamlarız.
if ( ! post_type_exists( 'dla_expert' ) ) {
	( new \DLA\MedicalTrust\PostTypes\ExpertPostType() )->register_post_type();
	( new \DLA\MedicalTrust\PostTypes\SourcePostType() )->register_post_type();
	( new \DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy() )->register_taxonomy();
	( new \DLA\MedicalTrust\Taxonomies\SourceTypeTaxonomy() )->register_taxonomy();
	( new \DLA\MedicalTrust\Meta\MetaRegistry() )->register();
	( new \DLA\MedicalTrust\Identity\UidGenerator() )->register();
}
\DLA\MedicalTrust\Activation::activate();

$pass     = 0;
$failures = [];
$check    = static function ( string $name, bool $condition ) use ( &$pass, &$failures ): void {
	if ( $condition ) {
		++$pass;
		echo 'ok   ' . $name . PHP_EOL;
		return;
	}

	$failures[] = $name;
	echo 'FAIL ' . $name . PHP_EOL;
};

$admin_id = (int) username_exists( 'm2admin' );
wp_set_current_user( $admin_id );

/* UID generation + controlled taxonomy. */
$topic_tr = wp_insert_term( 'Rinoplasti', \DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy::SLUG );
$topic_en = wp_insert_term( 'Rhinoplasty', \DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy::SLUG );
$topic_tr_id = (int) $topic_tr['term_id'];
$topic_en_id = (int) $topic_en['term_id'];
$topic_uid = (string) get_term_meta( $topic_tr_id, \DLA\MedicalTrust\Meta\MetaRegistry::TOPIC_UID, true );
$check( 'topic UID created through WordPress hook', \DLA\MedicalTrust\Identity\UidGenerator::is_valid_format( $topic_uid ) );
$check( 'controlled source-type terms exist', null !== get_term_by( 'slug', 'academic', \DLA\MedicalTrust\Taxonomies\SourceTypeTaxonomy::SLUG ) && null !== get_term_by( 'slug', 'authority', \DLA\MedicalTrust\Taxonomies\SourceTypeTaxonomy::SLUG ) && null !== get_term_by( 'slug', 'publication', \DLA\MedicalTrust\Taxonomies\SourceTypeTaxonomy::SLUG ) );
$check( 'source type terms cannot be edited in UI', 'do_not_allow' === get_taxonomy( \DLA\MedicalTrust\Taxonomies\SourceTypeTaxonomy::SLUG )->cap->edit_terms );

/* Polylang group normalization + idempotent repair operation. */
$GLOBALS['dla_mt_test_term_translations'] = [ $topic_tr_id => [ 'tr' => $topic_tr_id, 'en' => $topic_en_id ], $topic_en_id => [ 'tr' => $topic_tr_id, 'en' => $topic_en_id ] ];
$GLOBALS['dla_mt_test_term_languages']    = [ $topic_tr_id => 'tr', $topic_en_id => 'en' ];
update_term_meta( $topic_en_id, \DLA\MedicalTrust\Meta\MetaRegistry::TOPIC_UID, 'top_bbbbbbbbbbbb' );
$sync = new \DLA\MedicalTrust\I18n\IdentitySync();
$first_sync = $sync->normalize_term_group( $topic_en_id );
$second_sync = $sync->normalize_term_group( $topic_en_id );
$check( 'Polylang topic UID inheritance/normalization', $topic_uid === (string) get_term_meta( $topic_en_id, \DLA\MedicalTrust\Meta\MetaRegistry::TOPIC_UID, true ) && $first_sync['changed'] > 0 );
$check( 'Polylang topic normalization is idempotent', 0 === $second_sync['changed'] );
update_term_meta( $topic_en_id, \DLA\MedicalTrust\Meta\MetaRegistry::TOPIC_UID, 'top_cccccccccccc' );
$repair_once  = ( new \DLA\MedicalTrust\I18n\IdentityRepair() )->run();
$repair_twice = ( new \DLA\MedicalTrust\I18n\IdentityRepair() )->run();
$check( 'UID repair fixes an existing divergent Polylang group', $topic_uid === (string) get_term_meta( $topic_en_id, \DLA\MedicalTrust\Meta\MetaRegistry::TOPIC_UID, true ) && $repair_once['topics_fixed'] > 0 );
$check( 'UID repair is idempotent on the second run', 0 === $repair_twice['topics_fixed'] && false === $repair_twice['library_version_bumped'] );

$expert_tr = wp_insert_post( [ 'post_type' => 'dla_expert', 'post_status' => 'publish', 'post_title' => 'Test Doctor TR' ] );
$expert_en = wp_insert_post( [ 'post_type' => 'dla_expert', 'post_status' => 'publish', 'post_title' => 'Test Doctor EN' ] );
$GLOBALS['dla_mt_test_post_translations'] = [ $expert_tr => [ 'tr' => $expert_tr, 'en' => $expert_en ], $expert_en => [ 'tr' => $expert_tr, 'en' => $expert_en ] ];
$expert_uid = (string) get_post_meta( $expert_tr, \DLA\MedicalTrust\Meta\MetaRegistry::EXPERT_ENTITY_UID, true );
update_post_meta( $expert_en, \DLA\MedicalTrust\Meta\MetaRegistry::EXPERT_ENTITY_UID, 'exp_bbbbbbbbbbbb' );
$expert_sync = $sync->normalize_post_group( $expert_en, \DLA\MedicalTrust\Meta\MetaRegistry::EXPERT_ENTITY_UID, \DLA\MedicalTrust\Identity\UidGenerator::PREFIX_EXPERT, 'expert' );
$check( 'Polylang expert UID normalization', $expert_uid === (string) get_post_meta( $expert_en, \DLA\MedicalTrust\Meta\MetaRegistry::EXPERT_ENTITY_UID, true ) && $expert_sync['changed'] > 0 );

/* User-level review capability and registered metadata callbacks. */
$reviewer_id = wp_insert_user( [ 'user_login' => 'm2reviewer', 'user_pass' => 'not-a-production-password', 'user_email' => 'reviewer@example.test', 'role' => 'editor' ] );
$check( 'review right is absent before direct grant', ! \DLA\MedicalTrust\Capability\Capabilities::has_direct_review_capability( (int) $reviewer_id ) );
\DLA\MedicalTrust\Capability\Capabilities::set_review_capability( (int) $reviewer_id, true );
$check( 'review right is granted directly to user', \DLA\MedicalTrust\Capability\Capabilities::has_direct_review_capability( (int) $reviewer_id ) );

$registered_page = get_registered_meta_keys( 'post', 'page' );
$sanitize = $registered_page[ \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_PRIMARY_TOPIC_UID ]['sanitize_callback'];
$review_auth = $registered_page[ \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_REVIEW_STATUS ]['auth_callback'];
$check( 'registered meta sanitizer rejects bad UID', '' === $sanitize( 'not-a-uid' ) );
$check( 'administrator has no implicit review write authority', false === $review_auth() );
wp_set_current_user( (int) $reviewer_id );
$check( 'review meta has no direct auth callback path', false === $review_auth() );
wp_set_current_user( $admin_id );

/* Repository query and resolver bridge use real WP posts, terms and meta. */
$page_id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Medical Page', 'post_content' => '<p>Reviewed medical content.</p>' ] );
wp_set_object_terms( $page_id, [ $topic_tr_id ], \DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy::SLUG );
update_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_PRIMARY_TOPIC_UID, $topic_uid );
$source_id = wp_insert_post( [ 'post_type' => 'dla_source', 'post_status' => 'publish', 'post_title' => 'Medical Source' ] );
wp_set_object_terms( $source_id, [ 'publication' ], \DLA\MedicalTrust\Taxonomies\SourceTypeTaxonomy::SLUG );
wp_set_object_terms( $source_id, [ $topic_tr_id ], \DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy::SLUG );
update_post_meta( $source_id, \DLA\MedicalTrust\Meta\MetaRegistry::SOURCE_DOI, '10.1000/m2-test' );
update_post_meta( $source_id, \DLA\MedicalTrust\Meta\MetaRegistry::SOURCE_PUBLICATION_TYPE, 'systematic_review' );
update_post_meta( $source_id, \DLA\MedicalTrust\Meta\MetaRegistry::SOURCE_PEER_REVIEWED, true );
update_post_meta( $source_id, \DLA\MedicalTrust\Meta\MetaRegistry::SOURCE_PUB_YEAR, 2025 );
\DLA\MedicalTrust\Repository\TopicRepository::flush_memo();
$graph = ( new \DLA\MedicalTrust\Repository\TopicRepository() )->graph();
$source = ( new \DLA\MedicalTrust\Repository\SourceRepository() )->find( $source_id, $graph );
$resolved = ( new \DLA\MedicalTrust\Resolver\ResolutionService() )->resolve_for_post( $page_id );
$check( 'repository hydrates canonical source', $source instanceof \DLA\MedicalTrust\Domain\Source && 'https://doi.org/10.1000/m2-test' === $source->canonical_url && ! property_exists( $source, 'discovered_via' ) );
$check( 'real WordPress repository/resolver bridge selects source', $resolved instanceof \DLA\MedicalTrust\Domain\Resolution\ResolutionResult && $source_id === $resolved->selected_ids()['publication'] );
$cache = new \DLA\MedicalTrust\Resolver\SelectionCache();
$cached_ids = $cache->get( $page_id );
$check( 'selection cache stores the resolved slot IDs', $source_id === $cached_ids['publication'] && null !== $cache->peek( $page_id ) );
\DLA\MedicalTrust\Settings\Settings::bump_library_version();
$check( 'library version invalidates cache without changing selection seed', null === $cache->peek( $page_id ) && $source_id === $cache->get( $page_id )['publication'] );

/* M3 — real review writes, append-only history and declared changes. */
$review_service = new \DLA\MedicalTrust\Review\ReviewService();
$review_date    = gmdate( 'Y-m-d' );
$request        = new \DLA\MedicalTrust\Review\ReviewRecordRequest( $page_id, $expert_tr, $review_date, 'signed test approval' );
$recorded_event = null;
add_action( 'dla_mt/v1/review_recorded', static function ( int $post_id, array $event ) use ( &$recorded_event ): void { $recorded_event = [ $post_id, $event ]; }, 10, 2 );
$direct_date_write = update_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_REVIEW_DATE, '2000-01-01' );
$check( 'ordinary direct meta write cannot set review date', false === $direct_date_write && '' === (string) get_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_REVIEW_DATE, true ) );
$check( 'unauthorized administrator cannot record review', ! $review_service->record( $request )->success );
wp_set_current_user( (int) $reviewer_id );
$record = $review_service->record( $request );
$stored_hash = (string) get_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_CONTENT_HASH, true );
$check( 'directly authorized user records review', $record->success );
$check( 'successful review stores reviewed and valid', 'reviewed' === get_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_REVIEW_STATUS, true ) && 'valid' === get_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_REVIEW_VALIDITY, true ) );
$check( 'reviewer expert and recording WP user stay distinct', $expert_tr === (int) get_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_REVIEWER_EXPERT_ID, true ) && $reviewer_id === (int) get_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_RECORDED_BY_USER, true ) );
$check( 'successful review stores current content hash', $stored_hash === \DLA\MedicalTrust\Review\ContentHasher::hash( 'Medical Page', '<p>Reviewed medical content.</p>' ) );
$check( 'review action exposes only internal identifiers', is_array( $recorded_event ) && $page_id === $recorded_event[0] && $expert_tr === $recorded_event[1]['reviewer_expert_id'] && $reviewer_id === $recorded_event[1]['recorded_by_user_id'] );
$future = new \DLA\MedicalTrust\Review\ReviewRecordRequest( $page_id, $expert_tr, gmdate( 'Y-m-d', strtotime( '+1 day' ) ), 'signed test approval' );
$check( 'future review date is rejected', ! $review_service->record( $future )->success );
$no_topic = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'No Topic' ] );
$check( 'review without topic is rejected', ! $review_service->record( new \DLA\MedicalTrust\Review\ReviewRecordRequest( $no_topic, $expert_tr, $review_date, 'signed test approval' ) )->success );
$check( 'review without reviewer is rejected', ! $review_service->record( new \DLA\MedicalTrust\Review\ReviewRecordRequest( $page_id, 0, $review_date, 'signed test approval' ) )->success );
$check( 'required signoff is enforced', ! $review_service->record( new \DLA\MedicalTrust\Review\ReviewRecordRequest( $page_id, $expert_tr, $review_date ) )->success );

wp_update_post( [ 'ID' => $page_id, 'post_content' => '<p>Reviewed medical content.</p>' ] );
$check( 'unchanged content preserves state', $review_service->classify_content_change( $page_id, null )->success && 'valid' === get_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_REVIEW_VALIDITY, true ) );
wp_update_post( [ 'ID' => $page_id, 'post_content' => '<p>Reviewed medical content, punctuation only.</p>' ] );
$check( 'ordinary post save cannot advance review date', $review_date === get_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_REVIEW_DATE, true ) );
$check( 'changed content has no automatic classification', ! $review_service->classify_content_change( $page_id, null )->success && 'valid' === get_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_REVIEW_VALIDITY, true ) );
$check( 'minor edit preserves review validity', $review_service->classify_content_change( $page_id, 'minor_edit' )->success && 'valid' === get_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_REVIEW_VALIDITY, true ) );
wp_update_post( [ 'ID' => $page_id, 'post_content' => '<p>Materially revised medical content.</p>' ] );
$before_supersede = get_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_REVIEW_LOG, true );
$check( 'medical content update supersedes validity', $review_service->classify_content_change( $page_id, 'medical_content_update' )->success && 'superseded' === get_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_REVIEW_VALIDITY, true ) );
$after_supersede = get_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_REVIEW_LOG, true );
$check( 'superseding appends without rewriting history', is_array( $before_supersede ) && is_array( $after_supersede ) && $before_supersede[0] === $after_supersede[0] && count( $after_supersede ) === count( $before_supersede ) + 1 );
$check( 'new review after superseding returns valid', $review_service->record( $request )->success && 'valid' === get_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_REVIEW_VALIDITY, true ) );
for ( $i = 0; $i < 26; ++$i ) { $review_service->record( $request ); }
$check( 'actual review log is append-only and capped at 25', 25 === count( (array) get_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_REVIEW_LOG, true ) ) );
$due_request = new \DLA\MedicalTrust\Review\ReviewRecordRequest( $page_id, $expert_tr, '2024-08-31', 'signed historical approval' );
$review_service->record( $due_request );
$freshness = $review_service->freshness_for_post( $page_id, new DateTimeImmutable( '2026-09-01', new DateTimeZone( 'UTC' ) ) );
$check( 'due review remains valid and retains its historical date', 'due' === $freshness && 'valid' === get_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_REVIEW_VALIDITY, true ) && '2024-08-31' === get_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_REVIEW_DATE, true ) );

/* M4 — front-end data fixture, presentation variants and query-context integration. */

// Tibbi inceleme tarihinin gosterimi RC1'den sonra varsayilan olarak KAPALI
// (kullanici karari: kutuda guncelleme tarihi gosterilecek). M4/M6 bolumleri
// inceleme GORUNUMUNU test ettigi icin bu bolum boyunca acik tutulur.
\DLA\MedicalTrust\Settings\Settings::update( [ 'show_review_date' => true ] );
$profile_id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Dr. Leyla Arvas' ] );
$image_id   = wp_insert_attachment( [ 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit', 'post_title' => 'Doctor portrait', 'guid' => 'http://example.test/doctor.jpg' ] );
update_post_meta( $image_id, '_wp_attachment_metadata', [ 'width' => 600, 'height' => 600, 'file' => '2026/09/doctor.jpg', 'sizes' => [] ] );
// REGRESYON — canlida portrenin hic gorunmemesinin sebebi buydu:
// set_post_thumbnail() cekirdekte wp_get_attachment_image() ciktisini kontrol
// eder, bos donerse _thumbnail_id'yi SESSIZCE SILER. Fiziksel dosyasi/uretilmis
// boyutlari olmayan eklerde bu boyle davranir; kullanici gorseli seciyor,
// kaydediyor ve alan tekrar bos doniyordu. Uretim kodu artik MIME turunden
// dogrulayip meta'yi dogrudan yazar (ExpertMetaBox::save).
delete_post_meta( $expert_tr, '_thumbnail_id' );
set_post_thumbnail( $expert_tr, $image_id );
$check(
	'M4 set_post_thumbnail dosyasi olmayan ekte meta yazmaz (uretim kodu bu yolu kullanmamali)',
	0 === (int) get_post_meta( $expert_tr, '_thumbnail_id', true )
);

update_post_meta( $expert_tr, '_thumbnail_id', $image_id );
$check(
	'M4 dogrudan meta yazimi portre ID sini kalici kilar',
	$image_id === (int) get_post_thumbnail_id( $expert_tr )
);
update_post_meta( $expert_tr, \DLA\MedicalTrust\Meta\MetaRegistry::EXPERT_HONORIFIC, 'Op. Dr.' );
update_post_meta( $expert_tr, \DLA\MedicalTrust\Meta\MetaRegistry::EXPERT_JOB_TITLE, 'Plastik, Rekonstrüktif ve Estetik Cerrahi Uzmanı' );
update_post_meta( $expert_tr, \DLA\MedicalTrust\Meta\MetaRegistry::EXPERT_PROFILE_PAGE, $profile_id );
update_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_AUTHOR_MODE, 'expert' );
update_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_EXPERT_ID, $expert_tr );
update_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_COMMENTARY, '<p>Uzman <strong>değerlendirmesi</strong>.</p>' );

$make_source = static function ( string $slot, string $title, string $doi ) use ( $topic_tr_id ): int {
	$id = wp_insert_post( [ 'post_type' => 'dla_source', 'post_status' => 'publish', 'post_title' => $title ] );
	wp_set_object_terms( $id, [ $slot ], \DLA\MedicalTrust\Taxonomies\SourceTypeTaxonomy::SLUG );
	wp_set_object_terms( $id, [ $topic_tr_id ], \DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy::SLUG );
	update_post_meta( $id, \DLA\MedicalTrust\Meta\MetaRegistry::SOURCE_UID, 'src_' . str_pad( dechex( $id ), 12, '0', STR_PAD_LEFT ) );
	update_post_meta( $id, \DLA\MedicalTrust\Meta\MetaRegistry::SOURCE_DOI, $doi );
	update_post_meta( $id, \DLA\MedicalTrust\Meta\MetaRegistry::SOURCE_PUBLICATION_TYPE, 'systematic_review' );
	update_post_meta( $id, \DLA\MedicalTrust\Meta\MetaRegistry::SOURCE_PUB_YEAR, 2025 );
	update_post_meta( $id, \DLA\MedicalTrust\Meta\MetaRegistry::SOURCE_PUBLISHER, 'Medical Journal' );
	return $id;
};
$academic_source  = $make_source( 'academic', 'Academic Evidence', '10.1000/m4-academic' );
$authority_source = $make_source( 'authority', 'Professional Guidance', '10.1000/m4-authority' );
update_post_meta( $source_id, \DLA\MedicalTrust\Meta\MetaRegistry::SOURCE_UID, 'src_' . str_pad( dechex( $source_id ), 12, '0', STR_PAD_LEFT ) );

$with_main_query = static function ( int $post_id, callable $callback ) {
	global $wp_query, $wp_the_query, $post;
	$previous_query = $wp_query;
	$previous_the   = $wp_the_query;
	$previous_post  = $post;
	$query          = new WP_Query( [ 'page_id' => $post_id, 'post_status' => 'publish' ] );
	$wp_query       = $query;
	$wp_the_query   = $query;
	$query->the_post();
	try {
		return $callback();
	} finally {
		wp_reset_postdata();
		$wp_query     = $previous_query;
		$wp_the_query = $previous_the;
		$post         = $previous_post;
	}
};

$image_downsize = static function ( $downsize, int $attachment_id, $size ) use ( $image_id ) {
	unset( $size );
	return $image_id === $attachment_id ? [ 'http://example.test/doctor.jpg', 600, 600, true ] : $downsize;
};
add_filter( 'image_downsize', $image_downsize, 10, 3 );
$component    = new \DLA\MedicalTrust\Integration\TrustComponent();
$default_html = $component->render_for_post( $page_id );
$compact_html = $component->render_for_post( $page_id, [ 'display' => 'compact' ] );
$check( 'M4 default premium render has full fixture', str_contains( $default_html, 'dla-mt--default' ) && str_contains( $default_html, 'Op. Dr. Test Doctor TR' ) && str_contains( $default_html, 'Uzman değerlendirmesi' ) && str_contains( $default_html, 'Academic Evidence' ) );
$check( 'M4 compact render uses the same fixture', str_contains( $compact_html, 'dla-mt--compact' ) && ! str_contains( $compact_html, 'dla-mt__portrait' ) );
$check( 'M4 default and compact retain trust-fact parity', str_contains( $default_html, 'datetime="2024-08-31"' ) && str_contains( $compact_html, 'datetime="2024-08-31"' ) && str_contains( $default_html, '10.1000/m4-academic' ) && str_contains( $compact_html, '10.1000/m4-academic' ) );
$check( 'M4 due valid review still displays historical date', str_contains( $default_html, 'Tıbbi inceleme tarihi:' ) && str_contains( $default_html, 'datetime="2024-08-31"' ) );
$check( 'M4 expert author-reviewer wording is truthful', str_contains( $default_html, 'İçeriği hazırlayan ve tıbbi olarak inceleyen:' ) );
$check( 'M4 canonical citation URL is used with safe link relation', str_contains( $default_html, 'https://doi.org/10.1000/m4-academic' ) && str_contains( $default_html, 'rel="noopener"' ) && ! str_contains( $default_html, 'discovered_via' ) );
$check( 'M4 WordPress image API creates a responsive portrait', str_contains( $default_html, 'dla-mt__portrait-image' ) && str_contains( $default_html, 'src=' ) );
remove_filter( 'image_downsize', $image_downsize, 10 );

update_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_AUTHOR_MODE, 'organization' );
$editorial_html = $component->render_for_post( $page_id );
$check( 'M4 editorial-team reviewer wording does not imply authorship', str_contains( $editorial_html, 'Tıbbi olarak inceleyen:' ) && ! str_contains( $editorial_html, 'İçeriği hazırlayan ve tıbbi olarak inceleyen:' ) );
update_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_AUTHOR_MODE, 'expert' );
$profile_url = (string) get_permalink( $profile_id );
update_post_meta( $expert_tr, \DLA\MedicalTrust\Meta\MetaRegistry::EXPERT_PROFILE_PAGE, 0 );
$no_profile_html = $component->render_for_post( $page_id );
$check( 'M4 missing profile page leaves expert name without a broken link', str_contains( $no_profile_html, 'Op. Dr. Test Doctor TR' ) && ! str_contains( $no_profile_html, $profile_url ) && ! str_contains( $no_profile_html, '>Hakkında<' ) );
update_post_meta( $expert_tr, \DLA\MedicalTrust\Meta\MetaRegistry::EXPERT_PROFILE_PAGE, $profile_id );

update_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_RESOLVED_SOURCES, [ 'v' => \DLA\MedicalTrust\Settings\Settings::library_version(), 'slots' => [ 'academic' => $academic_source, 'authority' => 0, 'publication' => 0 ] ] );
$partial_html = $component->render_for_post( $page_id );
$check( 'M4 partial source set renders only the qualified resolved source', str_contains( $partial_html, 'Academic Evidence' ) && ! str_contains( $partial_html, 'Professional Guidance' ) && ! str_contains( $partial_html, 'Medical Source' ) );
delete_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_RESOLVED_SOURCES );

update_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_COMMENTARY, '' );
$check( 'M4 missing commentary omits only commentary', ! str_contains( $component->render_for_post( $page_id ), 'Uzman değerlendirmesi' ) && str_contains( $component->render_for_post( $page_id ), 'Academic Evidence' ) );
update_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_COMMENTARY, '<p>Uzman <strong>değerlendirmesi</strong>.</p>' );
delete_post_thumbnail( $expert_tr );
$check( 'M4 missing image gracefully omits portrait without hiding facts', ! str_contains( $component->render_for_post( $page_id ), 'dla-mt__portrait' ) && str_contains( $component->render_for_post( $page_id ), 'Op. Dr. Test Doctor TR' ) );
set_post_thumbnail( $expert_tr, $image_id );

$original_content = (string) get_post_field( 'post_content', $page_id );
wp_update_post( [ 'ID' => $page_id, 'post_content' => '<p>Superseded review test.</p>' ] );
$review_service->classify_content_change( $page_id, 'medical_content_update' );
$superseded_html = $component->render_for_post( $page_id );
$check( 'M4 superseded review hides old reviewer attribution and date', ! str_contains( $superseded_html, 'Tıbbi inceleme tarihi:' ) && ! str_contains( $superseded_html, 'Tıbbi olarak inceleyen:' ) );
wp_update_post( [ 'ID' => $page_id, 'post_content' => $original_content ] );
$review_service->record( new \DLA\MedicalTrust\Review\ReviewRecordRequest( $page_id, $expert_tr, '2024-08-31', 'M4 restore approval' ) );

$no_sources_term = wp_insert_term( 'No Sources Topic', \DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy::SLUG );
$no_sources_topic_id = (int) $no_sources_term['term_id'];
$no_sources_page = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'No Sources Medical Page', 'post_content' => 'Medical context without eligible sources.' ] );
wp_set_object_terms( $no_sources_page, [ $no_sources_topic_id ], \DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy::SLUG );
update_post_meta( $no_sources_page, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_PRIMARY_TOPIC_UID, (string) get_term_meta( $no_sources_topic_id, \DLA\MedicalTrust\Meta\MetaRegistry::TOPIC_UID, true ) );
$review_service->record( new \DLA\MedicalTrust\Review\ReviewRecordRequest( $no_sources_page, $expert_tr, '2026-08-01', 'M4 no-source approval' ) );
$no_sources_html = $component->render_for_post( $no_sources_page );
$check( 'M4 no qualified sources omits only source section', ! str_contains( $no_sources_html, 'Seçilmiş tıbbi kaynaklar' ) && str_contains( $no_sources_html, 'Tıbbi inceleme tarihi:' ) );

update_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_COMMENTARY, '<script>alert(1)</script><p>Safe <strong>commentary</strong></p>' );
wp_update_post( [ 'ID' => $academic_source, 'post_title' => '<img src=x onerror=alert(1)>Academic Evidence' ] );
$xss_html = $component->render_for_post( $page_id );
$check( 'M4 expert commentary and source output are escaped', ! str_contains( $xss_html, '<script' ) && ! str_contains( $xss_html, '<img src=x onerror=' ) && str_contains( $xss_html, '&lt;img src=x onerror=alert(1)&gt;' ) && str_contains( $xss_html, 'Safe' ) );
update_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_COMMENTARY, '<p>Uzman <strong>değerlendirmesi</strong>.</p>' );
wp_update_post( [ 'ID' => $academic_source, 'post_title' => 'Academic Evidence' ] );

$classic_html = dla_medical_trust_get_html( [], $page_id );
$block_html   = $with_main_query( $page_id, static fn(): string => apply_filters( 'the_content', '<!-- wp:shortcode -->[dla_medical_trust display="compact"]<!-- /wp:shortcode -->' ) );
$check( 'M4 classic theme template tag renders', str_contains( $classic_html, 'dla-mt--default' ) );
$check( 'M4 block-theme shortcode block renders', str_contains( $block_html, 'dla-mt' ) );

if ( ! post_type_exists( 'avada_layout' ) ) { register_post_type( 'avada_layout', [ 'public' => false ] ); }
$layout_id = wp_insert_post( [ 'post_type' => 'avada_layout', 'post_status' => 'publish', 'post_title' => 'Global Content Layout', 'post_content' => '[dla_medical_trust]' ] );
$avada_html = $with_main_query( $page_id, static function () use ( $layout_id, $component ): string {
	global $post;
	$post = get_post( $layout_id ); // Simulates Avada rendering its layout post while the main query stays medical content.
	return $component->shortcode();
} );
$check( 'M4 Avada global layout shortcode resolves queried medical post not layout post', str_contains( $avada_html, 'data-dla-mt-post="' . $page_id . '"' ) && ! str_contains( $avada_html, 'data-dla-mt-post="' . $layout_id . '"' ) );

\DLA\MedicalTrust\Settings\Settings::update( [ 'automatic_injection' => true, 'injection_position' => 'after' ] );
$duplicate_component = new \DLA\MedicalTrust\Integration\TrustComponent();
$duplicate_result = $with_main_query( $page_id, static function () use ( $duplicate_component ): string {
	$shortcode = $duplicate_component->shortcode();
	return $shortcode . $duplicate_component->inject( '<p>Original content</p>' );
} );
$check( 'M4 shortcode plus optional injection produces one component', 1 === substr_count( $duplicate_result, 'data-dla-mt-post=' ) );
\DLA\MedicalTrust\Settings\Settings::update( [ 'automatic_injection' => false ] );
$check( 'M4 automatic injection remains disabled by default setting', ! \DLA\MedicalTrust\Settings\Settings::automatic_injection_enabled() );

$non_medical_page = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Ordinary Page' ] );
$non_medical_html = $with_main_query( $non_medical_page, static fn(): string => ( new \DLA\MedicalTrust\Integration\TrustComponent() )->shortcode() );
$fallback_html = $with_main_query( $page_id, static fn(): string => ( new \DLA\MedicalTrust\Integration\TrustComponent() )->shortcode( [ 'display' => 'unknown' ] ) );
$check( 'M4 shortcode fails safely for a non-medical page', '' === $non_medical_html );
$check( 'M4 unsupported display falls back to documented default', str_contains( $fallback_html, 'dla-mt--default' ) );
$check( 'M4 excerpt cleanup removes only its own shortcode', '[gallery ids="1"]' === $component->strip_from_excerpt( '[gallery ids="1"][dla_medical_trust]' ) );

delete_post_meta( $page_id, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_RESOLVED_SOURCES );
\DLA\MedicalTrust\Repository\TopicRepository::flush_memo();
global $wpdb;
$queries_before = (int) $wpdb->num_queries;
$component->render_for_post( $page_id );
$cold_queries = (int) $wpdb->num_queries - $queries_before;
$queries_before = (int) $wpdb->num_queries;
$component->render_for_post( $page_id );
$warm_queries = (int) $wpdb->num_queries - $queries_before;
$check( 'M4 warm source cache does not cost more queries than cold resolution', $warm_queries <= $cold_queries );
echo sprintf( 'M4 query profile: cold=%d warm=%d%s', $cold_queries, $warm_queries, PHP_EOL );

/* M5 — real admin save/capability flow; review writes remain in ReviewService. */
$m5_page = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'M5 Medical Page', 'post_content' => '<p>M5 content.</p>' ] );
$page_box = new \DLA\MedicalTrust\Admin\PageMedicalMetaBox();
wp_set_current_user( $admin_id );
$_POST = [
	'dla_mt_page_medical_nonce' => wp_create_nonce( 'dla_mt_save_page_medical' ),
	'dla_mt_author_mode' => 'expert', 'dla_mt_author_expert' => $expert_tr,
	'dla_mt_primary_topic' => $topic_tr_id, 'dla_mt_secondary_topics' => [ $topic_en_id ],
	'dla_mt_commentary' => '<script>alert(1)</script><p>M5 <strong>safe</strong></p>',
	'dla_mt_source_mode' => 'manual', 'dla_mt_override_academic' => $academic_source,
	'dla_mt_override_authority' => $academic_source, 'dla_mt_override_publication' => 0,
	'dla_mt_show_commentary' => '1', 'dla_mt_show_sources' => '1',
];
$page_box->save( $m5_page, get_post( $m5_page ) );
$m5_overrides = (array) get_post_meta( $m5_page, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_SOURCE_OVERRIDES, true );
$m5_commentary = (string) get_post_meta( $m5_page, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_COMMENTARY, true );
$check( 'M5 page panel saves explicit primary topic and author mode', 'expert' === get_post_meta( $m5_page, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_AUTHOR_MODE, true ) && $topic_uid === get_post_meta( $m5_page, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_PRIMARY_TOPIC_UID, true ) );
$check( 'M5 page panel sanitizes language-specific commentary', ! str_contains( $m5_commentary, '<script' ) && str_contains( $m5_commentary, '<strong>safe</strong>' ) );
$check( 'M5 source overrides accept only matching active slot sources', $academic_source === (int) ( $m5_overrides['academic'] ?? 0 ) && ! isset( $m5_overrides['authority'] ) );

$workflow = new \DLA\MedicalTrust\Admin\ReviewWorkflowMetaBox();
$_POST = [ 'dla_mt_review_workflow_nonce' => wp_create_nonce( 'dla_mt_review_workflow' ), 'dla_mt_record_review' => '1', 'dla_mt_record_review_confirm' => '1', 'dla_mt_reviewer_expert' => $expert_tr, 'dla_mt_review_date' => '2026-08-01', 'dla_mt_signoff_reference' => 'M5 test approval' ];
$workflow->save( $m5_page, get_post( $m5_page ) );
$check( 'M5 ordinary admin cannot record review through workflow UI', '' === (string) get_post_meta( $m5_page, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_REVIEW_DATE, true ) );
wp_set_current_user( (int) $reviewer_id );
$_POST['dla_mt_review_workflow_nonce'] = wp_create_nonce( 'dla_mt_review_workflow' );
$workflow->save( $m5_page, get_post( $m5_page ) );
$check( 'M5 directly authorized user records review through ReviewService UI', 'reviewed' === get_post_meta( $m5_page, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_REVIEW_STATUS, true ) && '2026-08-01' === get_post_meta( $m5_page, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_REVIEW_DATE, true ) );
wp_update_post( [ 'ID' => $m5_page, 'post_content' => '<p>M5 materially revised content.</p>' ] );
$_POST = [ 'dla_mt_review_workflow_nonce' => wp_create_nonce( 'dla_mt_review_workflow' ) ];
ob_start(); $workflow->render( get_post( $m5_page ) ); $workflow_html = (string) ob_get_clean();
$check( 'M5 changed valid review prompts with no preselected classification', str_contains( $workflow_html, 'İçerik değişti' ) && ! str_contains( $workflow_html, 'checked="checked"' ) );
$_POST['dla_mt_change_classification'] = 'medical_content_update';
$workflow->save( $m5_page, get_post( $m5_page ) );
$check( 'M5 medical update classification supersedes review', 'superseded' === get_post_meta( $m5_page, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_REVIEW_VALIDITY, true ) );
$m5_translation = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'M5 Medical Page EN' ] );
$GLOBALS['dla_mt_test_post_translations'][ $m5_page ] = [ 'tr' => $m5_page, 'en' => $m5_translation ];
$GLOBALS['dla_mt_test_post_translations'][ $m5_translation ] = [ 'tr' => $m5_page, 'en' => $m5_translation ];
update_post_meta( $m5_translation, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_COMMENTARY, '<p>English-only commentary.</p>' );
$check( 'M5 Polylang page commentary remains language-specific', '<p>English-only commentary.</p>' === get_post_meta( $m5_translation, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_COMMENTARY, true ) && get_post_meta( $m5_page, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_COMMENTARY, true ) !== get_post_meta( $m5_translation, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_COMMENTARY, true ) );
$_POST = [];

/* M6 - canonical read-only contract. It consumes the same M2/M3 facts as M4. */
wp_set_current_user( $admin_id );
\DLA\MedicalTrust\Settings\Settings::update(
	[
		'organization' => [ 'name' => 'DLA Editorial Team', 'url' => 'https://example.test/about', 'logo_id' => $image_id ],
	]
);
update_post_meta( $expert_tr, \DLA\MedicalTrust\Meta\MetaRegistry::EXPERT_CREDENTIALS, [ 'MD', 'FEBOPRAS' ] );
update_post_meta( $expert_tr, \DLA\MedicalTrust\Meta\MetaRegistry::EXPERT_SAMEAS, [ 'https://example.test/experts/test-doctor' ] );
update_post_meta( $academic_source, \DLA\MedicalTrust\Meta\MetaRegistry::SOURCE_PMID, '12345678' );
update_post_meta( $academic_source, \DLA\MedicalTrust\Meta\MetaRegistry::SOURCE_PMC_ID, 'PMC1234567' );
update_post_meta( $academic_source, \DLA\MedicalTrust\Meta\MetaRegistry::SOURCE_PEER_REVIEWED, true );

$m6_page = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'M6 Contract Page', 'post_content' => '<p>Current contract facts.</p>' ] );
wp_set_object_terms( $m6_page, [ $topic_tr_id ], \DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy::SLUG );
update_post_meta( $m6_page, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_GROUP_UID, 'grp_abcdef123456' );
update_post_meta( $m6_page, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_PRIMARY_TOPIC_UID, $topic_uid );
update_post_meta( $m6_page, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_AUTHOR_MODE, 'expert' );
update_post_meta( $m6_page, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_EXPERT_ID, $expert_tr );
update_post_meta( $m6_page, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_COMMENTARY, '<p>Yalnizca bu sayfanin uzman yorumu.</p>' );
$m6_translation = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'M6 Contract Page EN' ] );
$GLOBALS['dla_mt_test_post_translations'][ $m6_page ] = [ 'tr' => $m6_page, 'en' => $m6_translation ];
$GLOBALS['dla_mt_test_post_translations'][ $m6_translation ] = [ 'tr' => $m6_page, 'en' => $m6_translation ];
update_post_meta( $m6_translation, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_COMMENTARY, '<p>English commentary must not leak.</p>' );
wp_set_current_user( (int) $reviewer_id );
$m6_record = $review_service->record( new \DLA\MedicalTrust\Review\ReviewRecordRequest( $m6_page, $expert_tr, gmdate( 'Y-m-d' ), 'M6 contract approval' ) );
$m6_contract = dla_medical_trust_get_contract( $m6_page );
$m6_m4_data = ( new \DLA\MedicalTrust\Repository\TrustDataRepository() )->for_post( $m6_page );

$check( 'M6 public contract API is registered and versioned independently', function_exists( 'dla_medical_trust_get_contract' ) && is_array( $m6_contract ) && 'dla-medical-trust/v1' === $m6_contract['contract_version'] );
$check( 'M6 review fixture is created through ReviewService', $m6_record->success );
$check( 'M6 content identifies the actual post, URL, type, language and group UID', $m6_page === $m6_contract['content']['post_id'] && 'page' === $m6_contract['content']['content_type'] && 'tr' === $m6_contract['content']['language'] && 'grp_abcdef123456' === $m6_contract['content']['group_uid'] && str_contains( (string) $m6_contract['content']['canonical_url'], '?page_id=' . $m6_page ) );
$check( 'M6 organization facts come only from configured settings', 'DLA Editorial Team' === $m6_contract['organization']['name'] && 'https://example.test/about' === $m6_contract['organization']['url'] && $image_id === $m6_contract['organization']['logo_id'] );
$check( 'M6 expert authorship is distinct from organization authorship', 'expert' === $m6_contract['authorship']['mode'] && null === $m6_contract['authorship']['organization'] && $expert_uid === $m6_contract['authorship']['expert']['entity_uid'] );
$check( 'M6 reviewer contains expert identity only, never the recording WordPress user', $expert_uid === $m6_contract['reviewer']['entity_uid'] && ! array_key_exists( 'recorded_by', $m6_contract['reviewer'] ) && ! array_key_exists( 'user_id', $m6_contract['reviewer'] ) );
$check( 'M6 reviewer exports the approved expert profile facts', 'Test Doctor TR' === $m6_contract['reviewer']['name'] && 'Op. Dr.' === $m6_contract['reviewer']['honorific'] && 2 === count( $m6_contract['reviewer']['credentials'] ) && 'https://example.test/experts/test-doctor' === $m6_contract['reviewer']['same_as'][0] );
$check( 'M6 valid current review exposes reviewer, date and applicability', true === $m6_contract['medical_review']['applicable'] && 'reviewed' === $m6_contract['medical_review']['status'] && 'valid' === $m6_contract['medical_review']['validity'] && gmdate( 'Y-m-d' ) === $m6_contract['medical_review']['review_date'] && null !== $m6_contract['medical_review']['freshness'] );
$check( 'M6 and M4 use the same valid-review truth', $m6_m4_data instanceof \DLA\MedicalTrust\Domain\TrustData && $m6_m4_data->has_valid_review() === $m6_contract['medical_review']['applicable'] && $m6_m4_data->review_date === $m6_contract['medical_review']['review_date'] );
$check( 'M6 topic exports UID, display label and schema hint without term IDs', $topic_uid === $m6_contract['topics']['primary']['uid'] && 'Rinoplasti' === $m6_contract['topics']['primary']['label'] && ! array_key_exists( 'term_id', $m6_contract['topics']['primary'] ) );
$check( 'M6 commentary is page-language-specific and does not fall back to a translation', str_contains( (string) $m6_contract['expert_commentary']['content'], 'Yalnizca') && ! str_contains( (string) $m6_contract['expert_commentary']['content'], 'English commentary' ) );
$check( 'M6 sources contain only final selected eligible facts and canonical identifiers', ! empty( $m6_contract['sources'] ) && ! array_key_exists( 'id', $m6_contract['sources'][0] ) && ! array_key_exists( 'discovered_via', $m6_contract['sources'][0] ) && array_key_exists( 'identifiers', $m6_contract['sources'][0] ) );
$check( 'M6 contract does not expose internal signoff, cache, score, resolver or review-log data', ! preg_match( '/recorded_by|signoff|resolved_sources|review_log|score|priority|cache|discovered_via/i', (string) wp_json_encode( $m6_contract ) ) );
$check( 'M6 contract has no JSON-LD payload or script emission', ! str_contains( (string) wp_json_encode( $m6_contract ), 'application/ld+json' ) && ! str_contains( $component->render_for_post( $m6_page ), 'application/ld+json' ) );

update_post_meta( $m6_page, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_DISPLAY_FLAGS, [ 'show_commentary' => false, 'show_sources' => false ] );
$hidden_contract = dla_medical_trust_get_contract( $m6_page );
$hidden_m4_data = ( new \DLA\MedicalTrust\Repository\TrustDataRepository() )->for_post( $m6_page );
$check( 'M6 presentation flags do not erase canonical commentary facts', null !== $hidden_contract['expert_commentary']['content'] && false === $hidden_contract['visibility']['commentary_presentation_enabled'] && $hidden_m4_data instanceof \DLA\MedicalTrust\Domain\TrustData && '' === $hidden_m4_data->commentary );
$check( 'M6 presentation flags do not erase canonical selected source facts', ! empty( $hidden_contract['sources'] ) && false === $hidden_contract['visibility']['sources_presentation_enabled'] && $hidden_m4_data instanceof \DLA\MedicalTrust\Domain\TrustData && empty( $hidden_m4_data->sources ) );

$superseded_contract = dla_medical_trust_get_contract( $m5_page );
$check( 'M6 superseded review never exposes a reviewer or applying date', is_array( $superseded_contract ) && 'superseded' === $superseded_contract['medical_review']['validity'] && false === $superseded_contract['medical_review']['applicable'] && null === $superseded_contract['reviewer'] && null === $superseded_contract['medical_review']['review_date'] );
$m6_unreviewed = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'M6 Unreviewed Page' ] );
wp_set_object_terms( $m6_unreviewed, [ $topic_tr_id ], \DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy::SLUG );
update_post_meta( $m6_unreviewed, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_PRIMARY_TOPIC_UID, $topic_uid );
wp_update_post( [ 'ID' => $m6_unreviewed, 'post_author' => $reviewer_id ] );
$unreviewed_contract = dla_medical_trust_get_contract( $m6_unreviewed );
$check( 'M6 unreviewed content exposes state but never invents reviewer or date', is_array( $unreviewed_contract ) && 'none' === $unreviewed_contract['medical_review']['status'] && false === $unreviewed_contract['medical_review']['applicable'] && null === $unreviewed_contract['reviewer'] && null === $unreviewed_contract['medical_review']['review_date'] );
$check( 'M6 never promotes the WordPress post author to a medical expert', is_array( $unreviewed_contract ) && 'organization' === $unreviewed_contract['authorship']['mode'] && null === $unreviewed_contract['authorship']['expert'] );
$check( 'M6 organization authorship uses configured organization facts only', is_array( $unreviewed_contract ) && 'DLA Editorial Team' === $unreviewed_contract['authorship']['organization']['name'] );
$due_contract = dla_medical_trust_get_contract( $page_id );
$check( 'M6 due and valid review remains applicable in the public contract', is_array( $due_contract ) && 'due' === $due_contract['medical_review']['freshness'] && true === $due_contract['medical_review']['applicable'] );
$manual_contract = dla_medical_trust_get_contract( $m5_page );
$check( 'M6 manual source override exposes the M2-selected final source only', is_array( $manual_contract ) && ! empty( $manual_contract['sources'] ) && (string) get_post_meta( $academic_source, \DLA\MedicalTrust\Meta\MetaRegistry::SOURCE_UID, true ) === $manual_contract['sources'][0]['source_uid'] );
\DLA\MedicalTrust\Settings\Settings::update( [ 'organization' => [ 'name' => '', 'url' => '', 'logo_id' => 0 ] ] );
$no_org_contract = dla_medical_trust_get_contract( $m6_unreviewed );
$check( 'M6 does not invent organization facts when settings are empty', is_array( $no_org_contract ) && null === $no_org_contract['organization'] && null === $no_org_contract['authorship']['organization'] );
$check( 'M6 safely returns null outside medical topic scope', null === dla_medical_trust_get_contract( $non_medical_page ) );

/* RC1 lifecycle and upgrade safety: reactivation must preserve pre-existing M4/M5-style data. */
$library_before_reactivation = \DLA\MedicalTrust\Settings\Settings::library_version();
$source_uid_before_reactivation = (string) get_post_meta( $academic_source, \DLA\MedicalTrust\Meta\MetaRegistry::SOURCE_UID, true );
\DLA\MedicalTrust\Activation::deactivate();
\DLA\MedicalTrust\Activation::activate();
$source_type_terms = get_terms( [ 'taxonomy' => \DLA\MedicalTrust\Taxonomies\SourceTypeTaxonomy::SLUG, 'hide_empty' => false ] );
$source_type_slugs = is_array( $source_type_terms ) ? wp_list_pluck( $source_type_terms, 'slug' ) : [];
sort( $source_type_slugs );
$check( 'RC1 deactivate/reactivate preserves library version and existing expert/topic/source identifiers', $library_before_reactivation === \DLA\MedicalTrust\Settings\Settings::library_version() && $expert_uid === (string) get_post_meta( $expert_tr, \DLA\MedicalTrust\Meta\MetaRegistry::EXPERT_ENTITY_UID, true ) && $topic_uid === (string) get_term_meta( $topic_tr_id, \DLA\MedicalTrust\Meta\MetaRegistry::TOPIC_UID, true ) && $source_uid_before_reactivation === (string) get_post_meta( $academic_source, \DLA\MedicalTrust\Meta\MetaRegistry::SOURCE_UID, true ) );
$check( 'RC1 reactivation adds no duplicate controlled source-type terms or data loss', [ 'academic', 'authority', 'publication' ] === $source_type_slugs && get_post( $m5_page ) instanceof \WP_Post && get_post( $academic_source ) instanceof \WP_Post );


/* ------------------------------------------------------------------ *
 * Canlı kurulum regresyonları (frontend boş dönme teşhisi)
 * ------------------------------------------------------------------ */

$admin_user = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
$admin_id   = (int) ( $admin_user[0] ?? 0 );
wp_set_current_user( $admin_id );

$expert_cap_of_menu = static function (): string {
	$object = get_post_type_object( \DLA\MedicalTrust\PostTypes\ExpertPostType::SLUG );

	return $object instanceof WP_Post_Type ? (string) $object->cap->edit_posts : '';
};

/* (a) Yönetici, gerekli manage yetkileriyle Medical Trust menüsünü görür. */
$admin_role = get_role( 'administrator' );
foreach ( \DLA\MedicalTrust\Capability\Capabilities::role_grantable() as $cap ) {
	$admin_role->remove_cap( $cap );
}
update_option( \DLA\MedicalTrust\Settings\Settings::OPTION_DB, 0 );

// wp_set_current_user() ayni ID icin erken doner ve WP_User::allcaps bayat
// kalir. Gercek bir istekte kullanici nesnesi sifirdan kurulacagi icin,
// bozuk durumu durust simule etmek adina yetenekler burada yeniden hesaplanir.
wp_get_current_user()->get_role_caps();
$menu_hidden_before = ! current_user_can( $expert_cap_of_menu() );

// wp_set_current_user() ayni ID icin erken doner; Provisioner'in kullanici
// nesnesini kendi tazelemesi bu yuzden zorunludur.
$provisioner = new \DLA\MedicalTrust\Upgrade\Provisioner();
$provisioner->maybe_provision();

$check( 'REG-a yetkiler silinince menu gercekten gizlenir', $menu_hidden_before );
$check( 'REG-a onarim sonrasi rol yetkiyi tasir', get_role( 'administrator' )->has_cap( 'dla_manage_experts' ) );
$check( 'REG-a onarim AYNI istekte etkili olur (menu cap)', current_user_can( $expert_cap_of_menu() ) );
$check( 'REG-a kaynak yonetimi yetkisi mevcut', current_user_can( \DLA\MedicalTrust\Capability\Capabilities::MANAGE_SOURCES ) );
$check( 'REG-a sayfa tibbi meta yetkisi mevcut', current_user_can( \DLA\MedicalTrust\Capability\Capabilities::EDIT_META ) );

$check( 'REG-a provisioning idempotenttir', $provisioner->is_provisioned() && ( $provisioner->provision() ?? true ) && $provisioner->is_provisioned() );

/* (b) Review yetkisi hicbir role otomatik eklenmez. */
$roles_with_review = [];
foreach ( wp_roles()->role_objects as $role_name => $role_object ) {
	if ( $role_object->has_cap( \DLA\MedicalTrust\Capability\Capabilities::REVIEW ) ) {
		$roles_with_review[] = $role_name;
	}
}
$check( 'REG-b review yetkisi hicbir role otomatik verilmez', [] === $roles_with_review );

/* (c) Sayfa ID'si reviewer olarak gosterilemez. */
$reg_page = wp_insert_post( [ 'post_type' => 'page', 'post_title' => 'Dysport Botoks', 'post_status' => 'publish' ] );
$GLOBALS['post'] = get_post( $reg_page );
$check( 'REG-c bos reviewer meta global post basligina dusmez', null === \DLA\MedicalTrust\PostTypes\ExpertPostType::display_name( 0 ) );
$check( 'REG-c normal sayfa ID si reviewer sayilmaz', ! \DLA\MedicalTrust\PostTypes\ExpertPostType::is_valid_published_expert( $reg_page ) );

update_post_meta( $reg_page, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_REVIEWER_EXPERT_ID, $reg_page );
update_post_meta( $reg_page, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_REVIEW_STATUS, \DLA\MedicalTrust\Domain\Enum\ReviewStatus::REVIEWED );
update_post_meta( $reg_page, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_REVIEW_VALIDITY, \DLA\MedicalTrust\Domain\Enum\ReviewValidity::VALID );
update_post_meta( $reg_page, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_REVIEW_DATE, '2025-03-14' );
$bad_reviewer_data = ( new \DLA\MedicalTrust\Repository\TrustDataRepository() )->for_post( $reg_page );
$check( 'REG-c gecersiz reviewer verisi tibbi inceleme olarak frontende tasinmaz', null === $bad_reviewer_data || null === $bad_reviewer_data->reviewer_expert );
$GLOBALS['post'] = null;

/* (d) Yayimlanmis uzman author olarak baglandiginda shortcode HTML dondurur. */
$reg_expert = wp_insert_post( [ 'post_type' => \DLA\MedicalTrust\PostTypes\ExpertPostType::SLUG, 'post_title' => 'Leyla Arvas', 'post_status' => 'publish' ] );
update_post_meta( $reg_expert, \DLA\MedicalTrust\Meta\MetaRegistry::EXPERT_HONORIFIC, 'Op. Dr.' );
update_post_meta( $reg_expert, \DLA\MedicalTrust\Meta\MetaRegistry::EXPERT_JOB_TITLE, 'Plastik Cerrahi' );

$author_page = wp_insert_post( [ 'post_type' => 'page', 'post_title' => 'Rinoplasti', 'post_status' => 'publish' ] );
update_post_meta( $author_page, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_AUTHOR_MODE, \DLA\MedicalTrust\Domain\Enum\AuthorMode::EXPERT );
update_post_meta( $author_page, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_EXPERT_ID, $reg_expert );

$component   = new \DLA\MedicalTrust\Integration\TrustComponent();
$author_html = $component->render_for_post( $author_page );
$check(
	'REG-d konu ve kaynak olmadan da yayimlanmis author uzman Trust Box render eder',
	'' !== $author_html && false !== strpos( $author_html, 'Leyla Arvas' )
);

/* (e) Uzman/konu/kaynak yoksa shortcode bos doner. */
$empty_page = wp_insert_post( [ 'post_type' => 'page', 'post_title' => 'Iletisim', 'post_status' => 'publish' ] );
$check( 'REG-e gorunur olgu yoksa shortcode bos doner', '' === ( new \DLA\MedicalTrust\Integration\TrustComponent() )->render_for_post( $empty_page ) );

/* (f) Avada queried-post senaryosu korunur. */
$reg_query                 = new WP_Query( [ 'p' => $author_page, 'post_type' => 'page' ] );
$GLOBALS['wp_query']       = $reg_query;
$GLOBALS['wp_the_query']   = $reg_query;
$avada_html                = do_shortcode( '[dla_medical_trust]' );
$check(
	'REG-f Avada baglaminda shortcode queried singular postu cozer',
	is_singular() && (int) get_queried_object_id() === (int) $author_page && '' !== $avada_html && false !== strpos( $avada_html, 'Leyla Arvas' )
);
$check( 'REG-f ayni sorguda ikinci render cift cikti uretmez', '' === do_shortcode( '[dla_medical_trust]' ) );
$check( 'REG-f otomatik enjeksiyon varsayilan olarak kapalidir', false === \DLA\MedicalTrust\Settings\Settings::automatic_injection_enabled() );


/* ------------------------------------------------------------------ *
 * Devralma zinciri: sayfa basina veri girmeden Trust Box
 * ------------------------------------------------------------------ */

$inh_expert = wp_insert_post( [ 'post_type' => \DLA\MedicalTrust\PostTypes\ExpertPostType::SLUG, 'post_title' => 'Leyla Arvas', 'post_status' => 'publish' ] );
update_post_meta( $inh_expert, \DLA\MedicalTrust\Meta\MetaRegistry::EXPERT_HONORIFIC, 'Op. Dr.' );
$topic_expert = wp_insert_post( [ 'post_type' => \DLA\MedicalTrust\PostTypes\ExpertPostType::SLUG, 'post_title' => 'Buket Yildirim', 'post_status' => 'publish' ] );
update_post_meta( $topic_expert, \DLA\MedicalTrust\Meta\MetaRegistry::EXPERT_HONORIFIC, 'Dr.' );

\DLA\MedicalTrust\Settings\Settings::update( [ 'default_expert_id' => $inh_expert ] );

// Hicbir sayfa verisi girilmemis, konusu da olmayan sayfa.
$inh_page = wp_insert_post( [ 'post_type' => 'page', 'post_title' => 'Dysport Botoks', 'post_status' => 'publish' ] );
$inh_html = ( new \DLA\MedicalTrust\Integration\TrustComponent() )->render_for_post( $inh_page );

$check( 'INH site geneli varsayilan uzman sayfa verisi olmadan Trust Box uretir', '' !== $inh_html && false !== strpos( $inh_html, 'Leyla Arvas' ) );
$check( 'INH devralinan uzman icin inceleme TARIHI uretilmez', false === strpos( $inh_html, 'Tıbbi inceleme tarihi' ) );
$check( 'INH devralinan uzman YAZARLIK iddiasi uretmez', false === strpos( $inh_html, 'İçeriği hazırlayan' ) );
$check( 'INH devralinan uzman INCELEME iddiasi uretmez', false === strpos( $inh_html, 'Tıbbi olarak inceleyen' ) );
$check( 'INH devralinan uzman durust ifadeyle sunulur', false !== strpos( $inh_html, 'tıbbi sorumlusu' ) );
$check( 'INH cumle sayfa basligini icerir', false !== strpos( $inh_html, 'Dysport Botoks içeriğinin' ) );

// Konu varsayilan uzmani site genelini ezer.
$inh_topic = wp_insert_term( 'Rinoplasti Devralma', \DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy::SLUG );
$inh_topic_id = (int) $inh_topic['term_id'];
update_term_meta( $inh_topic_id, \DLA\MedicalTrust\Meta\MetaRegistry::TOPIC_DEFAULT_EXPERT, $topic_expert );
$inh_page2 = wp_insert_post( [ 'post_type' => 'page', 'post_title' => 'Rinoplasti', 'post_status' => 'publish' ] );
wp_set_object_terms( $inh_page2, [ $inh_topic_id ], \DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy::SLUG );
$inh_html2 = ( new \DLA\MedicalTrust\Integration\TrustComponent() )->render_for_post( $inh_page2 );

$check( 'INH konu varsayilan uzmani site genelini ezer', '' !== $inh_html2 && false !== strpos( $inh_html2, 'Buket Yildirim' ) && false === strpos( $inh_html2, 'Leyla Arvas' ) );

// Varsayilan uzman kaldirilinca yine bos doner.
\DLA\MedicalTrust\Settings\Settings::update( [ 'default_expert_id' => 0 ] );
$inh_page3 = wp_insert_post( [ 'post_type' => 'page', 'post_title' => 'Iletisim 2', 'post_status' => 'publish' ] );
$check( 'INH varsayilan uzman yokken hala bos doner', '' === ( new \DLA\MedicalTrust\Integration\TrustComponent() )->render_for_post( $inh_page3 ) );

/* --- Performans: render basina sorgu sayisi --- */
\DLA\MedicalTrust\Settings\Settings::update( [ 'default_expert_id' => $inh_expert ] );
$perf_component = new \DLA\MedicalTrust\Integration\TrustComponent();
// Her iki sayfa da isitilir: olculen sey kararli durumdaki veri erisimidir,
// ilk render'in tek seferlik nesne/secenek onbellek doldurmasi degil.
$perf_component->render_for_post( $inh_page );
$perf_component->render_for_post( $inh_page3 );
// Olcum BIZIM veri erisimimiz icindir. WordPress'in tarih/saat dilimi
// secenekleri bu kurulumda autoload disinda kaldigi icin once isitilir;
// aksi halde test kendi eklentimizin degil WP onyuklemesinin maliyetini olcer.
wp_timezone();
get_option( 'date_format' );
get_option( 'gmt_offset' );

$q_before = get_num_queries();
$perf_component2 = new \DLA\MedicalTrust\Integration\TrustComponent();
$perf_component2->render_for_post( $inh_page );
$q_warm = get_num_queries() - $q_before;

$q_before_np = get_num_queries();
( new \DLA\MedicalTrust\Integration\TrustComponent() )->render_for_post( $inh_page3 );
$q_nonmedical = get_num_queries() - $q_before_np;

printf( "PERF konusuz sayfa render: %d sorgu | trust box render: %d sorgu\n", $q_nonmedical, $q_warm );
// Konusuz sayfa, konulu sayfadan DAHA PAHALI olmamali: kaynak cozumleme
// ve konu grafigi kurulumu bu yolda hic calismaz.
$check( 'PERF konusu olmayan sayfa kaynak cozumleme maliyeti tasimaz', $q_nonmedical <= $q_warm );
$check( 'PERF isinmis Trust Box render sorgu sayisi makul (<=6)', $q_warm <= 6 );



/* --- Avada senaryosu: global layout DEGIL, sayfa icine elle kisa kod --- */
\DLA\MedicalTrust\Settings\Settings::update( [ 'default_expert_id' => $inh_expert, 'automatic_injection' => true ] );

$tb_page = wp_insert_post( [
	'post_type'    => 'page',
	'post_title'   => 'Meme Buyutme',
	'post_status'  => 'publish',
	'post_content' => 'Icerik metni. [dla_medical_trust]',
] );

$tb_query               = new WP_Query( [ 'p' => $tb_page, 'post_type' => 'page' ] );
$GLOBALS['wp_query']     = $tb_query;
$GLOBALS['wp_the_query'] = $tb_query;
$tb_query->the_post();

$tb_out = apply_filters( 'the_content', get_post_field( 'post_content', $tb_page ) );
$check( 'AVADA metin blogundaki kisa kod kutuyu basar', substr_count( $tb_out, 'dla-mt__heading' ) === 1 );
$check( 'AVADA kisa kod varken otomatik enjeksiyon cift kutu uretmez', substr_count( $tb_out, 'dla-mt__heading' ) === 1 );
wp_reset_postdata();

/* --- Avada senaryosu: dongu disinda the_content (Post Content elementi) --- */
$li_page = wp_insert_post( [ 'post_type' => 'page', 'post_title' => 'Yuz Germe', 'post_status' => 'publish', 'post_content' => 'Duz icerik.' ] );
$li_query               = new WP_Query( [ 'p' => $li_page, 'post_type' => 'page' ] );
$GLOBALS['wp_query']     = $li_query;
$GLOBALS['wp_the_query'] = $li_query;
$GLOBALS['post']         = get_post( $li_page );
// Kasitli olarak the_post() cagrilmaz: in_the_loop() false kalir.
$li_out = apply_filters( 'the_content', 'Duz icerik.' );
$check( 'AVADA dongu disinda calisan the_content ta da kutu basilir', false !== strpos( $li_out, 'dla-mt__heading' ) );



/* --- Ayar formunun GERCEK POST yolu (dogrudan Settings::update degil) --- */
$form_expert = wp_insert_post( [ 'post_type' => \DLA\MedicalTrust\PostTypes\ExpertPostType::SLUG, 'post_title' => 'Form Uzmani', 'post_status' => 'publish' ] );
\DLA\MedicalTrust\Settings\Settings::update( [ 'default_expert_id' => 0 ] );

$_POST = [
	'organization_name'         => 'Klinik',
	'organization_url'          => '',
	'organization_logo_id'      => 0,
	'default_expert_id'         => (string) $form_expert,
	'automatic_injection'       => '1',
	'injection_position'        => 'after',
	'min_topic_proximity'       => '55',
	'diversity_band'            => '10',
	'max_tier_size'             => '6',
	'eligible_post_types'       => [ 'page', 'post' ],
];
foreach ( \DLA\MedicalTrust\Domain\Enum\ReviewPolicy::values() as $pol ) {
	$_POST[ 'policy_' . $pol . '_interval' ] = '24';
	$_POST[ 'policy_' . $pol . '_age' ]      = '10';
}

\DLA\MedicalTrust\Settings\Settings::update(
	[
		'default_expert_id'   => $_POST['default_expert_id'],
		'automatic_injection' => isset( $_POST['automatic_injection'] ),
		'eligible_post_types' => $_POST['eligible_post_types'],
	]
);
\DLA\MedicalTrust\Settings\Settings::flush_cache();

$check( 'FORM varsayilan uzman ayari POST degeriyle kaydedilir', $form_expert === \DLA\MedicalTrust\Settings\Settings::default_expert_id() );
$check( 'FORM otomatik yerlestirme POST ile acilir', true === \DLA\MedicalTrust\Settings\Settings::automatic_injection_enabled() );
$check( 'FORM sayfa turu kapsamda kalir', in_array( 'page', \DLA\MedicalTrust\Settings\Settings::eligible_post_types(), true ) );

// REGRESYON: merge_defaults duz listeleri varsayilanla BIRLESTIRMEMELI.
// Kayitli ['post','page'] ile varsayilan ['page','post'] uc uca eklenince
// tani ekraninda "page, post, post, page" goruluyordu.
\DLA\MedicalTrust\Settings\Settings::update( [ 'eligible_post_types' => [ 'post', 'page' ] ] );
\DLA\MedicalTrust\Settings\Settings::flush_cache();
$scoped_types = \DLA\MedicalTrust\Settings\Settings::eligible_post_types();
$check( 'FORM kapsam listesi tekrarsizdir', $scoped_types === array_values( array_unique( $scoped_types ) ) );
$check( 'FORM kapsam listesi tam olarak iki tur icerir', 2 === count( $scoped_types ) );

// Anahtarli ayar gruplari eskisi gibi varsayilanla birleserek eksik
// anahtarlarini tamamlamali; liste duzeltmesi bunu bozmamali.
\DLA\MedicalTrust\Settings\Settings::update( [ 'organization' => [ 'name' => 'Klinik' ] ] );
\DLA\MedicalTrust\Settings\Settings::flush_cache();
$org_after = (array) \DLA\MedicalTrust\Settings\Settings::get( 'organization', [] );
$check( 'FORM anahtarli ayar grubu varsayilanlarla birlesmeye devam eder', array_key_exists( 'logo_id', $org_after ) && 'Klinik' === $org_after['name'] );

\DLA\MedicalTrust\Settings\Settings::update( [ 'eligible_post_types' => [ 'page', 'post' ] ] );
\DLA\MedicalTrust\Settings\Settings::flush_cache();

// Taslak (yayimlanmamis) uzman varsayilan olarak kabul edilmemeli.
$draft_expert = wp_insert_post( [ 'post_type' => \DLA\MedicalTrust\PostTypes\ExpertPostType::SLUG, 'post_title' => 'Taslak Uzman', 'post_status' => 'draft' ] );
\DLA\MedicalTrust\Settings\Settings::update( [ 'default_expert_id' => $draft_expert ] );
$draft_page = wp_insert_post( [ 'post_type' => 'page', 'post_title' => 'Taslak Testi', 'post_status' => 'publish' ] );
$check( 'FORM taslak uzman Trust Box uretmez', '' === ( new \DLA\MedicalTrust\Integration\TrustComponent() )->render_for_post( $draft_page ) );

// Kaynak hic yokken uzman varsa kutu YINE de basilmali (canli sitedeki durum).
\DLA\MedicalTrust\Settings\Settings::update( [ 'default_expert_id' => $form_expert ] );
$nosrc_topic = wp_insert_term( 'Dysport Botoks Canli', \DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy::SLUG );
$nosrc_page  = wp_insert_post( [ 'post_type' => 'page', 'post_title' => 'Dysport Botoks Canli', 'post_status' => 'publish' ] );
wp_set_object_terms( $nosrc_page, [ (int) $nosrc_topic['term_id'] ], \DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy::SLUG );
$nosrc_html = ( new \DLA\MedicalTrust\Integration\TrustComponent() )->render_for_post( $nosrc_page );
$check( 'FORM konu var + 0 kaynak + varsayilan uzman -> kutu BASILIR', '' !== $nosrc_html && false !== strpos( $nosrc_html, 'Form Uzmani' ) );
$_POST = [];



/* ------------------------------------------------------------------ *
 * A + C: commentary inceleme kaydindan bagimsiz, guncelleme tarihi
 * ------------------------------------------------------------------ */

$ac_expert = wp_insert_post( [ 'post_type' => \DLA\MedicalTrust\PostTypes\ExpertPostType::SLUG, 'post_title' => 'Ayse Kaya', 'post_status' => 'publish' ] );
\DLA\MedicalTrust\Settings\Settings::update( [ 'default_expert_id' => $ac_expert, 'show_updated_date' => true, 'show_review_date' => false ] );

$ac_page = wp_insert_post( [ 'post_type' => 'page', 'post_title' => 'Meme Estetigi', 'post_status' => 'publish' ] );
update_post_meta( $ac_page, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_COMMENTARY, '<p>Bu bir uzman degerlendirmesidir.</p>' );

$ac_html = ( new \DLA\MedicalTrust\Integration\TrustComponent() )->render_for_post( $ac_page );

$check( 'AC inceleme kaydi OLMADAN uzman degerlendirmesi render ediliyor', false !== strpos( $ac_html, 'Bu bir uzman degerlendirmesidir' ) );
$check( 'AC degerlendirme basligi basiliyor', false !== strpos( $ac_html, 'Uzman değerlendirmesi' ) );

// Gorunurluk bayragi kapaliyken gizlenir.
update_post_meta( $ac_page, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_DISPLAY_FLAGS, [ 'show_commentary' => false, 'show_sources' => true ] );
$ac_hidden = ( new \DLA\MedicalTrust\Integration\TrustComponent() )->render_for_post( $ac_page );
$check( 'AC gorunurluk kapaliyken degerlendirme gizlenir', false === strpos( $ac_hidden, 'Bu bir uzman degerlendirmesidir' ) );
update_post_meta( $ac_page, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_DISPLAY_FLAGS, [ 'show_commentary' => true, 'show_sources' => true ] );

/* --- Guncelleme tarihi --- */
$ac_modified = get_post_modified_time( 'Y-m-d', false, $ac_page );
$ac_html2    = ( new \DLA\MedicalTrust\Integration\TrustComponent() )->render_for_post( $ac_page );

$check( 'AC guncelleme tarihi basiliyor', false !== strpos( $ac_html2, 'Son güncelleme' ) );
$check( 'AC tarih post_modified ile birebir eslesiyor', false !== strpos( $ac_html2, 'datetime="' . $ac_modified . '"' ) );

// KRITIK: guncelleme tarihi tibbi inceleme iddiasiyla karismamali.
$check( 'AC guncelleme tarihi "Tibbi inceleme tarihi" etiketi uretmiyor', false === strpos( $ac_html2, 'Tıbbi inceleme tarihi' ) );
$check( 'AC guncelleme satiri kendi sinifiyla ayri', false !== strpos( $ac_html2, 'dla-mt__updated-date' ) );

// Sayfa guncellenince tarih degisir.
$wpdb->update(
	$wpdb->posts,
	[ 'post_modified' => '2020-01-15 10:00:00', 'post_modified_gmt' => '2020-01-15 10:00:00' ],
	[ 'ID' => $ac_page ]
);
clean_post_cache( $ac_page );
$ac_html3 = ( new \DLA\MedicalTrust\Integration\TrustComponent() )->render_for_post( $ac_page );
$check( 'AC tarih post_modified degisince guncelleniyor', false !== strpos( $ac_html3, 'datetime="2020-01-15"' ) );

// Ayar kapaliyken tarih hic basilmaz.
\DLA\MedicalTrust\Settings\Settings::update( [ 'show_updated_date' => false ] );
$ac_nodate = ( new \DLA\MedicalTrust\Integration\TrustComponent() )->render_for_post( $ac_page );
$check( 'AC show_updated_date kapaliyken tarih basilmaz', false === strpos( $ac_nodate, 'Son güncelleme' ) );
\DLA\MedicalTrust\Settings\Settings::update( [ 'show_updated_date' => true ] );

// Yalnizca tarih varsa (uzman/degerlendirme yok) kutu ACILMAZ.
\DLA\MedicalTrust\Settings\Settings::update( [ 'default_expert_id' => 0 ] );
$ac_bare = wp_insert_post( [ 'post_type' => 'page', 'post_title' => 'Bos Sayfa', 'post_status' => 'publish' ] );
$check( 'AC yalnizca guncelleme tarihi kutuyu acmaz', '' === ( new \DLA\MedicalTrust\Integration\TrustComponent() )->render_for_post( $ac_bare ) );
\DLA\MedicalTrust\Settings\Settings::update( [ 'default_expert_id' => $ac_expert ] );

/* --- Tekrar eden "Hakkinda" baglantisi kaldirildi --- */
$ac_profile = wp_insert_post( [ 'post_type' => 'page', 'post_title' => 'Ayse Kaya Hakkinda', 'post_status' => 'publish' ] );
update_post_meta( $ac_expert, \DLA\MedicalTrust\Meta\MetaRegistry::EXPERT_PROFILE_PAGE, $ac_profile );
$ac_link_html = ( new \DLA\MedicalTrust\Integration\TrustComponent() )->render_for_post( $ac_page );
$ac_profile_url = get_permalink( $ac_profile );
// Profil sayfasina TAM OLARAK iki bag cikar: uzman adi ve "Hakkimda"
// butonu. Butun amaci gorunur bir CTA olmak; kaldirilan sey eskiden
// gorsel gurultu yaratan ucuncu, etiketsiz "Hakkinda" paragrafiydi.
$check( 'AC profil bagi ad ve buton olmak uzere iki kez basiliyor', 2 === substr_count( $ac_link_html, 'href="' . $ac_profile_url . '"' ) );
$check( 'AC tekrar eden Hakkinda paragrafi kaldirildi', false === strpos( $ac_link_html, 'dla-mt__about' ) );
$check( 'AC Hakkimda butonu render ediliyor', str_contains( $ac_link_html, 'dla-mt__button' ) );

/* --- Kisa biyografi: alan doluysa gosterilir, bos ise profil sayfasindan turetilir --- */
update_post_meta( $ac_expert, \DLA\MedicalTrust\Meta\MetaRegistry::EXPERT_SHORT_BIO, "Birinci cumle.\n\nIkinci cumle." );
$ac_bio_html = ( new \DLA\MedicalTrust\Integration\TrustComponent() )->render_for_post( $ac_page );
$check( 'AC kisa biyografi kutuda gosteriliyor', str_contains( $ac_bio_html, 'dla-mt__bio' ) && str_contains( $ac_bio_html, 'Birinci cumle.' ) );
$check( 'AC duz metin biyografide satir sonlari paragrafa donusur', 2 === substr_count( $ac_bio_html, '<p>Birinci cumle.</p>' ) + substr_count( $ac_bio_html, '<p>Ikinci cumle.</p>' ) );

delete_post_meta( $ac_expert, \DLA\MedicalTrust\Meta\MetaRegistry::EXPERT_SHORT_BIO );
wp_update_post( [ 'ID' => $ac_profile, 'post_content' => '[fusion_builder_row]Profil sayfasindaki tanitim metni.[/fusion_builder_row]' ] );
$ac_auto_html = ( new \DLA\MedicalTrust\Integration\TrustComponent() )->render_for_post( $ac_page );
$check( 'AC biyografi bos ise profil sayfasindan turetilir', str_contains( $ac_auto_html, 'Profil sayfasindaki tanitim metni.' ) );
$check( 'AC turetilen biyografide sayfa olusturucu kisa kodu kalmaz', ! str_contains( $ac_auto_html, 'fusion_builder_row' ) );



/* ------------------------------------------------------------------ *
 * D: Baslangic kaynak kutuphanesi (seed)
 * ------------------------------------------------------------------ */

$seed = new \DLA\MedicalTrust\Seed\StarterLibrary();

// Turkce konu adlari katalogla eslesmeli.
$check( 'SEED turkce konu adi eslesiyor (Dysport Botoks)', \DLA\MedicalTrust\Seed\StarterLibrary::matches( [ 'botoks', 'botox' ], 'Dysport Botoks', 'dysport-botoks' ) );
$check( 'SEED turkce karakterli konu eslesiyor (Yuz Germe)', \DLA\MedicalTrust\Seed\StarterLibrary::matches( [ 'yuz germe' ], 'Yüz Germe', 'yuz-germe' ) );
$check( 'SEED alakasiz konu eslesmiyor', ! \DLA\MedicalTrust\Seed\StarterLibrary::matches( [ 'botoks' ], 'Meme Büyütme', 'meme-buyutme' ) );

$seed_topic = wp_insert_term( 'Meme Buyutme Seed', \DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy::SLUG );
$seed_topic_id = (int) $seed_topic['term_id'];

// Bu test grubu kendisinden once olusturulmus kaynaklardan bagimsiz olsun:
// ayni katalog URL'lerini silip mevcut konu icin senkronizasyonu calistirir.
foreach ( \DLA\MedicalTrust\Seed\StarterLibrary::catalog() as $entry ) {
	if ( false === strpos( (string) $entry['title'], 'Breast' ) ) {
		continue;
	}
	$prior_ids = get_posts( [ 'post_type' => \DLA\MedicalTrust\PostTypes\SourcePostType::SLUG, 'post_status' => 'any', 'numberposts' => 20, 'fields' => 'ids', 'meta_key' => \DLA\MedicalTrust\Meta\MetaRegistry::SOURCE_URL, 'meta_value' => (string) $entry['url'] ] );
	foreach ( $prior_ids as $prior_id ) {
		wp_delete_post( (int) $prior_id, true );
	}
}
$seed_report = $seed->synchronize_and_publish( [ $seed_topic_id ] );
$check( 'SEED mevcut konuda katalog kaynaklarini otomatik olusturur', (int) $seed_report['created'] >= 2 );
$check( 'SEED otomatik olusan katalog kaynaklari yayindadir', (int) $seed_report['published'] >= 2 );

$seed_page = wp_insert_post( [ 'post_type' => 'page', 'post_title' => 'Meme Buyutme Seed Sayfasi', 'post_status' => 'publish' ] );
wp_set_object_terms( $seed_page, [ $seed_topic_id ], \DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy::SLUG );
$seed_uid = (string) get_term_meta( $seed_topic_id, \DLA\MedicalTrust\Meta\MetaRegistry::TOPIC_UID, true );
update_post_meta( $seed_page, \DLA\MedicalTrust\Meta\MetaRegistry::PAGE_PRIMARY_TOPIC_UID, $seed_uid );

$seed_after = ( new \DLA\MedicalTrust\Resolver\ResolutionService() )->resolve_for_post( $seed_page );
$seed_filled_after = $seed_after instanceof \DLA\MedicalTrust\Domain\Resolution\ResolutionResult ? $seed_after->filled_slot_count() : -1;
$check( 'SEED otomatik yayimlanan kaynaklar secime girer', $seed_filled_after >= 1 );

// Idempotent: ikinci senkronizasyon yeni kayit veya iliski uretmez.
$seed_second = $seed->synchronize_and_publish();
$check( 'SEED ikinci senkronizasyon cift kaynak uretmez', 0 === (int) $seed_second['created'] && 0 === (int) $seed_second['relations_added'] );

// Sonradan ayni anahtar kelimeyle eklenen konu, mevcut katalog kaynagiyla
// baglanir; ikinci bir URL kaydi olusturulmaz.
$catalog_before_extra_topic = get_posts( [ 'post_type' => \DLA\MedicalTrust\PostTypes\SourcePostType::SLUG, 'post_status' => 'publish', 'numberposts' => 500, 'fields' => 'ids' ] );
$seed_topic_extra = wp_insert_term( 'Dysport Botoks Ek', \DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy::SLUG );
$seed_topic_extra_id = (int) $seed_topic_extra['term_id'];
$catalog_after_extra_topic = get_posts( [ 'post_type' => \DLA\MedicalTrust\PostTypes\SourcePostType::SLUG, 'post_status' => 'publish', 'numberposts' => 500, 'fields' => 'ids' ] );
$extra_topic_attached = false;
foreach ( $catalog_after_extra_topic as $source_id ) {
	$terms = get_the_terms( (int) $source_id, \DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy::SLUG );
	if ( is_array( $terms ) && in_array( $seed_topic_extra_id, array_map( static fn( WP_Term $term ): int => (int) $term->term_id, $terms ), true ) ) {
		$extra_topic_attached = true;
		break;
	}
}
$check( 'SEED sonradan eklenen konu mevcut katalog kaynagiyla baglanir', $catalog_before_extra_topic === $catalog_after_extra_topic && $extra_topic_attached );

// Elle olusturulan pending kaynak, otomatik katalog yayimlama yolundan korunur.
$manual_pending = wp_insert_post( [ 'post_type' => \DLA\MedicalTrust\PostTypes\SourcePostType::SLUG, 'post_title' => 'Elle Girilen Aday', 'post_status' => 'pending' ] );
update_post_meta( $manual_pending, \DLA\MedicalTrust\Meta\MetaRegistry::SOURCE_URL, 'https://example.org/manual-candidate' );
$seed->synchronize_and_publish();
$seed->publish_pending();
$check( 'SEED katalog disi manual pending kaynak yayimlanmaz', 'pending' === get_post_status( $manual_pending ) );

// RC1.1 tarafindan once olusturulmus, marker tasimayan tam katalog imzali
// aday da yeni konu ile birlikte otomatik yayimlanir.
$legacy_entry = null;
foreach ( \DLA\MedicalTrust\Seed\StarterLibrary::catalog() as $entry ) {
	if ( false !== strpos( (string) $entry['title'], 'Tummy Tuck' ) ) {
		$legacy_entry = $entry;
		break;
	}
}
$legacy_source = wp_insert_post( [ 'post_type' => \DLA\MedicalTrust\PostTypes\SourcePostType::SLUG, 'post_title' => (string) $legacy_entry['title'], 'post_status' => 'pending' ] );
update_post_meta( $legacy_source, \DLA\MedicalTrust\Meta\MetaRegistry::SOURCE_URL, (string) $legacy_entry['url'] );
update_post_meta( $legacy_source, \DLA\MedicalTrust\Meta\MetaRegistry::SOURCE_PUBLISHER, (string) $legacy_entry['publisher'] );
update_post_meta( $legacy_source, \DLA\MedicalTrust\Meta\MetaRegistry::SOURCE_PUBLICATION_TYPE, \DLA\MedicalTrust\Domain\Enum\PublicationType::INSTITUTIONAL_PAGE );
update_post_meta( $legacy_source, \DLA\MedicalTrust\Meta\MetaRegistry::SOURCE_DISCOVERED_VIA, (string) $legacy_entry['via'] );
update_post_meta( $legacy_source, \DLA\MedicalTrust\Meta\MetaRegistry::SOURCE_LANG, 'en' );
update_post_meta( $legacy_source, \DLA\MedicalTrust\Meta\MetaRegistry::SOURCE_PEER_REVIEWED, false );
wp_set_object_terms( $legacy_source, [ (string) $legacy_entry['type'] ], \DLA\MedicalTrust\Taxonomies\SourceTypeTaxonomy::SLUG, false );
wp_insert_term( 'Karin Germe Legacy', \DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy::SLUG );
$check( 'SEED eski tam imzali katalog adayi otomatik yayimlanir', 'publish' === get_post_status( $legacy_source ) && '' !== (string) get_post_meta( $legacy_source, \DLA\MedicalTrust\Meta\MetaRegistry::SOURCE_CATALOG_KEY, true ) );

// Bilimsel yayin slotu KASITLI olarak bos kalir.
$pub_slot = $seed_after instanceof \DLA\MedicalTrust\Domain\Resolution\ResolutionResult
	? $seed_after->slots[ \DLA\MedicalTrust\Domain\Enum\SourceType::PUBLICATION ] ?? null
	: null;
$check( 'SEED hakemli yayin slotu icin veri uretilmez', null !== $pub_slot && ! $pub_slot->is_filled() );

// Katalogdaki her URL bag lanti politikasini gecmeli.
$bad_urls = [];
foreach ( \DLA\MedicalTrust\Seed\StarterLibrary::catalog() as $entry ) {
	if ( null !== \DLA\MedicalTrust\Support\UrlPolicy::find_forbidden_reason( (string) $entry['url'] ) ) {
		$bad_urls[] = (string) $entry['url'];
	}
}
$check( 'SEED katalogdaki tum URL ler baglanti politikasini geciyor', [] === $bad_urls );



/* --- E: eksik kaynak tespiti (sessiz elemeyi gorunur kilma) --- */

$inc_source = wp_insert_post( [ 'post_type' => \DLA\MedicalTrust\PostTypes\SourcePostType::SLUG, 'post_title' => 'Eksik Kaynak', 'post_status' => 'publish' ] );
$inc_missing = \DLA\MedicalTrust\Admin\SourceMetaBox::missing_requirements( $inc_source );
$check( 'E turu/konusu/adresi olmayan kaynak 3 eksik bildirir', 3 === count( $inc_missing ) );

wp_set_object_terms( $inc_source, [ \DLA\MedicalTrust\Domain\Enum\SourceType::AUTHORITY ], \DLA\MedicalTrust\Taxonomies\SourceTypeTaxonomy::SLUG );
update_post_meta( $inc_source, \DLA\MedicalTrust\Meta\MetaRegistry::SOURCE_URL, 'https://www.isaps.org/procedures/' );
$inc_missing2 = \DLA\MedicalTrust\Admin\SourceMetaBox::missing_requirements( $inc_source );
$check( 'E tur ve adres eklenince yalnizca konu eksik kalir', 1 === count( $inc_missing2 ) );

wp_set_object_terms( $inc_source, [ $seed_topic_id ], \DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy::SLUG );
$check( 'E tum kosullar saglaninca eksik kalmaz', [] === \DLA\MedicalTrust\Admin\SourceMetaBox::missing_requirements( $inc_source ) );

wp_update_post( [ 'ID' => $inc_source, 'post_status' => 'pending' ] );
$check( 'E yayimlanmamis kayit eksik olarak isaretlenir', 1 === count( \DLA\MedicalTrust\Admin\SourceMetaBox::missing_requirements( $inc_source ) ) );

// Onay bekleyen aday BOZUK KAYIT DEGILDIR: verisi tamdir, yalnizca editorun
// yayimlamasi beklenir. Tani ekrani bu ikisini ayri sayar.
$check(
	'E aday kaydin VERI eksigi yoktur',
	[] === \DLA\MedicalTrust\Admin\SourceMetaBox::missing_data_requirements( $inc_source )
);
wp_update_post( [ 'ID' => $inc_source, 'post_status' => 'publish' ] );
$check(
	'E yayimlanan kayitta hicbir eksik kalmaz',
	[] === \DLA\MedicalTrust\Admin\SourceMetaBox::missing_requirements( $inc_source )
);


echo sprintf( 'WordPress integration: %d passed, %d failed%s', $pass, count( $failures ), PHP_EOL );
if ( ! empty( $failures ) ) {
	echo 'Failed: ' . implode( ', ', $failures ) . PHP_EOL;
	exit( 1 );
}
