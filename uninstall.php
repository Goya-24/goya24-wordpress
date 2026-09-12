<?php
/**
 * Runs when the plugin is deleted from the plugins screen.
 *
 * Everything the plugin stored goes: the option with the key and secret,
 * the pending connection if any, and the per-person "not now" on the
 * notice. The workspace itself lives on goya24 and is untouched; the REST
 * keys WooCommerce made for it are WooCommerce's, listed under its own
 * REST API settings.
 *
 * @package Goya24
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'goya24' );
delete_transient( 'goya24_connect_state' );
delete_metadata( 'user', 0, 'goya24_dismissed_connect_notice', '', true );
