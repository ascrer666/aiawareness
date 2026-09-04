<?php
/**
 * Opt-in external AI summary links placed before the article content.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Integration;

use DLA\MedicalTrust\Frontend\AssetManager;
use DLA\MedicalTrust\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ArticleSummaryLinks {

	/** @var array<int,bool> */
	private array $rendered = [];

	public function __construct( private ?AssetManager $assets = null ) {
		$this->assets ??= new AssetManager();
	}

	public function register(): void {
		$this->assets->register();
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_if_expected' ], 20 );
		// Avada Global Layout'lari da bu filtreyi kullanir. Diger eklentilerin
		// donusturmesinden once ham icerigi kontrol edebilmek icin erken calis.
		add_filter( 'the_content', [ $this, 'prepend' ], 1 );
	}

	public function enqueue_if_expected(): void {
		if ( $this->target_post_id() > 0 ) {
			$this->assets->enqueue();
		}
	}

	/**
	 * The content filter runs after the theme has printed the title. It also
	 * works with Avada's Post Content element without knowing Avada internals.
	 */
	public function prepend( string $content ): string {
		$post_id = $this->target_post_id();
		if ( 0 === $post_id || isset( $this->rendered[ $post_id ] ) ) {
			return $content;
		}

		$current_id = get_the_ID();
		if ( false !== $current_id && (int) $current_id !== $post_id ) {
			return $content;
		}

		$post = get_post( $post_id );
		// Avada bir Global Layout'un header/footer icerigini islerken ana
		// sorgudaki sayfayi "current post" olarak birakabiliyor. ID tek basina
		// yeterli olmaz ve bilesen sitenin en ustune tasinir. Yalnizca
		// sorgulanan sayfanin kendi ham icerigi filtrelenirken ekleme yap.
		if ( ! $post instanceof \WP_Post || $content !== (string) $post->post_content ) {
			return $content;
		}

		$html = $this->render( $post_id );
		if ( '' === $html ) {
			return $content;
		}

		$this->rendered[ $post_id ] = true;

		return $html . $content;
	}

	public function render( int $post_id ): string {
		$post = get_post( $post_id );
		$url  = get_permalink( $post_id );
		if ( ! $post instanceof \WP_Post || ! is_string( $url ) || '' === $url ) {
			return '';
		}

		$prompt = sprintf(
			__( 'Bu makaleyi özetle ve yalnızca güvenilir kaynakları kullan: %s', 'dla-medical-trust' ),
			$url
		);
		$providers = [
			'ChatGPT'    => 'https://chatgpt.com/?q=',
			'Grok'       => 'https://grok.com/?q=',
			'Perplexity' => 'https://www.perplexity.ai/search?q=',
			'Claude'     => 'https://claude.ai/new?q=',
			'Gemini'     => 'https://gemini.google.com/app?q=',
		];

		ob_start();
		?>
		<aside class="dla-mt-summary-links" aria-label="<?php echo esc_attr__( 'Yapay zekâ ile makale özeti', 'dla-medical-trust' ); ?>">
			<p class="dla-mt-summary-links__title"><span aria-hidden="true">✦</span> <?php echo esc_html__( 'Bu içeriği yapay zekâ ile özetle', 'dla-medical-trust' ); ?></p>
			<div class="dla-mt-summary-links__actions">
				<?php foreach ( $providers as $name => $base_url ) : ?>
					<a class="dla-mt-summary-links__button" href="<?php echo esc_url( $base_url . rawurlencode( $prompt ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $name ); ?><span class="screen-reader-text"> <?php echo esc_html__( '(yeni pencerede açılır)', 'dla-medical-trust' ); ?></span></a>
				<?php endforeach; ?>
			</div>
		</aside>
		<?php

		return (string) ob_get_clean();
	}

	private function target_post_id(): int {
		if ( ! Settings::article_summary_links_enabled() || is_feed() || is_embed() || ! is_singular() ) {
			return 0;
		}

		$post_id = (int) get_queried_object_id();
		$post    = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, Settings::eligible_post_types(), true ) ) {
			return 0;
		}

		return $post_id;
	}
}
