<?php
/**
 * Tıbbi konu terim alanları.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Admin;

use DLA\MedicalTrust\Capability\Capabilities;
use DLA\MedicalTrust\Domain\Enum\ReviewPolicy;
use DLA\MedicalTrust\Domain\Enum\SchemaTypeHint;
use DLA\MedicalTrust\Meta\MetaRegistry;
use DLA\MedicalTrust\PostTypes\ExpertPostType;
use DLA\MedicalTrust\Settings\Settings;
use DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TopicTermFields {

	private const NONCE_ACTION = 'dla_mt_save_topic';
	private const NONCE_NAME   = 'dla_mt_topic_nonce';

	public function register(): void {
		$tax = MedicalTopicTaxonomy::SLUG;

		add_action( $tax . '_add_form_fields', [ $this, 'render_add_fields' ] );
		add_action( $tax . '_edit_form_fields', [ $this, 'render_edit_fields' ] );
		add_action( 'created_' . $tax, [ $this, 'save' ] );
		add_action( 'edited_' . $tax, [ $this, 'save' ] );
	}

	public function render_add_fields(): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		echo '<div class="form-field">';
		printf( '<label for="dla_mt_review_policy">%s</label>', esc_html__( 'İnceleme politikası', 'dla-medical-trust' ) );
		$this->policy_select( ReviewPolicy::STANDARD );
		printf( '<p>%s</p>', esc_html__( 'İçeriğin ne sıklıkla yeniden incelenmesi gerektiğini belirler.', 'dla-medical-trust' ) );
		echo '</div>';
	}

	public function render_edit_fields( \WP_Term $term ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$uid            = (string) get_term_meta( $term->term_id, MetaRegistry::TOPIC_UID, true );
		$policy         = (string) get_term_meta( $term->term_id, MetaRegistry::TOPIC_REVIEW_POLICY, true );
		$default_expert = (int) get_term_meta( $term->term_id, MetaRegistry::TOPIC_DEFAULT_EXPERT, true );
		$related        = (array) get_term_meta( $term->term_id, MetaRegistry::TOPIC_RELATED_UIDS, true );
		$hint           = (string) get_term_meta( $term->term_id, MetaRegistry::TOPIC_SCHEMA_HINT, true );
		// Meta hiç yoksa alan boş gösterilir; "0" geçerli bir geçersiz kılmadır.
		$band = metadata_exists( 'term', $term->term_id, MetaRegistry::TOPIC_DIVERSITY_BAND )
			? (string) (int) get_term_meta( $term->term_id, MetaRegistry::TOPIC_DIVERSITY_BAND, true )
			: '';
		$resolved       = Settings::policy( $policy );

		$this->row(
			'dla_mt_review_policy',
			__( 'İnceleme politikası', 'dla-medical-trust' ),
			function () use ( $policy ): void {
				$this->policy_select( $policy );
			},
			sprintf(
				/* translators: 1: ay sayısı, 2: yıl sayısı. */
				__( 'Şu an geçerli: %1$d ay inceleme aralığı, %2$d yıl kaynak yaş sınırı.', 'dla-medical-trust' ),
				$resolved['interval_months'],
				$resolved['max_source_age_years']
			)
		);

		$this->row(
			'dla_mt_default_expert',
			__( 'Varsayılan uzman', 'dla-medical-trust' ),
			function () use ( $default_expert ): void {
				$experts = get_posts(
					[
						'post_type'              => ExpertPostType::SLUG,
						'post_status'            => [ 'publish', 'draft' ],
						'numberposts'            => 100,
						'orderby'                => 'title',
						'order'                  => 'ASC',
						'update_post_meta_cache' => false,
						'update_post_term_cache' => false,
					]
				);

				echo '<select id="dla_mt_default_expert" name="dla_mt_default_expert">';
				printf(
					'<option value=""%s>%s</option>',
					selected( $default_expert, 0, false ),
					esc_html__( '— seçilmedi —', 'dla-medical-trust' )
				);

				foreach ( $experts as $expert ) {
					printf(
						'<option value="%1$d"%2$s>%3$s</option>',
						(int) $expert->ID,
						selected( $default_expert, $expert->ID, false ),
						esc_html( $expert->post_title )
					);
				}

				echo '</select>';
			},
			__( 'Sayfada uzman seçilmemişse devreye girer.', 'dla-medical-trust' )
		);

		$this->row(
			'dla_mt_schema_hint',
			__( 'Schema tip ipucu', 'dla-medical-trust' ),
			static function () use ( $hint ): void {
				echo '<select id="dla_mt_schema_hint" name="dla_mt_schema_hint">';
				printf(
					'<option value=""%s>%s</option>',
					selected( $hint, '', false ),
					esc_html__( '— belirtilmedi —', 'dla-medical-trust' )
				);

				foreach ( SchemaTypeHint::labels() as $value => $label ) {
					printf(
						'<option value="%1$s"%2$s>%3$s</option>',
						esc_attr( $value ),
						selected( $hint, $value, false ),
						esc_html( $label )
					);
				}

				echo '</select>';
			},
			__( 'Bu eklenti kullanmaz ve JSON-LD üretmez; yalnızca saklar ve kontrat üzerinden schema eklentisine sunar.', 'dla-medical-trust' )
		);

		$this->row(
			'dla_mt_related_uids',
			__( 'İlişkili konu kimlikleri', 'dla-medical-trust' ),
			static function () use ( $related ): void {
				printf(
					'<textarea id="dla_mt_related_uids" name="dla_mt_related_uids" rows="3" class="large-text code">%s</textarea>',
					esc_textarea( implode( "\n", array_map( 'strval', $related ) ) )
				);
			},
			__( 'Her satıra bir konu kimliği (top_xxxxxxxxxxxx). Küratörlü ilişki, otomatik üst konu yürüyüşünden daha yüksek yakınlık puanı alır.', 'dla-medical-trust' )
		);

		$this->row(
			'dla_mt_diversity_band',
			__( 'Çeşitlilik bandı', 'dla-medical-trust' ),
			static function () use ( $band ): void {
				printf(
					'<input type="number" id="dla_mt_diversity_band" name="dla_mt_diversity_band" value="%s" min="0" max="200" step="1" class="small-text">',
					esc_attr( $band )
				);
			},
			sprintf(
				/* translators: %d: global varsayılan bant. */
				__( 'Boş bırakılırsa global ayar devralınır (şu an %d). Kaynak havuzu geniş konularda yükseltmek çeşitliliği artırır.', 'dla-medical-trust' ),
				(int) Settings::get( 'diversity_band', 10 )
			)
		);

		$this->row(
			'dla_mt_topic_uid_display',
			__( 'Konu kimliği', 'dla-medical-trust' ),
			static function () use ( $uid ): void {
				printf( '<code>%s</code>', esc_html( '' !== $uid ? $uid : __( 'üretilmedi', 'dla-medical-trust' ) ) );
			},
			__( 'Dil-nötr kalıcı kimlik. Kaynak çözümlemesi term_id yerine bunu kullanır.', 'dla-medical-trust' )
		);
	}

	private function policy_select( string $selected ): void {
		echo '<select id="dla_mt_review_policy" name="dla_mt_review_policy">';

		foreach ( ReviewPolicy::labels() as $value => $label ) {
			$resolved = Settings::policy( $value );

			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $selected, $value, false ),
				esc_html(
					sprintf(
						/* translators: 1: politika adı, 2: ay sayısı. */
						__( '%1$s — %2$d ay', 'dla-medical-trust' ),
						$label,
						$resolved['interval_months']
					)
				)
			);
		}

		echo '</select>';
	}

	private function row( string $id, string $label, callable $render, string $description ): void {
		printf(
			'<tr class="form-field"><th scope="row"><label for="%1$s">%2$s</label></th><td>',
			esc_attr( $id ),
			esc_html( $label )
		);

		$render();

		printf( '<p class="description">%s</p></td></tr>', esc_html( $description ) );
	}

	public function save( int $term_id ): void {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_NAME ] ) );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( Capabilities::MANAGE_SOURCES ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- yukarıda doğrulandı.
		$map = [
			MetaRegistry::TOPIC_REVIEW_POLICY  => 'dla_mt_review_policy',
			MetaRegistry::TOPIC_DEFAULT_EXPERT => 'dla_mt_default_expert',
			MetaRegistry::TOPIC_SCHEMA_HINT    => 'dla_mt_schema_hint',
			MetaRegistry::TOPIC_RELATED_UIDS   => 'dla_mt_related_uids',
		];

		foreach ( $map as $meta_key => $field ) {
			if ( ! isset( $_POST[ $field ] ) ) {
				continue;
			}

			update_term_meta( $term_id, $meta_key, wp_unslash( $_POST[ $field ] ) );
		}

		// Çeşitlilik bandı: devralma "boş dize" ile değil, meta'nın HİÇ
		// BULUNMAMASI ile ifade edilir. Boş bırakılırsa kayıt silinir.
		if ( isset( $_POST['dla_mt_diversity_band'] ) ) {
			$raw = trim( (string) wp_unslash( $_POST['dla_mt_diversity_band'] ) );

			if ( '' === $raw ) {
				delete_term_meta( $term_id, MetaRegistry::TOPIC_DIVERSITY_BAND );
			} else {
				update_term_meta( $term_id, MetaRegistry::TOPIC_DIVERSITY_BAND, (int) $raw );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
