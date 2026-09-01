<?php
/**
 * Saf M1 + M2 birim test koşumu.
 *
 * @package DLA\MedicalTrust
 */

declare( strict_types = 1 );

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/harness.php';
require_once __DIR__ . '/unit-m1.php';
require_once __DIR__ . '/unit-m2.php';
require_once __DIR__ . '/unit-m3.php';

echo PHP_EOL . str_repeat( '─', 60 ) . PHP_EOL;
printf( 'Toplam: %d   Geçen: %d   Başarısız: %d%s', T::$pass + T::$fail, T::$pass, T::$fail, PHP_EOL );

if ( T::$fail > 0 ) {
	echo PHP_EOL . 'Başarısız testler:' . PHP_EOL;
	foreach ( T::$failures as $failure ) {
		echo '  · ' . $failure . PHP_EOL;
	}

	exit( 1 );
}

exit( 0 );
