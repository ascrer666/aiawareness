<?php
/** Theme developer template tags for the M4 component. */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'dla_medical_trust_get_html' ) ) {
	/** @param array<string,mixed> $args */
	function dla_medical_trust_get_html( array $args = [], ?int $post_id = null ): string {
		$post_id = $post_id ?? (int) get_queried_object_id();

		return ( new \DLA\MedicalTrust\Integration\TrustComponent() )->render_for_post( $post_id, $args );
	}
}

if ( ! function_exists( 'dla_medical_trust' ) ) {
	/** @param array<string,mixed> $args */
	function dla_medical_trust( array $args = [], ?int $post_id = null ): void {
		echo dla_medical_trust_get_html( $args, $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer escapes every output.
	}
}
