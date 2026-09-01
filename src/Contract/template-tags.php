<?php
/** Public read-only API for schema and integration consumers. */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'dla_medical_trust_get_contract' ) ) {
	/**
	 * Return canonical Medical Trust facts for an eligible medical singular post.
	 * Returns null when the post is outside the configured medical content scope.
	 *
	 * @return array<string,mixed>|null
	 */
	function dla_medical_trust_get_contract( ?int $post_id = null ): ?array {
		$post_id = $post_id ?? (int) get_queried_object_id();

		return ( new \DLA\MedicalTrust\Contract\TrustContractService() )->for_post( $post_id )?->to_array();
	}
}
