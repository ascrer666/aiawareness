<?php
/**
 * Immutable, scalar-only export value for Medical Trust contract consumers.
 *
 * The public API returns a fresh PHP array, so callers cannot mutate internal
 * resolver state or WordPress objects.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Contract;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TrustContract {

	/** @var array<string,mixed> */
	private array $payload;

	/** @param array<string,mixed> $payload */
	public function __construct( array $payload ) {
		$this->payload = self::copy_value( $payload );
	}

	/** @return array<string,mixed> */
	public function to_array(): array {
		return self::copy_value( $this->payload );
	}

	/** @param array<string,mixed>|array<int,mixed> $value */
	private static function copy_value( array $value ): array {
		$out = [];
		foreach ( $value as $key => $item ) {
			if ( is_array( $item ) ) {
				$out[ $key ] = self::copy_value( $item );
				continue;
			}

			// The contract is deliberately scalar-only. Its builder never passes
			// objects, resources, or callbacks across this public boundary.
			$out[ $key ] = is_scalar( $item ) || null === $item ? $item : null;
		}

		return $out;
	}
}
