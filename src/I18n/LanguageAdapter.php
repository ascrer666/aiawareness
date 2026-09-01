<?php
/**
 * Dil adaptörü sözleşmesi (v0.2 §11).
 *
 * Çekirdek algoritma hiçbir çok dil eklentisini tanımaz; yalnızca bu dar
 * arayüzü bilir. Polylang'dan WPML'e geçiş bu dosyanın altında kalır.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\I18n;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface LanguageAdapter {

	public function name(): string;

	public function is_multilingual(): bool;

	public function current_language(): string;

	public function default_language(): string;

	/**
	 * @return array<string,int> dil kodu => post ID (kendisi dahil)
	 */
	public function post_translations( int $post_id ): array;

	/**
	 * @return array<string,int> dil kodu => term ID (kendisi dahil)
	 */
	public function term_translations( int $term_id ): array;

	public function post_language( int $post_id ): string;

	public function term_language( int $term_id ): string;
}
