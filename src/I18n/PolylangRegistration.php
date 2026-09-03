<?php
/**
 * Declares the plugin's translated object types to Polylang.
 *
 * Experts and medical topics are language-specific editorial entities. Sources
 * deliberately are not: one curated source is shared through its language-
 * neutral topic UID and must not be duplicated for every site language.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\I18n;

use DLA\MedicalTrust\PostTypes\ExpertPostType;
use DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PolylangRegistration {

	public function register(): void {
		// Polylang applies these filters both when building its settings UI and
		// when constructing the translated-object model. `false` makes the
		// entries enabled and non-optional, so no manual Polylang checkbox is
		// required.
		add_filter( 'pll_get_post_types', [ $this, 'post_types' ], 10, 2 );
		add_filter( 'pll_get_taxonomies', [ $this, 'taxonomies' ], 10, 2 );
	}

	/** @param string[] $post_types @return string[] */
	public function post_types( array $post_types, bool $is_settings ): array {
		unset( $is_settings );
		$post_types[] = ExpertPostType::SLUG;

		return array_values( array_unique( $post_types ) );
	}

	/** @param string[] $taxonomies @return string[] */
	public function taxonomies( array $taxonomies, bool $is_settings ): array {
		unset( $is_settings );
		$taxonomies[] = MedicalTopicTaxonomy::SLUG;

		return array_values( array_unique( $taxonomies ) );
	}
}
