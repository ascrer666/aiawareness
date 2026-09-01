<?php
/** Plugin-owned CSS registration for the server-rendered trust component. */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AssetManager {

	public const STYLE_HANDLE = 'dla-medical-trust';

	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'register_style' ], 5 );
	}

	public function register_style(): void {
		$path    = DLA_MT_DIR . 'assets/css/dla-medical-trust.css';
		$version = is_file( $path ) ? (string) filemtime( $path ) : VERSION;
		wp_register_style( self::STYLE_HANDLE, DLA_MT_URL . 'assets/css/dla-medical-trust.css', [], $version );
	}

	public function enqueue(): void {
		if ( ! wp_style_is( self::STYLE_HANDLE, 'registered' ) ) {
			$this->register_style();
		}
		wp_enqueue_style( self::STYLE_HANDLE );
	}
}
