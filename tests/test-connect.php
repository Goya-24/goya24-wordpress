<?php
/**
 * The connection hand-off: the button's URL and the endpoint it comes back to.
 *
 * @package Goya24
 */

class Test_Connect extends WP_UnitTestCase {

	const KEY    = 'd24_pk_oPJYZVIIFNlTucNtha4LLFdh';
	const SECRET = 'd951a306786d19b30bd5b1c8aa6902183cbb38d0df87a670fd338b09cdc942ee';

	public function set_up() {
		parent::set_up();
		delete_option( Goya24_Options::OPTION );
		delete_transient( Goya24_Connect::STATE_TRANSIENT );
	}

	private function post( array $body ) {
		$request = new WP_REST_Request( 'POST', '/goya24/v1/connect' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
		return rest_get_server()->dispatch( $request );
	}

	public function test_the_button_makes_a_state_and_carries_the_site_to_goya24() {
		$url = Goya24_Connect::start_url( 'https://example.org/wp-admin/options-general.php?page=goya24' );

		$state = get_transient( Goya24_Connect::STATE_TRANSIENT );
		$this->assertIsString( $state );
		$this->assertSame( 32, strlen( $state ) );

		$this->assertStringStartsWith( 'https://goya24.com/en/connect/wordpress?', $url );
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		$this->assertSame( home_url( '/' ), $query['site'] );
		$this->assertSame( rest_url( 'goya24/v1/connect' ), $query['callback'] );
		$this->assertSame( $state, $query['state'] );
		$this->assertSame( GOYA24_VERSION, $query['plugin'] );
		$this->assertSame( '0', $query['woocommerce'] );
		$this->assertStringContainsString( 'page=goya24', $query['return'] );
	}

	public function test_the_button_speaks_the_admins_language() {
		$id = self::factory()->user->create( array( 'locale' => 'fa_IR' ) );
		wp_set_current_user( $id );

		$url = Goya24_Connect::start_url( 'https://example.org/wp-admin/' );

		wp_set_current_user( 0 );
		$this->assertStringStartsWith( 'https://goya24.com/fa/connect/wordpress?', $url );
	}

	public function test_a_post_without_a_pending_connection_is_refused() {
		$response = $this->post(
			array(
				'state' => 'anything',
				'key'   => self::KEY,
			)
		);

		$this->assertSame( 403, $response->get_status() );
		$this->assertFalse( Goya24_Options::is_connected() );
	}

	public function test_the_wrong_state_is_refused() {
		Goya24_Connect::start_url( 'https://example.org/wp-admin/' );

		$response = $this->post(
			array(
				'state' => str_repeat( 'x', 32 ),
				'key'   => self::KEY,
			)
		);

		$this->assertSame( 403, $response->get_status() );
		$this->assertFalse( Goya24_Options::is_connected() );
		$this->assertNotFalse( get_transient( Goya24_Connect::STATE_TRANSIENT ), 'a wrong guess does not spend the real state' );
	}

	public function test_the_right_state_connects_the_site_once() {
		Goya24_Connect::start_url( 'https://example.org/wp-admin/' );
		$state = get_transient( Goya24_Connect::STATE_TRANSIENT );

		$response = $this->post(
			array(
				'state'     => $state,
				'key'       => self::KEY,
				'secret'    => self::SECRET,
				'workspace' => array(
					'id'   => 'ce4a8f01-cb92-446f-9340-cadba5730634',
					'name' => 'فروشگاه نمونه',
				),
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['ok'] );
		$this->assertTrue( $data['connected'] );
		$this->assertArrayNotHasKey( 'secret', $data );

		$this->assertSame( self::KEY, Goya24_Options::key() );
		$this->assertSame( self::SECRET, Goya24_Options::secret() );
		$this->assertSame( 'فروشگاه نمونه', Goya24_Options::get( 'workspace_name' ) );
		$this->assertNotSame( '', Goya24_Options::get( 'connected_at' ) );
		$this->assertFalse( get_transient( Goya24_Connect::STATE_TRANSIENT ) );

		$again = $this->post(
			array(
				'state' => $state,
				'key'   => self::KEY,
			)
		);
		$this->assertSame( 403, $again->get_status(), 'the state is spent' );
	}

	public function test_a_malformed_key_is_refused_and_spends_the_state() {
		Goya24_Connect::start_url( 'https://example.org/wp-admin/' );
		$state = get_transient( Goya24_Connect::STATE_TRANSIENT );

		$response = $this->post(
			array(
				'state' => $state,
				'key'   => 'not-a-key',
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertFalse( Goya24_Options::is_connected() );
	}

	public function test_reconnecting_forgets_the_old_store_connection() {
		Goya24_Options::update(
			array(
				'key'                => self::KEY,
				'store_connected_at' => '2026-09-01T00:00:00+00:00',
			)
		);
		Goya24_Connect::start_url( 'https://example.org/wp-admin/' );

		$this->post(
			array(
				'state' => get_transient( Goya24_Connect::STATE_TRANSIENT ),
				'key'   => 'd24_pk_AnotherWorkspaceKey0001',
			)
		);

		$this->assertSame( 'd24_pk_AnotherWorkspaceKey0001', Goya24_Options::key() );
		$this->assertSame( '', Goya24_Options::get( 'store_connected_at' ) );
	}

	public function test_status_says_what_is_there_and_nothing_secret() {
		Goya24_Options::update(
			array(
				'key'    => self::KEY,
				'secret' => self::SECRET,
			)
		);

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/goya24/v1/status' ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['connected'] );
		$this->assertSame( GOYA24_VERSION, $data['plugin'] );
		$this->assertFalse( $data['woocommerce'] );
		$this->assertStringNotContainsString( self::SECRET, wp_json_encode( $data ) );
		$this->assertStringNotContainsString( self::KEY, wp_json_encode( $data ) );
	}
}
