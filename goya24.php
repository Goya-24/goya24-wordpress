<?php
/**
 * Plugin Name:       goya24
 * Plugin URI:        https://github.com/Goya-24/goya24-wordpress
 * Description:       The goya24 AI support messenger on your site. It answers customers in Persian and English, hands off to your team, and with WooCommerce knows who is asking and finds their order.
 * Version:           0.1.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Goya24
 * Author URI:        https://goya24.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       goya24
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   10.4
 *
 * @package Goya24
 */

defined( 'ABSPATH' ) || exit;

define( 'GOYA24_VERSION', '0.1.0' );
define( 'GOYA24_FILE', __FILE__ );
define( 'GOYA24_DIR', plugin_dir_path( __FILE__ ) );
define( 'GOYA24_URL', plugin_dir_url( __FILE__ ) );

require_once GOYA24_DIR . 'includes/class-goya24-options.php';
require_once GOYA24_DIR . 'includes/class-goya24-identity.php';
require_once GOYA24_DIR . 'includes/class-goya24-frontend.php';
require_once GOYA24_DIR . 'includes/class-goya24-connect.php';
require_once GOYA24_DIR . 'includes/class-goya24-woocommerce.php';
require_once GOYA24_DIR . 'includes/class-goya24-admin.php';
require_once GOYA24_DIR . 'includes/class-goya24.php';

Goya24::instance();
