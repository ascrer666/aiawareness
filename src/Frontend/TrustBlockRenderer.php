<?php
/**
 * Formats already-resolved TrustData. It deliberately does not read WordPress
 * metadata or select sources.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Frontend;

use DLA\MedicalTrust\Domain\TrustData;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TrustBlockRenderer {

	public function __construct( private ?AssetManager $assets = null ) {
		$this->assets ??= new AssetManager();
	}

	/** @param array<string,mixed> $args */
	public function render( TrustData $data, array $args = [] ): string {
		$display     = 'compact' === ( $args['display'] ?? '' ) ? 'compact' : 'default';
		$heading_tag = $this->heading_tag( $args['heading_level'] ?? 'h2' );
		$template    = locate_template( [ 'dla-medical-trust/trust-block.php' ] );
		if ( '' === $template ) {
			$template = DLA_MT_DIR . 'templates/trust-block.php';
		}

		$this->assets->enqueue();
		$accent     = \DLA\MedicalTrust\Settings\Settings::accent_color();
		$trust_data = $data;
		ob_start();
		require $template;

		return (string) ob_get_clean();
	}

	private function heading_tag( $value ): string {
		$value = strtolower( trim( (string) $value ) );
		return in_array( $value, [ 'h2', 'h3', 'h4', 'h5', 'h6' ], true ) ? $value : 'h2';
	}
}
