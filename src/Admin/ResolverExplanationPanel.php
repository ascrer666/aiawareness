<?php
/**
 * Çözümleme açıklama paneli (M2.4).
 *
 * ────────────────────────────────────────────────────────────────────────
 *  SIFIR ETKİ GARANTİSİ
 *
 *  Panel `ResolutionService`'i doğrudan çağırır — `SelectionCache`'i DEĞİL.
 *  Servis yan etkisizdir: cache'e yazmaz, sayaç artırmaz, meta güncellemez.
 *  Ayrıca çözümleyicide "açıklama modu" diye ayrı bir kod yolu yoktur;
 *  karar izi her çözümlemede üretilir, panel yalnızca OKUR.
 *
 *  Sonuç: sayfa, panel açıkken de kapalıyken de aynı kaynakları çözer.
 * ────────────────────────────────────────────────────────────────────────
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Admin;

use DLA\MedicalTrust\Capability\Capabilities;
use DLA\MedicalTrust\Domain\Enum\SourceType;
use DLA\MedicalTrust\Domain\Resolution\Candidate;
use DLA\MedicalTrust\Domain\Resolution\ResolutionResult;
use DLA\MedicalTrust\Domain\Resolution\SlotResult;
use DLA\MedicalTrust\Resolver\ResolutionService;
use DLA\MedicalTrust\Resolver\SelectionCache;
use DLA\MedicalTrust\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ResolverExplanationPanel {

	public function register(): void {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ], 20, 2 );
	}

	public function add_meta_box( string $post_type, $post ): void {
		unset( $post );

		if ( ! in_array( $post_type, Settings::eligible_post_types(), true ) ) {
			return;
		}

		if ( ! current_user_can( Capabilities::EDIT_META ) ) {
			return;
		}

		add_meta_box(
			'dla-mt-resolver-explanation',
			__( 'Kaynak Çözümlemesi (tanı)', 'dla-medical-trust' ),
			[ $this, 'render' ],
			$post_type,
			'normal',
			'low'
		);
	}

	public function render( \WP_Post $post ): void {
		printf(
			'<p class="description" style="margin:0 0 12px">%s</p>',
			esc_html__( 'Yalnızca tanı amaçlıdır. Bu panel çözümleyicinin sonucunu etkilemez: cache\'e yazmaz, seçimi değiştirmez. Sayfa, panel açık olsa da olmasa da aynı kaynakları çözer.', 'dla-medical-trust' )
		);

		$result = ( new ResolutionService() )->resolve_for_post( $post->ID );

		if ( null === $result ) {
			printf(
				'<p><em>%s</em></p>',
				esc_html__( 'Bu sayfa tıbbi içerik olarak işaretlenmemiş — tıbbi konu atanmamış. Çözümleme yapılmadı.', 'dla-medical-trust' )
			);

			return;
		}

		$this->render_summary( $post->ID, $result );
		$this->render_topics( $result );

		foreach ( SourceType::values() as $slot ) {
			if ( isset( $result->slots[ $slot ] ) ) {
				$this->render_slot( $result->slots[ $slot ] );
			}
		}
	}

	private function render_summary( int $post_id, ResolutionResult $result ): void {
		$cached = ( new SelectionCache() )->peek( $post_id );

		echo '<table class="widefat striped" style="margin-bottom:16px"><tbody>';

		$this->kv( __( 'Rendezvous seed anahtarı', 'dla-medical-trust' ), $result->seed_key );
		$this->kv(
			__( 'Dolu slot', 'dla-medical-trust' ),
			sprintf( '%d / %d', $result->filled_slot_count(), count( $result->slots ) )
		);
		$this->kv( __( 'Minimum konu yakınlığı', 'dla-medical-trust' ), (string) $result->min_topic_proximity );
		$this->kv( __( 'Kütüphane sürümü', 'dla-medical-trust' ), (string) Settings::library_version() );
		$this->kv(
			__( 'Cache durumu', 'dla-medical-trust' ),
			null === $cached
				? __( 'yazılmamış veya bayat — sonraki render\'da hesaplanacak', 'dla-medical-trust' )
				: __( 'güncel', 'dla-medical-trust' )
		);

		echo '</tbody></table>';
	}

	private function render_topics( ResolutionResult $result ): void {
		printf( '<h4 style="margin:14px 0 6px">%s</h4>', esc_html__( 'Konu yakınlıkları', 'dla-medical-trust' ) );

		if ( empty( $result->proximity_map ) ) {
			printf( '<p><em>%s</em></p>', esc_html__( 'Konu bulunamadı.', 'dla-medical-trust' ) );

			return;
		}

		arsort( $result->proximity_map );

		echo '<table class="widefat striped"><thead><tr>';
		printf( '<th>%s</th>', esc_html__( 'Konu kimliği', 'dla-medical-trust' ) );
		printf( '<th>%s</th>', esc_html__( 'Basamak', 'dla-medical-trust' ) );
		printf( '<th>%s</th>', esc_html__( 'Yakınlık', 'dla-medical-trust' ) );
		printf( '<th>%s</th>', esc_html__( 'Tabanı geçti mi', 'dla-medical-trust' ) );
		echo '</tr></thead><tbody>';

		foreach ( $result->proximity_map as $uid => $score ) {
			$passes = $score >= $result->min_topic_proximity;

			printf(
				'<tr><td><code>%1$s</code></td><td>%2$s</td><td>%3$d</td><td>%4$s</td></tr>',
				esc_html( (string) $uid ),
				esc_html( $result->rung_map[ $uid ] ?? '—' ),
				(int) $score,
				$passes
					? esc_html__( 'evet', 'dla-medical-trust' )
					: '<span style="color:#8e3a44">' . esc_html__( 'hayır', 'dla-medical-trust' ) . '</span>'
			);
		}

		echo '</tbody></table>';
	}

	private function render_slot( SlotResult $slot ): void {
		printf(
			'<h4 style="margin:18px 0 6px">%s — %s</h4>',
			esc_html( SourceType::label( $slot->slot ) ),
			$slot->is_filled()
				? esc_html__( 'dolu', 'dla-medical-trust' )
				: '<span style="color:#8a6414">' . esc_html__( 'BOŞ', 'dla-medical-trust' ) . '</span>'
		);

		printf(
			'<p class="description" style="margin:0 0 6px">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: bant, 2: havuz boyutu, 3: azami havuz boyutu, 4: aday sayısı */
					__( 'Çeşitlilik bandı: %1$d · Havuz: %2$d/%3$d · Değerlendirilen aday: %4$d', 'dla-medical-trust' ),
					$slot->band,
					$slot->tier_size,
					$slot->max_tier_size,
					count( $slot->candidates )
				)
			)
		);

		if ( $slot->override_applied ) {
			printf(
				'<p class="description"><strong>%s</strong></p>',
				esc_html__( 'Sayfa düzeyi override uygulandı: skorlama ve band atlandı.', 'dla-medical-trust' )
			);
		}

		if ( null !== $slot->override_rejected_reason ) {
			printf(
				'<div class="notice notice-warning inline"><p>%s <code>%s</code></p></div>',
				esc_html__( 'Override reddedildi, otomatik çözüme düşüldü:', 'dla-medical-trust' ),
				esc_html( $slot->override_rejected_reason )
			);
		}

		if ( empty( $slot->candidates ) ) {
			printf(
				'<p><em>%s</em></p>',
				esc_html__( 'Hiç aday yok. Bu konuda bu türde yayımlanmış kaynak bulunmuyor.', 'dla-medical-trust' )
			);

			return;
		}

		if ( 1 === $slot->tier_size ) {
			printf(
				'<div class="notice notice-info inline"><p>%s</p></div>',
				esc_html__( 'Havuzda tek aday var: bu konudaki tüm sayfalar aynı kaynağı gösterecek. Çeşitlilik için bu türde yakın kalitede kaynak eklemeniz gerekir.', 'dla-medical-trust' )
			);
		}

		echo '<table class="widefat striped"><thead><tr>';
		foreach (
			[
				__( 'Kaynak', 'dla-medical-trust' ),
				__( 'Yakınlık', 'dla-medical-trust' ),
				__( 'Basamak', 'dla-medical-trust' ),
				__( 'Skor bileşenleri', 'dla-medical-trust' ),
				__( 'Skor', 'dla-medical-trust' ),
				__( 'Havuz', 'dla-medical-trust' ),
				__( 'Ağırlık', 'dla-medical-trust' ),
				__( 'Sonuç', 'dla-medical-trust' ),
			] as $heading
		) {
			printf( '<th>%s</th>', esc_html( $heading ) );
		}
		echo '</tr></thead><tbody>';

		foreach ( $slot->candidates as $candidate ) {
			$this->render_candidate_row( $candidate );
		}

		echo '</tbody></table>';
	}

	private function render_candidate_row( Candidate $candidate ): void {
		$outcome = $candidate->selected
			? '<strong style="color:#2c6b47">' . esc_html__( 'SEÇİLDİ', 'dla-medical-trust' ) . '</strong>'
			: esc_html( $this->reason_label( (string) $candidate->rejected_reason ) );

		$components = [];
		foreach ( $candidate->components as $name => $value ) {
			if ( 0 !== $value ) {
				$components[] = $name . ' ' . ( $value > 0 ? '+' : '' ) . $value;
			}
		}

		printf(
			'<tr><td><a href="%1$s">%2$s</a><br><code style="font-size:11px">%3$s</code></td>'
			. '<td>%4$d</td><td>%5$s</td><td style="font-size:11px">%6$s</td><td><strong>%7$d</strong></td>'
			. '<td>%8$s</td><td style="font-size:11px">%9$s</td><td>%10$s</td></tr>',
			esc_url( (string) get_edit_post_link( $candidate->source->id ) ),
			esc_html( $candidate->source->title ),
			esc_html( $candidate->source->uid ),
			(int) $candidate->proximity,
			esc_html( $candidate->proximity_rung ),
			esc_html( implode( ' · ', $components ) ),
			(int) $candidate->score,
			$candidate->in_tier ? esc_html__( 'evet', 'dla-medical-trust' ) : '—',
			null !== $candidate->rendezvous_weight ? esc_html( (string) $candidate->rendezvous_weight ) : '—',
			esc_html( $outcome )
		);
	}

	private function reason_label( string $reason ): string {
		$base = explode( ':', $reason )[0];

		$labels = [
			Candidate::REJECT_INELIGIBLE   => __( 'uygun değil', 'dla-medical-trust' ),
			Candidate::REJECT_PROXIMITY    => __( 'yakınlık tabanının altında', 'dla-medical-trust' ),
			Candidate::REJECT_SLOT         => __( 'slot uyuşmuyor', 'dla-medical-trust' ),
			Candidate::REJECT_OUT_OF_BAND  => __( 'bandın dışında', 'dla-medical-trust' ),
			Candidate::REJECT_TIER_TRIM    => __( 'havuz sınırında kırpıldı', 'dla-medical-trust' ),
			Candidate::REJECT_NOT_SELECTED => __( 'havuzda ama seçilmedi', 'dla-medical-trust' ),
		];

		$label = $labels[ $base ] ?? $reason;
		$extra = explode( ':', $reason )[1] ?? '';

		return '' !== $extra ? $label . ' (' . $extra . ')' : $label;
	}

	private function kv( string $key, string $value ): void {
		printf(
			'<tr><td style="width:220px"><strong>%s</strong></td><td><code>%s</code></td></tr>',
			esc_html( $key ),
			esc_html( $value )
		);
	}
}
