<?php
/**
 * Read-only RC smoke check against an already configured local WordPress and
 * its real Polylang plugin. It never writes to the site's database.
 *
 * Run only with explicit opt-in:
 * DLA_MT_REAL_WP_POLYLANG=1 DLA_MT_WP_ROOT=C:\xampp\htdocs\drleylaarvas
 * C:\xampp\php\php.exe tests\wp-real-polylang.php
 */

declare( strict_types = 1 );

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'DLA_MT_REAL_WP_POLYLANG' ) ) {
	fwrite( STDERR, "SKIP: set DLA_MT_REAL_WP_POLYLANG=1 for the read-only real-Polylang smoke check.\n" );
	exit( 0 );
}

$wp_root = realpath( (string) getenv( 'DLA_MT_WP_ROOT' ) );
if ( false === $wp_root || ! is_file( $wp_root . DIRECTORY_SEPARATOR . 'wp-load.php' ) ) {
	fwrite( STDERR, "DLA_MT_WP_ROOT must point to a configured WordPress root.\n" );
	exit( 2 );
}

require_once $wp_root . DIRECTORY_SEPARATOR . 'wp-load.php';

if ( ! function_exists( 'pll_get_post_translations' ) ) {
	fwrite( STDERR, "Polylang is not active in this WordPress installation.\n" );
	exit( 2 );
}

define( 'DLA_MT_DIR', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
require_once DLA_MT_DIR . 'src/Support/Autoloader.php';
\DLA\MedicalTrust\Support\Autoloader::register( DLA_MT_DIR . 'src', 'DLA\\MedicalTrust' );
\DLA\MedicalTrust\I18n\Languages::reset();

$adapter = \DLA\MedicalTrust\I18n\Languages::adapter();
$posts   = get_posts(
	[
		'post_type'   => 'any',
		'post_status' => 'publish',
		'numberposts' => 1,
	]
);
$post_id = ! empty( $posts ) ? (int) $posts[0]->ID : 0;

if ( 'polylang' !== $adapter->name() || ! $adapter->is_multilingual() ) {
	fwrite( STDERR, "DLA Medical Trust did not bind to the active real Polylang adapter.\n" );
	exit( 1 );
}

printf( "adapter=%s languages=%d sample_post=%d sample_language=%s translation_members=%d\n", $adapter->name(), count( (array) pll_languages_list() ), $post_id, $post_id > 0 ? $adapter->post_language( $post_id ) : '', $post_id > 0 ? count( $adapter->post_translations( $post_id ) ) : 0 );
