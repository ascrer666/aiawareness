<?php
/**
 * Composer'sız çalışabilmesi için minimal PSR-4 autoloader.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Autoloader {

	public static function register( string $base_dir, string $prefix ): void {
		$base_dir = rtrim( $base_dir, '/\\' ) . DIRECTORY_SEPARATOR;
		$prefix   = trim( $prefix, '\\' ) . '\\';

		spl_autoload_register(
			static function ( string $class ) use ( $base_dir, $prefix ): void {
				if ( 0 !== strncmp( $prefix, $class, strlen( $prefix ) ) ) {
					return;
				}

				$relative = substr( $class, strlen( $prefix ) );
				$path     = $base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative ) . '.php';

				// Traversal koruması: çözülen yol base_dir dışına çıkamaz.
				$real = realpath( $path );
				if ( false === $real || 0 !== strncmp( $real, realpath( $base_dir ), strlen( realpath( $base_dir ) ) ) ) {
					return;
				}

				require_once $real;
			}
		);
	}
}
