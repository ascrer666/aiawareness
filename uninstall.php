<?php
/**
 * Kaldırma.
 *
 * VARSAYILAN: hiçbir şey silinmez. Küratörlü bir kaynak kütüphanesini kaza
 * ile kaybetmek geri alınamaz (v0.1 §13). Silme yalnızca ayarlardan açıkça
 * istenmişse yapılır.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$dla_mt_settings = get_option( 'dla_mt_settings', [] );
$dla_mt_retain   = ! is_array( $dla_mt_settings ) || ( $dla_mt_settings['retain_data_on_uninstall'] ?? true );

if ( $dla_mt_retain ) {
	return;
}

// --- İçerik ---
// Partiler hâlinde: binlerce kayıtlı bir kütüphanede tek seferde -1
// çekmek bellek ve zaman aşımı riski taşır.
foreach ( [ 'dla_expert', 'dla_source' ] as $dla_mt_post_type ) {
	do {
		$dla_mt_ids = get_posts(
			[
				'post_type'        => $dla_mt_post_type,
				'post_status'      => 'any',
				'numberposts'      => 200,
				'fields'           => 'ids',
				'suppress_filters' => true,
			]
		);

		foreach ( $dla_mt_ids as $dla_mt_id ) {
			wp_delete_post( (int) $dla_mt_id, true );
		}
	} while ( ! empty( $dla_mt_ids ) );
}

// --- Terimler ---
foreach ( [ 'dla_medical_topic', 'dla_source_type' ] as $dla_mt_taxonomy ) {
	$dla_mt_terms = get_terms(
		[
			'taxonomy'   => $dla_mt_taxonomy,
			'hide_empty' => false,
			'fields'     => 'ids',
		]
	);

	if ( is_array( $dla_mt_terms ) ) {
		foreach ( $dla_mt_terms as $dla_mt_term_id ) {
			wp_delete_term( (int) $dla_mt_term_id, $dla_mt_taxonomy );
		}
	}
}

// --- Sayfa düzeyi meta ---
global $wpdb;

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( '_dla_' ) . '%'
	)
);

$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( '_dla_' ) . '%'
	)
);

// --- Seçenekler ---
delete_option( 'dla_mt_settings' );
delete_option( 'dla_mt_library_version' );
delete_option( 'dla_mt_db_version' );

// --- Yetenekler ---
$dla_mt_caps = [ 'dla_manage_sources', 'dla_manage_experts', 'dla_edit_medical_meta', 'dla_review_medical_content' ];

foreach ( wp_roles()->role_objects as $dla_mt_role ) {
	foreach ( $dla_mt_caps as $dla_mt_cap ) {
		$dla_mt_role->remove_cap( $dla_mt_cap );
	}
}

// Kullanıcı bazında verilmiş yetenekler rol döngüsüyle temizlenmez;
// inceleme yetkisi kasıtlı olarak kullanıcıya doğrudan atandığı için ayrıca
// dolaşılması gerekir.
$dla_mt_users = get_users(
	[
		'fields' => 'ID',
		'number' => -1,
	]
);

foreach ( $dla_mt_users as $dla_mt_user_id ) {
	$dla_mt_user = get_userdata( (int) $dla_mt_user_id );

	if ( ! $dla_mt_user instanceof WP_User ) {
		continue;
	}

	foreach ( $dla_mt_caps as $dla_mt_cap ) {
		if ( isset( $dla_mt_user->caps[ $dla_mt_cap ] ) ) {
			$dla_mt_user->remove_cap( $dla_mt_cap );
		}
	}
}
