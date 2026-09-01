<?php
/** Shortcode, template-tag and opt-in content-injection integration. */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Integration;

use DLA\MedicalTrust\Frontend\AssetManager;
use DLA\MedicalTrust\Frontend\TrustBlockRenderer;
use DLA\MedicalTrust\Repository\TrustDataRepository;
use DLA\MedicalTrust\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TrustComponent {

	/** @var array<int,bool> */
	private array $rendered_in_query = [];
	/** @var array<int,bool> */
	private array $injected = [];

	public function __construct(
		private ?TrustDataRepository $data = null,
		private ?TrustBlockRenderer $renderer = null,
		private ?AssetManager $assets = null
	) {
		$this->data     ??= new TrustDataRepository();
		$this->assets   ??= new AssetManager();
		$this->renderer ??= new TrustBlockRenderer( $this->assets );
	}

	public function register(): void {
		$this->assets->register();
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_if_expected' ], 20 );
		add_shortcode( 'dla_medical_trust', [ $this, 'shortcode' ] );
		add_filter( 'the_content', [ $this, 'inject' ], 20 );
		add_filter( 'strip_shortcodes_tagnames', [ $this, 'strip_shortcode_tag' ] );
		add_filter( 'get_the_excerpt', [ $this, 'strip_from_excerpt' ], 20 );
	}

	/** @param array<string,string> $atts */
	public function shortcode( $atts = [] ): string {
		$post_id = $this->queried_singular_id();
		if ( 0 === $post_id || isset( $this->rendered_in_query[ $post_id ] ) ) {
			return '';
		}

		$html = $this->render_for_post( $post_id, is_array( $atts ) ? $atts : [] );
		if ( '' !== $html ) {
			$this->rendered_in_query[ $post_id ] = true;
		}

		return $html;
	}

	/** @param array<string,mixed> $args */
	public function render_for_post( int $post_id, array $args = [] ): string {
		if ( $post_id < 1 ) {
			return '';
		}
		$data = $this->data->for_post( $post_id );

		return null === $data ? '' : $this->renderer->render( $data, $args );
	}

	public function inject( string $content ): string {
		if ( ! Settings::automatic_injection_enabled() || is_feed() || is_embed() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post_id = $this->queried_singular_id();
		if ( 0 === $post_id || isset( $this->injected[ $post_id ] ) || isset( $this->rendered_in_query[ $post_id ] ) || has_shortcode( $content, 'dla_medical_trust' ) ) {
			return $content;
		}
		$html = $this->render_for_post( $post_id );
		if ( '' === $html ) {
			return $content;
		}
		$this->injected[ $post_id ]          = true;
		$this->rendered_in_query[ $post_id ] = true;

		return 'before' === Settings::injection_position() ? $html . $content : $content . $html;
	}

	/** @param string[] $tags @return string[] */
	public function strip_shortcode_tag( $tags ): array {
		$tags   = is_array( $tags ) ? $tags : [];
		$tags[] = 'dla_medical_trust';

		return array_values( array_unique( $tags ) );
	}

	public function strip_from_excerpt( string $excerpt ): string {
		$pattern = get_shortcode_regex( [ 'dla_medical_trust' ] );

		return preg_replace( '/' . $pattern . '/s', '', $excerpt ) ?? $excerpt;
	}

	public function enqueue_if_expected(): void {
		$post_id = $this->queried_singular_id();
		if ( $post_id > 0 && $this->data->is_medical_post( $post_id ) ) {
			$this->assets->enqueue();
		}
	}

	private function queried_singular_id(): int {
		if ( ! is_singular() ) {
			return 0;
		}
		$post_id = (int) get_queried_object_id();
		$post    = get_post( $post_id );

		return $post instanceof \WP_Post ? $post_id : 0;
	}
}
