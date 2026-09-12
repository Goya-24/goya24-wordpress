<?php
/**
 * The plugin.
 *
 * @package Goya24
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires the parts together, once.
 */
final class Goya24 {

	/**
	 * The one instance.
	 *
	 * @var Goya24|null
	 */
	private static $instance = null;

	/**
	 * The messenger tag.
	 *
	 * @var Goya24_Frontend
	 */
	public $frontend;

	/**
	 * The connection endpoints.
	 *
	 * @var Goya24_Connect
	 */
	public $connect;

	/**
	 * The store.
	 *
	 * @var Goya24_WooCommerce
	 */
	public $woocommerce;

	/**
	 * The settings screen.
	 *
	 * @var Goya24_Admin|null
	 */
	public $admin = null;

	/**
	 * Boot, or return the booted plugin.
	 *
	 * @return Goya24
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	private function __construct() {
		register_activation_hook( GOYA24_FILE, array( __CLASS__, 'activate' ) );

		add_action( 'init', array( $this, 'load_textdomain' ) );

		$this->frontend    = new Goya24_Frontend();
		$this->connect     = new Goya24_Connect();
		$this->woocommerce = new Goya24_WooCommerce();
		if ( is_admin() ) {
			$this->admin = new Goya24_Admin();
		}
	}

	/**
	 * Translations. WordPress.org's language packs load on their own; the
	 * bundled Persian is for a site that installed the zip by hand.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'goya24', false, dirname( plugin_basename( GOYA24_FILE ) ) . '/languages' );
	}

	/**
	 * First activation: the option exists, with defaults, so the settings
	 * screen never saves against a missing row.
	 *
	 * @return void
	 */
	public static function activate() {
		add_option( Goya24_Options::OPTION, Goya24_Options::defaults() );
	}
}
