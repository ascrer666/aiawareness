<?php
/** ReviewService dışındaki review-meta yazılarını engeller. */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Review;

use DLA\MedicalTrust\Meta\MetaRegistry;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ReviewMetaGuard {

	private static int $depth = 0;

	public function register(): void {
		add_filter( 'add_post_metadata', [ $this, 'block' ], 10, 5 );
		add_filter( 'update_post_metadata', [ $this, 'block' ], 10, 5 );
		add_filter( 'delete_post_metadata', [ $this, 'block_delete' ], 10, 4 );
	}

	/** @param mixed $check @param mixed $value @return mixed */
	public function block( $check, int $post_id, string $key, $value, bool $unique ) {
		unset( $post_id, $value, $unique );
		return self::is_protected( $key ) && 0 === self::$depth ? false : $check;
	}

	/** @param mixed $check @param mixed $value @return mixed */
	public function block_delete( $check, int $post_id, string $key, $value ) {
		unset( $post_id, $value );
		return self::is_protected( $key ) && 0 === self::$depth ? false : $check;
	}

	public static function write( callable $operation ): void {
		++self::$depth;
		try { $operation(); } finally { --self::$depth; }
	}

	private static function is_protected( string $key ): bool {
		return in_array( $key, [ MetaRegistry::PAGE_REVIEWER_EXPERT_ID, MetaRegistry::PAGE_REVIEW_DATE, MetaRegistry::PAGE_REVIEW_STATUS, MetaRegistry::PAGE_REVIEW_VALIDITY, MetaRegistry::PAGE_RECORDED_BY_USER, MetaRegistry::PAGE_RECORDED_AT, MetaRegistry::PAGE_SIGNOFF_REFERENCE, MetaRegistry::PAGE_CONTENT_HASH, MetaRegistry::PAGE_REVIEW_LOG ], true );
	}
}
