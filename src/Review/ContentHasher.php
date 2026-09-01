<?php
/**
 * İncelenen içerik sürümünün deterministik parmak izi.
 *
 * Yalnızca blok yorumları, sunum shortcode kabukları, HTML etiketleri ve
 * boşluk farkları normalize edilir. Uzunluk/benzerlik/semantik çıkarım yoktur.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Review;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ContentHasher {

	public static function hash( string $title, string $content ): string {
		return hash( 'sha256', self::normalize( $title ) . "\n\n" . self::normalize( $content ) );
	}

	public static function normalize( string $value ): string {
		$value = str_replace( [ "\r\n", "\r" ], "\n", $value );
		$value = preg_replace( '/<!--\s*\/?wp:[\s\S]*?-->/', '', $value ) ?? $value;
		$value = preg_replace( '/\[\/?fusion_[^\]\s]+(?:\s[^\]]*)?\]/i', '', $value ) ?? $value;
		$value = preg_replace( '/<(?:br|\/p|\/div|\/li|\/h[1-6])\b[^>]*>/i', "\n", $value ) ?? $value;
		$value = strip_tags( $value );
		$value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		return trim( preg_replace( '/\s+/u', ' ', $value ) ?? $value );
	}
}
