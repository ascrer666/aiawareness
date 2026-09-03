<?php
/** M5 non-deceptive readiness warnings; advisory only, never blocks publishing. */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Admin;

use DLA\MedicalTrust\Capability\Capabilities;
use DLA\MedicalTrust\Domain\Enum\ReviewStatus;
use DLA\MedicalTrust\Domain\Enum\ReviewValidity;
use DLA\MedicalTrust\Meta\MetaRegistry;
use DLA\MedicalTrust\PostTypes\ExpertPostType;
use DLA\MedicalTrust\Repository\TrustDataRepository;
use DLA\MedicalTrust\Review\ReviewService;
use DLA\MedicalTrust\Settings\Settings;
use DLA\MedicalTrust\Taxonomies\MedicalTopicTaxonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReadinessPanel {

	public function register(): void {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ], 30, 2 );
	}

	public function add_meta_box( string $post_type, $post ): void {
		unset( $post );

		if ( ! in_array( $post_type, Settings::eligible_post_types(), true ) || ! current_user_can( Capabilities::EDIT_META ) ) {
			return;
		}

		add_meta_box( 'dla-mt-readiness', __( 'Medical Trust Hazırlık Durumu', 'dla-medical-trust' ), [ $this, 'render' ], $post_type, 'side', 'default' );
	}

	public function render( \WP_Post $post ): void {
		// Kurulum eksikleri önce gelir: bunlar sayfayla değil, kütüphaneyle
		// ilgilidir ve çözülmeden diğer uyarıların anlamı yoktur.
		if ( $this->render_setup_gaps() ) {
			return;
		}

		$blocking    = [];
		$recommended = [];
		$terms       = get_the_terms( $post->ID, MedicalTopicTaxonomy::SLUG );

		if ( ! is_array( $terms ) || empty( $terms ) ) {
			$blocking[] = [ 'label' => __( 'Tıbbi konu atanmamış', 'dla-medical-trust' ) ];
		} elseif ( '' === (string) get_post_meta( $post->ID, MetaRegistry::PAGE_PRIMARY_TOPIC_UID, true ) ) {
			$blocking[] = [ 'label' => __( 'Birincil konu seçilmemiş', 'dla-medical-trust' ) ];
		}

		$status   = (string) get_post_meta( $post->ID, MetaRegistry::PAGE_REVIEW_STATUS, true );
		$validity = (string) get_post_meta( $post->ID, MetaRegistry::PAGE_REVIEW_VALIDITY, true );

		// Tıbbi inceleme tarihi gösterilmiyorsa, inceleme kaydının olmaması bir
		// bütünlük engeli DEĞİLDİR — kullanıcının hiç kullanmadığı bir özelliği
		// kırmızı hata gibi göstermek gürültüden ibaret.
		if ( ReviewStatus::REVIEWED !== $status && Settings::show_review_date() ) {
			$blocking[] = [ 'label' => __( 'Tıbbi inceleme kaydı yok', 'dla-medical-trust' ) ];
		}

		if ( ReviewValidity::SUPERSEDED === $validity ) {
			$blocking[] = [ 'label' => __( 'İçerik güncellendi, inceleme geçersizleşti', 'dla-medical-trust' ) ];
		}

		// get_post( 0 ) global post'a düşeceği için ID doğrudan kontrol edilir;
		// ayrıca yalnızca yayımlanmış bir uzman kaydı reviewer sayılır.
		$reviewer_id = (int) get_post_meta( $post->ID, MetaRegistry::PAGE_REVIEWER_EXPERT_ID, true );

		if ( ReviewStatus::REVIEWED === $status && Settings::show_review_date() && ! ExpertPostType::is_valid_published_expert( $reviewer_id ) ) {
			$blocking[] = [ 'label' => __( 'Inceleyen uzman geçersiz veya atanmamış', 'dla-medical-trust' ) ];
		}

		if ( 'due' === ( new ReviewService() )->freshness_for_post( $post->ID ) ) {
			$recommended[] = [ 'label' => __( 'Inceleme süresi doldu', 'dla-medical-trust' ) ];
		}

		$data = ( new TrustDataRepository() )->for_post( $post->ID );

		if ( null !== $data && empty( $data->sources ) ) {
			$recommended[] = [ 'label' => __( 'Uygun kaynak seçilemedi', 'dla-medical-trust' ) ];
		}

		if ( '' === trim( (string) get_post_meta( $post->ID, MetaRegistry::PAGE_COMMENTARY, true ) ) ) {
			$recommended[] = [ 'label' => __( 'Uzman değerlendirmesi yok (isteğe bağlı)', 'dla-medical-trust' ) ];
		}

		$this->list( __( 'Bütünlük engelleri', 'dla-medical-trust' ), $blocking, 'error' );
		$this->list( __( 'Tamamlanması önerilenler', 'dla-medical-trust' ), $recommended, 'warning' );

		if ( empty( $blocking ) && empty( $recommended ) ) {
			echo '<p>' . esc_html__( 'Medical Trust bilgileri hazır.', 'dla-medical-trust' ) . '</p>';
		}
	}

	/**
	 * Kütüphane boşsa sayfa bazlı uyarılar yerine kurulum yönlendirmesi
	 * gösterilir. Hiçbir uzman, konu veya reviewer OTOMATİK ATANMAZ —
	 * yalnızca doğru ekrana yönlendirilir.
	 *
	 * @return bool Kurulum eksiği gösterildiyse true.
	 */
	private function render_setup_gaps(): bool {
		$gaps = [];

		if ( 0 === $this->published_expert_count() ) {
			$gaps[] = [
				'label' => __( 'Yayımlanmış tıbbi uzman yok. Önce Medical Trust → Tıbbi Uzmanlar altında bir uzman oluşturup yayımlayın.', 'dla-medical-trust' ),
				'url'   => admin_url( 'post-new.php?post_type=' . ExpertPostType::SLUG ),
				'cta'   => __( 'Uzman ekle', 'dla-medical-trust' ),
			];
		}

		if ( 0 === $this->topic_count() ) {
			$gaps[] = [
				'label' => __( 'Tıbbi konu tanımlanmamış. Kaynak seçimi konu olmadan çalışmaz.', 'dla-medical-trust' ),
				'url'   => admin_url( 'edit-tags.php?taxonomy=' . MedicalTopicTaxonomy::SLUG . '&post_type=' . ExpertPostType::SLUG ),
				'cta'   => __( 'Konu ekle', 'dla-medical-trust' ),
			];
		}

		if ( empty( $gaps ) ) {
			return false;
		}

		$this->list( __( 'Önce kurulumu tamamlayın', 'dla-medical-trust' ), $gaps, 'error' );

		echo '<p class="description">' . esc_html__( 'Bu adımlar tamamlanana kadar sayfa düzeyi tıbbi bilgiler doldurulamaz.', 'dla-medical-trust' ) . '</p>';

		return true;
	}

	private function published_expert_count(): int {
		$experts = get_posts(
			[
				'post_type'              => ExpertPostType::SLUG,
				'post_status'            => 'publish',
				'numberposts'            => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		return count( $experts );
	}

	private function topic_count(): int {
		$terms = get_terms(
			[
				'taxonomy'   => MedicalTopicTaxonomy::SLUG,
				'hide_empty' => false,
				'number'     => 1,
				'fields'     => 'ids',
			]
		);

		return is_array( $terms ) ? count( $terms ) : 0;
	}

	/**
	 * @param array<int,array{label:string,url?:string,cta?:string}> $items
	 */
	private function list( string $title, array $items, string $type ): void {
		if ( empty( $items ) ) {
			return;
		}

		echo '<div class="notice notice-' . esc_attr( $type ) . ' inline"><p><strong>' . esc_html( $title ) . '</strong></p><ul style="list-style:disc;margin-left:18px">';

		foreach ( $items as $item ) {
			echo '<li>' . esc_html( (string) $item['label'] );

			if ( ! empty( $item['url'] ) ) {
				printf(
					' <a href="%1$s">%2$s</a>',
					esc_url( (string) $item['url'] ),
					esc_html( (string) ( $item['cta'] ?? __( 'Aç', 'dla-medical-trust' ) ) )
				);
			}

			echo '</li>';
		}

		echo '</ul></div>';
	}
}
