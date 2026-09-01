<?php
/**
 * M4 default template. Themes may override it at:
 * yourtheme/dla-medical-trust/trust-block.php
 *
 * Available: $trust_data (TrustData), $display, $heading_tag.
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
$date_label = '';
if ( null !== $trust_data->review_date ) {
	$timestamp  = strtotime( $trust_data->review_date . ' UTC' );
	$date_label = false === $timestamp ? '' : wp_date( get_option( 'date_format' ), $timestamp );
}
?>
<section class="dla-mt dla-mt--<?php echo esc_attr( $display ); ?>" data-dla-mt-post="<?php echo esc_attr( (string) $trust_data->post_id ); ?>" aria-labelledby="<?php echo esc_attr( $component_id ); ?>-heading">
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
				<?php if ( '' !== (string) $trust_data->primary_expert['profile_url'] ) : ?><p class="dla-mt__about"><a href="<?php echo esc_url( (string) $trust_data->primary_expert['profile_url'] ); ?>"><?php echo esc_html__( 'Hakkında', 'dla-medical-trust' ); ?></a></p><?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="dla-mt__attribution">
			<?php if ( null !== $trust_data->reviewer_expert && null !== $trust_data->author_expert && (int) $trust_data->reviewer_expert['id'] === (int) $trust_data->author_expert['id'] ) : ?>
				<p><?php printf( esc_html__( 'İçeriği hazırlayan ve tıbbi olarak inceleyen: %s', 'dla-medical-trust' ), $person_link( $trust_data->reviewer_expert ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes name and URL. ?></p>
			<?php else : ?>
				<?php if ( null !== $trust_data->author_expert ) : ?><p><?php printf( esc_html__( 'İçeriği hazırlayan: %s', 'dla-medical-trust' ), $person_link( $trust_data->author_expert ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes name and URL. ?></p><?php endif; ?>
				<?php if ( null !== $trust_data->reviewer_expert ) : ?><p><?php printf( esc_html__( 'Tıbbi olarak inceleyen: %s', 'dla-medical-trust' ), $person_link( $trust_data->reviewer_expert ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper escapes name and URL. ?></p><?php endif; ?>
			<?php endif; ?>
			<?php if ( '' !== $date_label && $trust_data->has_valid_review() ) : ?><p class="dla-mt__review-date"><?php echo esc_html__( 'Tıbbi inceleme tarihi:', 'dla-medical-trust' ); ?> <time datetime="<?php echo esc_attr( $trust_data->review_date ); ?>"><?php echo esc_html( $date_label ); ?></time></p><?php endif; ?>
			<?php if ( 'due' === $trust_data->review_freshness ) : ?><p class="dla-mt__freshness"><?php echo esc_html__( 'Tıbbi incelemenin güncellenmesi planlanmıştır.', 'dla-medical-trust' ); ?></p><?php endif; ?>
		</div>

		<?php if ( '' !== trim( $trust_data->commentary ) ) : ?>
			<div class="dla-mt__commentary">
				<h3 class="dla-mt__subheading"><?php echo esc_html__( 'Uzman değerlendirmesi', 'dla-medical-trust' ); ?></h3>
				<div class="dla-mt__richtext"><?php echo wp_kses( $trust_data->commentary, \DLA\MedicalTrust\Support\Sanitizer::allowed_html() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- allowlist is explicit. ?></div>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $trust_data->sources ) ) : ?>
			<div class="dla-mt__sources">
				<h3 class="dla-mt__subheading"><?php echo esc_html__( 'Seçilmiş tıbbi kaynaklar', 'dla-medical-trust' ); ?></h3>
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

	<footer class="dla-mt__footer"><p><?php echo esc_html__( 'Bu bölüm, içeriğin tıbbi sorumluluk ve inceleme bilgilerini sunar; kişisel tıbbi değerlendirme yerine geçmez.', 'dla-medical-trust' ); ?></p></footer>
</section>
