<?php
/**
 * What changes when the site is a WooCommerce store.
 *
 * @package Goya24
 */

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce: the customer's real name, and the store's orders.
 *
 * Two things a store adds. The signed-in customer is introduced by the name
 * they typed at checkout rather than a username, and the store itself can be
 * connected to the workspace so the agent looks an order up when somebody
 * asks about it. The lookup happens on goya24's side through WooCommerce's
 * REST API; this plugin only arranges the keys, and it does that through
 * WooCommerce's own authorization screen so nothing is copied by hand.
 */
final class Goya24_WooCommerce {

	/** How long the store-connect button stays valid once pressed. */
	const AUTH_TTL = 15 * MINUTE_IN_SECONDS;

	/** Where WooCommerce posts the keys it made. */
	const CALLBACK_PATH = '/api/integrations/woocommerce/callback';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'before_woocommerce_init', array( $this, 'declare_compatibility' ) );
		add_filter( 'goya24_user', array( $this, 'customer' ), 10, 2 );
	}

	/**
	 * Whether WooCommerce is running.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return class_exists( 'WooCommerce', false );
	}

	/**
	 * Orders are read through WooCommerce's REST API, never from its tables,
	 * so custom order tables change nothing here.
	 *
	 * @return void
	 */
	public function declare_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', GOYA24_FILE, true );
		}
	}

	/**
	 * Introduce the customer by the name they gave the store.
	 *
	 * @param array<string, string>|null $data The user as the tag will carry it.
	 * @param WP_User                    $user The signed-in user.
	 * @return array<string, string>|null
	 */
	public function customer( $data, $user ) {
		if ( ! is_array( $data ) || ! self::is_active() || ! class_exists( 'WC_Customer' ) ) {
			return $data;
		}
		try {
			$customer = new WC_Customer( $user->ID );
		} catch ( Exception $e ) {
			return $data;
		}

		$name = trim( $customer->get_first_name() . ' ' . $customer->get_last_name() );
		if ( '' === $name ) {
			$name = trim( $customer->get_billing_first_name() . ' ' . $customer->get_billing_last_name() );
		}
		if ( '' !== $name ) {
			$data['name'] = $name;
		}
		return $data;
	}

	/**
	 * Whether the store can be connected: the site is connected to a
	 * workspace and holds its secret, which is what signs the request.
	 *
	 * @return bool
	 */
	public static function can_connect_store() {
		return self::is_active() && Goya24_Options::is_connected() && '' !== Goya24_Options::secret();
	}

	/**
	 * A short-lived proof of which workspace is asking, bound to this site.
	 *
	 * WooCommerce passes it back untouched with the keys it makes, and
	 * goya24 checks it against the same secret before it stores anything.
	 *
	 * @param int $now Unix time, for tests.
	 * @return string
	 */
	public static function store_token( $now = 0 ) {
		$now     = $now ? (int) $now : time();
		$expires = $now + self::AUTH_TTL;
		$site    = home_url( '/' );
		$proof   = hash_hmac( 'sha256', $site . '|' . $expires, Goya24_Options::secret() );
		$encoded = rtrim( strtr( base64_encode( $site ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- URL-safe transport, not obfuscation.
		return implode( '.', array( Goya24_Options::key(), (string) $expires, $encoded, $proof ) );
	}

	/**
	 * WooCommerce's own authorization screen for goya24.
	 *
	 * The store owner approves there; WooCommerce makes a pair of REST keys,
	 * posts them to goya24, and sends the browser back to `$return_to` with
	 * `success=1`.
	 *
	 * @param string $return_to Where WooCommerce sends the browser afterwards.
	 * @return string
	 */
	public static function authorize_url( $return_to ) {
		$args = array(
			'app_name'     => 'goya24',
			// Read for orders, products and customers; write so the agent
			// can change an order's status when a teammate approves it.
			'scope'        => 'read_write',
			'user_id'      => self::store_token(),
			'return_url'   => $return_to,
			'callback_url' => Goya24_Options::origin() . self::CALLBACK_PATH,
		);
		return home_url( '/wc-auth/v1/authorize?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 ) );
	}
}
