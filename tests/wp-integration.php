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
		unset( $term_id, $field );
		return 'tr';
	}
}

require_once ABSPATH . 'wp-settings.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

wp_install( 'DLA Medical Trust M2 Tests', 'm2admin', 'm2@example.test', true, '', 'not-a-production-password' );

define( 'DLA_MT_FILE', dirname( __DIR__ ) . '/dla-medical-trust.php' );
define( 'DLA_MT_DIR', dirname( __DIR__ ) . '/' );
define( 'DLA_MT_URL', 'http://example.test/wp-content/plugins/dla-medical-trust/' );
define( 'DLA\MedicalTrust\VERSION', '0.1.0-M2' );
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
$check( 'direct reviewer can write review metadata', true === $review_auth() );
wp_set_current_user( $admin_id );

/* Repository query and resolver bridge use real WP posts, terms and meta. */
$page_id = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Medical Page' ] );
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

echo sprintf( 'WordPress integration: %d passed, %d failed%s', $pass, count( $failures ), PHP_EOL );
if ( ! empty( $failures ) ) {
	echo 'Failed: ' . implode( ', ', $failures ) . PHP_EOL;
	exit( 1 );
}
