<?php
/**
 * WordPress'siz test önyüklemesi.
 *
 * M1'deki saf (WordPress bağımsız) sınıfları çalıştırabilmek için asgari
 * çekirdek fonksiyon taklitleri. Bunlar gerçek WordPress davranışının birebir
 * kopyası DEĞİLDİR; yalnızca saf mantığın izole edilebilmesini sağlar.
 *
 * Gerçek entegrasyon testleri WP test suite gerektirir ve M2'ye aittir.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

define( 'ABSPATH', __DIR__ . '/' );

require_once dirname( __DIR__ ) . '/src/Support/Autoloader.php';
DLA\MedicalTrust\Support\Autoloader::register( dirname( __DIR__ ) . '/src', 'DLA\\MedicalTrust' );

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		unset( $domain );

		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		unset( $domain );

		return $text;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( string $type, $gmt = 0 ) {
		unset( $gmt );

		return gmdate( $type );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ): int {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $value ): string {
		$value = wp_strip_all_tags_stub( $value );

		return trim( preg_replace( '/[\r\n\t ]+/', ' ', $value ) ?? '' );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( string $value ): string {
		return trim( wp_strip_all_tags_stub( $value ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $value ): string {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) ) ?? '';
	}
}

function wp_strip_all_tags_stub( string $value ): string {
	return strip_tags( $value );
}

if ( ! function_exists( 'wp_kses' ) ) {
	function wp_kses( string $value, array $allowed ): string {
		$tags = '';
		foreach ( array_keys( $allowed ) as $tag ) {
			$tags .= '<' . $tag . '>';
		}

		return strip_tags( $value, $tags );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( string $url, array $protocols = [] ): string {
		$scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );

		if ( ! empty( $protocols ) && ! in_array( $scheme, $protocols, true ) ) {
			return '';
		}

		return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : '';
	}
}

if ( ! function_exists( 'wp_http_validate_url' ) ) {
	function wp_http_validate_url( string $url ) {
		$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );

		$blocked = [ 'localhost', '127.0.0.1', '0.0.0.0', '::1' ];
		if ( in_array( $host, $blocked, true ) || str_starts_with( $host, '192.168.' ) || str_starts_with( $host, '10.' ) ) {
			return false;
		}

		return $url;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, $value, ...$args ) {
		unset( $hook, $args );

		return $value;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $name, $default = false ) {
		unset( $name );

		return $default;
	}
}
