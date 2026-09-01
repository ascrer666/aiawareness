<?php
/** Append-only, son 25 olayla sınırlı MVP inceleme geçmişi. */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Review;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ReviewLog {

	public const LIMIT = 25;

	/** @param array<int,array<string,mixed>> $existing @param array<string,mixed> $event @return array<int,array<string,mixed>> */
	public static function append( array $existing, array $event ): array {
		$log = [];
		foreach ( $existing as $item ) {
			if ( is_array( $item ) ) { $log[] = $item; }
		}
		$log[] = $event;

		return array_slice( $log, -self::LIMIT );
	}

	/** @param array<int,array<string,mixed>> $log @return array<string,mixed>|null */
	public static function latest( array $log ): ?array {
		$last = end( $log );
		return is_array( $last ) ? $last : null;
	}
}
