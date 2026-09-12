<?php
/**
 * Connecting the site to a workspace without copying anything by hand.
 *
 * @package Goya24
 */

defined( 'ABSPATH' ) || exit;

/**
 * The two halves of the connection.
 *
 * The admin presses one button and lands on goya24, signed in, choosing a
 * workspace. goya24 then posts the workspace's key and identity secret to
 * this site's REST endpoint — server to server, never through the browser's
 * address bar — and sends the admin back here. The one-time `state` made
 * when the button was pressed is what lets the endpoint accept that post
 * and nothing else. It is the same shape as WooCommerce's own "connect an
 * app" flow, and for the same reasons.
 */
final class Goya24_Connect {

	/** REST namespace. */
	const REST_NAMESPACE = 'goya24/v1';

	/** Where the pending connection's state waits. */
	const STATE_TRANSIENT = 'goya24_connect_state';

	/** How long a pressed button stays valid. */
	const STATE_TTL = 15 * MINUTE_IN_SECONDS;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Where the button sends the admin.
	 *
	 * @param string $return_to The admin page to come back to.
	 * @return string
	 */
	public static function start_url( $return_to ) {
		$state = wp_generate_password( 32, false );
		set_transient( self::STATE_TRANSIENT, $state, self::STATE_TTL );

		$locale = 0 === strpos( (string) get_user_locale(), 'fa' ) ? 'fa' : 'en';
		$args   = array(
			'site'        => home_url( '/' ),
			'name'        => get_bloginfo( 'name' ),
			'callback'    => rest_url( self::REST_NAMESPACE . '/connect' ),
			'return'      => $return_to,
			'state'       => $state,
			'plugin'      => GOYA24_VERSION,
			'woocommerce' => Goya24_WooCommerce::is_active() ? '1' : '0',
		);

		return Goya24_Options::origin() . '/' . $locale . '/connect/wordpress?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
	}

	/**
	 * Register the endpoints.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/connect',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'receive' ),
				// Unauthenticated on purpose: goya24's server calls this,
				// not a signed-in user. The one-time state made by
				// start_url() is the credential, and it is checked first.
				'permission_callback' => '__return_true',
				'args'                => array(
					'state'     => array(
						'required' => true,
						'type'     => 'string',
					),
					'key'       => array(
						'required' => true,
						'type'     => 'string',
					),
					'secret'    => array(
						'required' => false,
						'type'     => 'string',
					),
					'workspace' => array(
						'required' => false,
						'type'     => 'object',
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'status' ),
				// Nothing here that the page's own tag does not already show.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * What goya24 posts once the admin has chosen a workspace.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function receive( WP_REST_Request $request ) {
		$expected = get_transient( self::STATE_TRANSIENT );
		$state    = (string) $request->get_param( 'state' );
		if ( ! is_string( $expected ) || '' === $expected || ! hash_equals( $expected, $state ) ) {
			return new WP_Error(
				'goya24_unknown_state',
				__( 'This connection request is unknown or has expired. Start again from the plugin settings.', 'goya24' ),
				array( 'status' => 403 )
			);
		}

		$key = trim( sanitize_text_field( (string) $request->get_param( 'key' ) ) );
		if ( ! Goya24_Options::valid_key( $key ) ) {
			return new WP_Error(
				'goya24_invalid_key',
				__( 'The workspace key is not in the expected form.', 'goya24' ),
				array( 'status' => 400 )
			);
		}

		$secret = trim( sanitize_text_field( (string) $request->get_param( 'secret' ) ) );
		if ( '' !== $secret && ! Goya24_Options::valid_secret( $secret ) ) {
			return new WP_Error(
				'goya24_invalid_secret',
				__( 'The identity secret is not in the expected form.', 'goya24' ),
				array( 'status' => 400 )
			);
		}

		$workspace = $request->get_param( 'workspace' );
		$workspace = is_array( $workspace ) ? $workspace : array();

		// The state is spent whether or not the rest was valid: a retry
		// starts from the button again.
		delete_transient( self::STATE_TRANSIENT );

		Goya24_Options::update(
			array(
				'key'                => $key,
				'secret'             => $secret,
				'workspace_id'       => isset( $workspace['id'] ) ? sanitize_text_field( (string) $workspace['id'] ) : '',
				'workspace_name'     => isset( $workspace['name'] ) ? sanitize_text_field( (string) $workspace['name'] ) : '',
				'connected_at'       => gmdate( 'c' ),
				'store_connected_at' => '',
			)
		);

		return rest_ensure_response( $this->facts() + array( 'ok' => true ) );
	}

	/**
	 * Whether the plugin is here, and what it can do.
	 *
	 * @return WP_REST_Response
	 */
	public function status() {
		return rest_ensure_response( $this->facts() );
	}

	/**
	 * The non-secret facts about this install.
	 *
	 * @return array<string, mixed>
	 */
	private function facts() {
		return array(
			'site'        => home_url( '/' ),
			'plugin'      => GOYA24_VERSION,
			'connected'   => Goya24_Options::is_connected(),
			'woocommerce' => Goya24_WooCommerce::is_active(),
			'locale'      => Goya24_Frontend::locale(),
		);
	}
}
