<?php
/**
 * PHPUnit bootstrap for wp-env.
 *
 * @package UpdateWatch
 */

require_once dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

$afbuw_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $afbuw_tests_dir ) {
	$afbuw_tests_dir = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';
}
require_once $afbuw_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/afb-update-watch.php';
	}
);

require $afbuw_tests_dir . '/includes/bootstrap.php';

require_once __DIR__ . '/TestCase.php';
