<?php
/**
 * The WordPress the tests run in.
 *
 * WordPress core comes from `roots/wordpress-no-content` in vendor; the test
 * library from `wp-phpunit/wp-phpunit`. The database is whatever the
 * environment names — a throwaway one: the suite empties it on every run.
 *
 * @package Goya24
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/vendor/roots/wordpress-no-content/' );

/**
 * An environment variable, or the default when it is unset or blank.
 *
 * @param string $name    Variable name.
 * @param string $fallback What to use without it.
 * @return string
 */
function goya24_tests_env( $name, $fallback ) {
	$value = getenv( $name );
	return ( false === $value || '' === $value ) ? $fallback : $value;
}

define( 'DB_NAME', goya24_tests_env( 'WP_TESTS_DB_NAME', 'wordpress_test' ) );
define( 'DB_USER', goya24_tests_env( 'WP_TESTS_DB_USER', 'root' ) );
define( 'DB_PASSWORD', goya24_tests_env( 'WP_TESTS_DB_PASSWORD', 'root' ) );
define( 'DB_HOST', goya24_tests_env( 'WP_TESTS_DB_HOST', '127.0.0.1' ) );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

// The plugin is loaded by tests/bootstrap.php, not from a plugins folder;
// this only has to be somewhere WordPress may write uploads.
define( 'WP_CONTENT_DIR', goya24_tests_env( 'WP_TESTS_CONTENT_DIR', __DIR__ . '/wp-content' ) );

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WP_DEBUG', true );
define( 'WPLANG', '' );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
