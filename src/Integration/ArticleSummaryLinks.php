<?php
/**
 * Opt-in external AI summary links placed before the article content.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Integration;

use DLA\MedicalTrust\Frontend\AiProviderIcons;
use DLA\MedicalTrust\Frontend\AssetManager;
use DLA\MedicalTrust\I18n\Languages;
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

			// Canli sitede gorunen yan panel rozeti statik bir deeplink; Google'in
			// interaktif publisher.js kutuphanesini yuklemiyor. Bu hedef divin
			// gercek dugmeye donusmesi icin resmi betigi yalnizca ayar acikken ve
			// ilgili makale sayfasinda bir kez ekle.
			if ( Settings::google_preferred_source_enabled() ) {
				wp_enqueue_script(
					'dla-mt-google-preferred-source',
					'https://news.google.com/swg/js/v1/publisher.js',
					[],
					null,
					[
						'in_footer' => false,
						'strategy'  => 'async',
					]
				);
			}
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

		// Bos satirla ayir. Icerigin ilk satiri ciplak bir video adresi
		// olabilir ve WordPress boyle bir adresi yalnizca SATIR BASINDA
		// gordugunde oynaticiya cevirir (WP_Embed::autoembed, oncelik 8).
		// Bileseni bosluksuz yapistirmak o satiri satir basi olmaktan
		// cikariyor, gomulu video ham baglantiya donuyordu.
		return $html . "\n\n" . $content;
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
		// Anahtar hem CSS degistiricisi hem de isaret kimligidir; etiket
		// gorunen metindir ve marka adi oldugu icin cevrilmez.
		$providers = [
			'chatgpt'    => [ 'label' => 'ChatGPT', 'url' => 'https://chatgpt.com/?q=' ],
			'grok'       => [ 'label' => 'Grok', 'url' => 'https://grok.com/?q=' ],
			'perplexity' => [ 'label' => 'Perplexity', 'url' => 'https://www.perplexity.ai/search?q=' ],
			'claude'     => [ 'label' => 'Claude', 'url' => 'https://claude.ai/new?q=' ],
			'gemini'     => [ 'label' => 'Gemini', 'url' => 'https://gemini.google.com/app?q=' ],
		];
		$preferred_source = Settings::google_preferred_source_enabled()
			? '<div class="dla-mt-preferred-source"><div google-add-preferred-source-btn></div></div>'
			: '';

		ob_start();
		?>
		<?php if ( 'above_summary' === Settings::google_preferred_source_position() ) : ?>
			<?php echo $preferred_source; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed, attribute-only Google SDK target. ?>
		<?php endif; ?>
		<aside class="dla-mt-summary-links" aria-label="<?php echo esc_attr__( 'Yapay zekâ ile makale özeti', 'dla-medical-trust' ); ?>">
			<p class="dla-mt-summary-links__title"><span aria-hidden="true">✦</span> <?php echo esc_html__( 'Bu içeriği yapay zekâ ile özetle', 'dla-medical-trust' ); ?></p>
			<div class="dla-mt-summary-links__actions">
				<?php foreach ( $providers as $slug => $provider ) : ?>
					<a class="dla-mt-summary-links__button dla-mt-summary-links__button--<?php echo esc_attr( $slug ); ?>" href="<?php echo esc_url( $provider['url'] . rawurlencode( $prompt ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo AiProviderIcons::markup( $slug ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sabit, degiskensiz SVG isaret. ?><span class="dla-mt-summary-links__label"><?php echo esc_html( $provider['label'] ); ?></span><span class="screen-reader-text"> <?php echo esc_html__( '(yeni pencerede açılır)', 'dla-medical-trust' ); ?></span></a>
				<?php endforeach; ?>
			</div>
		</aside>
		<?php if ( 'below_summary' === Settings::google_preferred_source_position() ) : ?>
			<?php echo $preferred_source; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed, attribute-only Google SDK target. ?>
		<?php endif; ?>
		<?php

		// Sablonun kendi girintisi cikti degildir; disari temiz bir
		// isaretleme birakilir.
		return trim( (string) ob_get_clean() );
	}

	private function target_post_id(): int {
		if ( ! Settings::article_summary_links_enabled() || is_feed() || is_embed() || ! is_singular() ) {
			return 0;
		}

		$post_id = (int) get_queried_object_id();
		$post    = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || ! in_array( $post->post_type, Settings::summary_links_post_types(), true ) ) {
			return 0;
		}

		if ( $this->is_excluded( $post_id ) ) {
			return 0;
		}

		return $post_id;
	}

	/**
	 * Ozet cubugu bu icerikte bastirilmis mi?
	 *
	 * Kapsamdaki "sayfa" turu yalnizca tedavi anlatan makaleleri degil, ana
	 * sayfa ve iletisim gibi metinsiz sayfalari da icerir. Ozetlenecek bir
	 * makale olmayan bu sayfalarda hem ozet cubugu hem de onunla birlikte
	 * gelen Google dugmesi anlamsizdir; ikisi ayni kapidan gecer.
	 */
	private function is_excluded( int $post_id ): bool {
		// Ana sayfa her dilde ayri bir kayittir; is_front_page() ucunu de
		// tek kuralla yakalar, ID listesine dil dil eklemek gerekmez.
		if ( Settings::summary_links_skip_front_page() && is_front_page() ) {
			return true;
		}

		$excluded = Settings::summary_links_excluded_ids();

		if ( [] === $excluded ) {
			return false;
		}

		if ( in_array( $post_id, $excluded, true ) ) {
			return true;
		}

		// Bir sayfayi haric tutmak ceviri kardeslerini de kapsar: yonetici
		// "Iletisim" sayfasini bir kez isaretler, "Contact" da kapanir.
		foreach ( Languages::adapter()->post_translations( $post_id ) as $translated_id ) {
			if ( in_array( (int) $translated_id, $excluded, true ) ) {
				return true;
			}
		}

		return false;
	}
}
