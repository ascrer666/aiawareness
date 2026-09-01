<?php
/** @package DLA\MedicalTrust */

declare( strict_types = 1 );

namespace DLA\MedicalTrust\Review;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ReviewOperationResult {

	/** @param string[] $errors */
	private function __construct( public bool $success, public string $outcome, public array $errors = [] ) {}

	public static function success( string $outcome ): self { return new self( true, $outcome ); }
	/** @param string[] $errors */
	public static function failure( array $errors ): self { return new self( false, 'rejected', $errors ); }
}
