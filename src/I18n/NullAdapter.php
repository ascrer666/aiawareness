<?php
/**
 * Çok dil eklentisi yoksa devreye giren adaptör.
 *
 * Her nesne kendi başına bir çeviri grubudur. Sistem tek dilli olarak
 * sorunsuz çalışır (v0.1 NFR-04).
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\I18n;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NullAdapter implements LanguageAdapter {

	public function name(): string {
		return 'none';
	}

	public function is_multilingual(): bool {
		return false;
	}

	public function current_language(): string {
		return $this->default_language();
	}

	public function default_language(): string {
		$locale = (string) get_locale();

		return strtolower( substr( $locale, 0, 2 ) );
	}

	public function post_translations( int $post_id ): array {
		return [ $this->default_language() => $post_id ];
	}

	public function term_translations( int $term_id ): array {
		return [ $this->default_language() => $term_id ];
	}

	public function post_language( int $post_id ): string {
		unset( $post_id );

		return $this->default_language();
	}

	public function term_language( int $term_id ): string {
		unset( $term_id );

		return $this->default_language();
	}
}
