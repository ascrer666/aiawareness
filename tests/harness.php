<?php
/**
 * Asgari test kosumu (PHPUnit gerektirmez).
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

final class T {
	public static int $pass = 0;
	public static int $fail = 0;
	/** @var string[] */
	public static array $failures = [];
	private static string $group = '';

	public static function group( string $name ): void {
		self::$group = $name;
		echo PHP_EOL . '── ' . $name . PHP_EOL;
	}

	public static function is( string $what, $actual, $expected ): void {
		if ( $actual === $expected ) {
			self::$pass++;
			echo '  ok   ' . $what . PHP_EOL;

			return;
		}

		self::$fail++;
		$msg = sprintf(
			'%s / %s — beklenen: %s, gelen: %s',
			self::$group,
			$what,
			var_export( $expected, true ),
			var_export( $actual, true )
		);
		self::$failures[] = $msg;
		echo '  FAIL ' . $what . '  → beklenen ' . var_export( $expected, true )
			. ', gelen ' . var_export( $actual, true ) . PHP_EOL;
	}

	public static function true( string $what, bool $actual ): void {
		self::is( $what, $actual, true );
	}
}
