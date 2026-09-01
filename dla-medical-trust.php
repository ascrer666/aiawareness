<?php
/**
 * Plugin Name:       DLA Medical Trust
 * Plugin URI:        https://www.drleylaarvas.com/
 * Description:       Tıbbi içerik sorumluluk katmanı — uzman, konu, küratörlü kaynak kütüphanesi, tıbbi inceleme kayıtları ve deterministik kaynak çözümlemesi.
 * Version:           0.6.0-rc1
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Medical Content Trust
 * Text Domain:       dla-medical-trust
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VERSION     = '0.6.0-rc1';
const DB_VERSION  = 1;
const TEXT_DOMAIN = 'dla-medical-trust';

define( 'DLA_MT_FILE', __FILE__ );
define( 'DLA_MT_DIR', plugin_dir_path( __FILE__ ) );
define( 'DLA_MT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Ortam kontrolü. Desteklenmeyen sürümlerde eklenti sessizce devre dışı kalır,
 * fatal error üretmez (v0.1 NFR-04).
 */
function environment_is_supported(): bool {
	return version_compare( PHP_VERSION, '8.0', '>=' )
		&& version_compare( get_bloginfo( 'version' ), '6.0', '>=' );
}

if ( ! environment_is_supported() ) {
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__(
					'DLA Medical Trust için PHP 8.0 ve WordPress 6.0 gerekiyor. Eklenti devre dışı bırakıldı.',
					'dla-medical-trust'
				)
			);
		}
	);
	return;
}

require_once __DIR__ . '/src/Support/Autoloader.php';
Support\Autoloader::register( __DIR__ . '/src', __NAMESPACE__ );

register_activation_hook( __FILE__, [ Activation::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ Activation::class, 'deactivate' ] );

add_action(
	'plugins_loaded',
	static function (): void {
		load_plugin_textdomain( TEXT_DOMAIN, false, dirname( plugin_basename( DLA_MT_FILE ) ) . '/languages' );
		( new Plugin() )->boot();
	}
);
