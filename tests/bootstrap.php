<?php
/**
 * PHPUnit bootstrap: WordPress's own test framework, with the plugin loaded
 * the way a must-use plugin would be.
 *
 * @package Goya24
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$goya24_tests_dir = getenv( 'WP_PHPUNIT__DIR' );
if ( ! $goya24_tests_dir ) {
	echo "wp-phpunit is not installed: run `composer install`.\n";
	exit( 1 );
}

require_once $goya24_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/goya24.php';
	}
);

require $goya24_tests_dir . '/includes/bootstrap.php';
