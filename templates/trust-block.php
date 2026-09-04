<?php
/**
 * M4 default template. Themes may override it at:
 * yourtheme/dla-medical-trust/trust-block.php
 *
 * Available: $trust_data (TrustData), $display, $heading_tag, $accent.
 *
 * @package DLA\MedicalTrust
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$component_id = 'dla-mt-' . $trust_data->post_id;
$person_link  = static function ( array $person, string $class = '' ): string {
	$name = esc_html( (string) $person['name'] );
	$url  = (string) ( $person['profile_url'] ?? '' );

	return '' === $url
		? sprintf( '<span class="%1$s">%2$s</span>', esc_attr( $class ), $name )
		: sprintf( '<a class="%1$s" href="%2$s">%3$s</a>', esc_attr( $class ), esc_url( $url ), $name );
};
$format     = 'F Y';
$to_label   = static function ( ?string $iso ) use ( $format ): string {
	if ( null === $iso || '' === $iso ) {
		return '';
	}
	$timestamp = strtotime( $iso . ' UTC' );

	return false === $timestamp ? '' : wp_date( $format, $timestamp );
};
$date_label    = $to_label( $trust_data->review_date );
$updated_label = $to_label( $trust_data->updated_date );
$board_page_id = \DLA\MedicalTrust\Settings\Settings::editorial_board_page_id();
if ( $board_page_id > 0 ) {
	$language    = \DLA\MedicalTrust\I18n\Languages::adapter()->post_language( $trust_data->post_id );
	$translations = \DLA\MedicalTrust\I18n\Languages::adapter()->post_translations( $board_page_id );
	$board_page_id = (int) ( $translations[ $language ] ?? $board_page_id );
}
$board_page = $board_page_id > 0 ? get_post( $board_page_id ) : null;
?>
<section class="dla-mt dla-mt--<?php echo esc_attr( $display ); ?>"<?php if ( isset( $accent ) && '' !== $accent ) : ?> style="--dla-mt-accent: <?php echo esc_attr( $accent ); ?>"<?php endif; ?> data-dla-mt-post="<?php echo esc_attr( (string) $trust_data->post_id ); ?>" aria-labelledby="<?php echo esc_attr( $component_id ); ?>-heading">
	<header class="dla-mt__header">
		<?php printf( '<%1$s id="%2$s-heading" class="dla-mt__heading">%3$s</%1$s>', esc_attr( $heading_tag ), esc_attr( $component_id ), esc_html__( 'İçerik Sorumlusu ve Tıbbi Denetim', 'dla-medical-trust' ) ); ?>
	</header>

	<?php if ( 'default' === $display && null !== $trust_data->primary_expert && (int) $trust_data->primary_expert['image_id'] > 0 ) : ?>
		<figure class="dla-mt__portrait">
			<?php echo wp_get_attachment_image( (int) $trust_data->primary_expert['image_id'], 'medium', false, [ 'class' => 'dla-mt__portrait-image', 'alt' => (string) $trust_data->primary_expert['name'], 'loading' => 'lazy' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress image API returns escaped responsive markup. ?>
		</figure>
	<?php endif; ?>

	<div class="dla-mt__body">
		<?php if ( null !== $trust_data->primary_expert ) : ?>
			<div class="dla-mt__expert">
				<p class="dla-mt__eyebrow"><?php echo esc_html__( 'Tıbbi uzman', 'dla-medical-trust' ); ?></p>
				<p class="dla-mt__name"><?php echo $person_link( $trust_data->primary_expert, 'dla-mt__profile-link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes name and URL. ?></p>
				<?php if ( '' !== (string) $trust_data->primary_expert['specialty'] ) : ?><p class="dla-mt__specialty"><?php echo esc_html( (string) $trust_data->primary_expert['specialty'] ); ?></p><?php endif; ?>

				<?php
				$expert_bio     = (string) ( $trust_data->primary_expert['bio'] ?? '' );
				$expert_profile = (string) ( $trust_data->primary_expert['profile_url'] ?? '' );
				?>
				<?php if ( 'default' === $display && '' !== $expert_bio ) : ?>
					<div class="dla-mt__bio dla-mt__richtext"><?php echo wp_kses( $expert_bio, \DLA\MedicalTrust\Support\Sanitizer::allowed_html() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- allowlist is explicit. ?></div>
				<?php endif; ?>

				<?php if ( 'default' === $display && '' !== $expert_profile ) : ?>
					<p class="dla-mt__cta"><a class="dla-mt__button" href="<?php echo esc_url( $expert_profile ); ?>"><?php echo esc_html__( 'Hakkımda', 'dla-medical-trust' ); ?></a></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="dla-mt__attribution">
			<?php if ( null !== $trust_data->reviewer_expert && null !== $trust_data->author_expert && (int) $trust_data->reviewer_expert['id'] === (int) $trust_data->author_expert['id'] ) : ?>
				<p><?php printf( esc_html__( 'İçeriği hazırlayan ve tıbbi olarak inceleyen: %s', 'dla-medical-trust' ), $person_link( $trust_data->reviewer_expert ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes name and URL. ?></p>
			<?php else : ?>
				<?php if ( null !== $trust_data->author_expert ) : ?><p><?php printf( esc_html__( 'İçeriği hazırlayan: %s', 'dla-medical-trust' ), $person_link( $trust_data->author_expert ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes name and URL. ?></p><?php endif; ?>
				<?php if ( null !== $trust_data->reviewer_expert ) : ?><p><?php printf( esc_html__( 'Tıbbi olarak inceleyen: %s', 'dla-medical-trust' ), $person_link( $trust_data->reviewer_expert ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes name and URL. ?></p><?php endif; ?>
			<?php endif; ?>
			<?php if ( $trust_data->primary_expert_is_inherited() && null !== $trust_data->primary_expert ) : ?>
				<?php
				/*
				 * Devralınan sorumlu: yazarlık veya inceleme İDDİA EDİLMEZ.
				 * Sayfa başlığı yalnızca cümleyi somutlaştırmak için kullanılır.
				 */
				$page_label = trim( wp_strip_all_tags( get_the_title( $trust_data->post_id ) ) );
				?>
				<p>
				<?php
				if ( '' !== $page_label ) {
					printf(
						/* translators: 1: sayfa başlığı, 2: uzman adı. */
						esc_html__( '%1$s içeriğinin tıbbi sorumlusu: %2$s', 'dla-medical-trust' ),
						esc_html( $page_label ),
						esc_html( (string) $trust_data->primary_expert['name'] )
					);
				} else {
					printf(
						esc_html__( 'Bu sayfanın tıbbi içerik sorumlusu: %s', 'dla-medical-trust' ),
						esc_html( (string) $trust_data->primary_expert['name'] )
					);
				}
				?>
				</p>
			<?php endif; ?>
			<?php if ( '' !== $date_label && $trust_data->has_valid_review() ) : ?><p class="dla-mt__review-date"><?php echo esc_html__( 'Tıbbi inceleme tarihi:', 'dla-medical-trust' ); ?> <time datetime="<?php echo esc_attr( $trust_data->review_date ); ?>"><?php echo esc_html( $date_label ); ?></time></p><?php endif; ?>
			<?php if ( 'due' === $trust_data->review_freshness ) : ?><p class="dla-mt__freshness"><?php echo esc_html__( 'Tıbbi incelemenin güncellenmesi planlanmıştır.', 'dla-medical-trust' ); ?></p><?php endif; ?>
			<?php
			/*
			 * İçerik güncelleme tarihi — KENDİ etiketiyle, ayrı satırda.
			 * "Tıbbi inceleme" ifadesiyle asla birlikte kullanılmaz; bu tarih
			 * bir inceleme iddiası taşımaz, yalnızca sayfanın ne zaman
			 * güncellendiğini bildirir.
			 */
			?>
			<?php if ( '' !== $updated_label ) : ?><p class="dla-mt__updated-date"><?php echo esc_html__( 'Son güncelleme:', 'dla-medical-trust' ); ?> <time datetime="<?php echo esc_attr( (string) $trust_data->updated_date ); ?>"><?php echo esc_html( $updated_label ); ?></time></p><?php endif; ?>
		</div>

		<?php if ( '' !== trim( $trust_data->commentary ) ) : ?>
			<div class="dla-mt__commentary">
				<h3 class="dla-mt__subheading"><?php echo esc_html__( 'Uzman değerlendirmesi', 'dla-medical-trust' ); ?></h3>
				<div class="dla-mt__richtext"><?php echo wp_kses( $trust_data->commentary, \DLA\MedicalTrust\Support\Sanitizer::allowed_html() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- allowlist is explicit. ?></div>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $trust_data->sources ) ) : ?>
			<div class="dla-mt__sources">
				<h3 class="dla-mt__subheading"><?php echo esc_html__( 'Kaynaklar', 'dla-medical-trust' ); ?></h3>
				<ol class="dla-mt__source-list">
					<?php foreach ( $trust_data->sources as $source ) : ?>
						<li class="dla-mt__source">
							<span class="dla-mt__source-type"><?php echo esc_html( \DLA\MedicalTrust\Domain\Enum\SourceType::label( (string) $source['type'] ) ); ?></span>
							<cite><a href="<?php echo esc_url( (string) $source['url'] ); ?>" rel="noopener"><?php echo esc_html( (string) $source['title'] ); ?></a></cite>
							<?php $citation = array_filter( [ (string) $source['publisher'], (string) $source['journal'], (int) $source['year'] > 0 ? (string) $source['year'] : '' ] ); if ( ! empty( $citation ) ) : ?><span class="dla-mt__citation-meta"><?php echo esc_html( implode( ' · ', $citation ) ); ?></span><?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ol>
			</div>
		<?php endif; ?>
	</div>

</section>

<?php if ( $board_page instanceof \WP_Post && 'publish' === $board_page->post_status ) : ?>
	<aside class="dla-mt-editorial-note" aria-label="<?php echo esc_attr__( 'Editoryal bilgilendirme', 'dla-medical-trust' ); ?>">
		<p><?php printf( esc_html__( 'Bu içeriğin geliştirilmesine %s katkı sağlamıştır. Sayfa içeriği sadece bilgilendirme amaçlıdır. Tanı ve tedavi için mutlaka hekiminize başvurunuz.', 'dla-medical-trust' ), sprintf( '<a href="%1$s">%2$s</a>', esc_url( (string) get_permalink( $board_page_id ) ), esc_html( (string) $board_page->post_title ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- link URL and title are escaped. ?></p>
	</aside>
<?php endif; ?>
