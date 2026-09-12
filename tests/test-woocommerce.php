<?php
/**
 * The store: the token that proves the workspace, and WooCommerce's screen.
 *
 * WooCommerce itself is not loaded here; what it adds to the customer's
 * name is checked against a real store by hand. Everything the plugin
 * computes on its own is checked here.
 *
 * @package Goya24
 */

class Test_WooCommerce extends WP_UnitTestCase {

	const KEY    = 'd24_pk_oPJYZVIIFNlTucNtha4LLFdh';
	const SECRET = 'd951a306786d19b30bd5b1c8aa6902183cbb38d0df87a670fd338b09cdc942ee';

	public function set_up() {
		parent::set_up();
		delete_option( Goya24_Options::OPTION );
	}

	public function test_without_woocommerce_nothing_store_shaped_is_offered() {
		$this->assertFalse( Goya24_WooCommerce::is_active() );
		Goya24_Options::update(
			array(
				'key'    => self::KEY,
				'secret' => self::SECRET,
			)
		);
		$this->assertFalse( Goya24_WooCommerce::can_connect_store() );
	}

	public function test_the_customer_filter_leaves_the_user_alone_without_woocommerce() {
		$data = array(
			'id'   => '7',
			'name' => 'sara',
		);
		$user = new WP_User( 0 );

		$this->assertSame( $data, ( new Goya24_WooCommerce() )->customer( $data, $user ) );
		$this->assertNull( ( new Goya24_WooCommerce() )->customer( null, $user ) );
	}

	public function test_the_store_token_names_the_workspace_and_binds_the_site_for_a_while() {
		Goya24_Options::update(
			array(
				'key'    => self::KEY,
				'secret' => self::SECRET,
			)
		);
		$now = 1789172023;

		$token = Goya24_WooCommerce::store_token( $now );
		$parts = explode( '.', $token );

		$this->assertCount( 4, $parts );
		$this->assertSame( self::KEY, $parts[0] );
		$this->assertSame( (string) ( $now + 15 * MINUTE_IN_SECONDS ), $parts[1] );
		$this->assertSame( home_url( '/' ), base64_decode( strtr( $parts[2], '-_', '+/' ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- reading the site back out of the token.
		$this->assertSame(
			hash_hmac( 'sha256', home_url( '/' ) . '|' . $parts[1], self::SECRET ),
			$parts[3]
		);
		$this->assertMatchesRegularExpression( '/^[A-Za-z0-9._-]+$/', $token, 'safe in a query string as it is' );
	}

	public function test_the_authorize_url_is_woocommerces_own_screen() {
		Goya24_Options::update(
			array(
				'key'    => self::KEY,
				'secret' => self::SECRET,
				'origin' => 'https://staging.example.com',
			)
		);

		$url = Goya24_WooCommerce::authorize_url( 'https://example.org/wp-admin/options-general.php?page=goya24&goya24=store' );

		$this->assertStringStartsWith( home_url( '/wc-auth/v1/authorize?' ), $url );
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		$this->assertSame( 'goya24', $query['app_name'] );
		$this->assertSame( 'read_write', $query['scope'] );
		$this->assertSame( 'https://staging.example.com/api/integrations/woocommerce/callback', $query['callback_url'] );
		$this->assertSame( 'https://example.org/wp-admin/options-general.php?page=goya24&goya24=store', $query['return_url'] );
		$this->assertStringStartsWith( self::KEY . '.', $query['user_id'] );
	}
}
