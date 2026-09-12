<?php
/**
 * The option: defaults, precedence, and what the form is allowed to store.
 *
 * @package Goya24
 */

class Test_Options extends WP_UnitTestCase {

	const KEY    = 'd24_pk_oPJYZVIIFNlTucNtha4LLFdh';
	const SECRET = 'd951a306786d19b30bd5b1c8aa6902183cbb38d0df87a670fd338b09cdc942ee';

	public function set_up() {
		parent::set_up();
		delete_option( Goya24_Options::OPTION );
	}

	public function test_defaults_fill_in_what_is_not_stored() {
		update_option( Goya24_Options::OPTION, array( 'key' => self::KEY ) );

		$all = Goya24_Options::all();

		$this->assertSame( self::KEY, $all['key'] );
		$this->assertSame( 'auto', $all['locale'] );
		$this->assertTrue( $all['identify'] );
		$this->assertSame( '', $all['secret'] );
	}

	public function test_a_corrupt_option_is_treated_as_empty() {
		update_option( Goya24_Options::OPTION, 'not an array' );

		$this->assertSame( Goya24_Options::defaults(), Goya24_Options::all() );
		$this->assertNull( Goya24_Options::get( 'no_such_setting' ) );
	}

	public function test_update_keeps_the_rest() {
		Goya24_Options::update( array( 'key' => self::KEY ) );
		Goya24_Options::update( array( 'theme' => 'dark' ) );

		$this->assertSame( self::KEY, Goya24_Options::get( 'key' ) );
		$this->assertSame( 'dark', Goya24_Options::get( 'theme' ) );
	}

	public function test_connected_means_a_key() {
		$this->assertFalse( Goya24_Options::is_connected() );
		Goya24_Options::update( array( 'key' => self::KEY ) );
		$this->assertTrue( Goya24_Options::is_connected() );
	}

	public function test_disconnect_forgets_the_workspace_but_not_the_look() {
		Goya24_Options::update(
			array(
				'key'            => self::KEY,
				'secret'         => self::SECRET,
				'workspace_name' => 'Shop',
				'connected_at'   => '2026-09-12T00:00:00+00:00',
				'theme'          => 'dark',
				'identify'       => false,
			)
		);

		Goya24_Options::disconnect();

		$this->assertSame( '', Goya24_Options::key() );
		$this->assertSame( '', Goya24_Options::secret() );
		$this->assertSame( '', Goya24_Options::get( 'workspace_name' ) );
		$this->assertSame( '', Goya24_Options::get( 'connected_at' ) );
		$this->assertSame( 'dark', Goya24_Options::get( 'theme' ) );
		$this->assertFalse( Goya24_Options::get( 'identify' ) );
	}

	public function test_origin_defaults_to_the_service_and_loses_its_slash() {
		$this->assertSame( 'https://goya24.com', Goya24_Options::origin() );
		Goya24_Options::update( array( 'origin' => 'https://staging.example.com/' ) );
		$this->assertSame( 'https://staging.example.com', Goya24_Options::origin() );
	}

	/**
	 * @dataProvider keys
	 */
	public function test_key_shape( $key, $valid ) {
		$this->assertSame( $valid, Goya24_Options::valid_key( $key ) );
	}

	public function keys() {
		return array(
			'real'         => array( self::KEY, true ),
			'short'        => array( 'd24_pk_abc', false ),
			'wrong prefix' => array( 'pk_oPJYZVIIFNlTucNtha4LLFdh', false ),
			'spaces'       => array( 'd24_pk_oPJYZVIIF NlTucNtha4LLFdh', false ),
			'script'       => array( 'd24_pk_<script>alert(1)</script>', false ),
			'not a string' => array( array( self::KEY ), false ),
		);
	}

	/**
	 * @dataProvider origins
	 */
	public function test_origin_shape( $origin, $valid ) {
		$this->assertSame( $valid, Goya24_Options::valid_origin( $origin ) );
	}

	public function origins() {
		return array(
			'service'        => array( 'https://goya24.com', true ),
			'port'           => array( 'http://localhost:3000', true ),
			'trailing slash' => array( 'https://goya24.com/', true ),
			'path'           => array( 'https://goya24.com/app', false ),
			'query'          => array( 'https://goya24.com/?x=1', false ),
			'credentials'    => array( 'https://user:pass@goya24.com', false ),
			'javascript'     => array( 'javascript:alert(1)', false ),
			'bare host'      => array( 'goya24.com', false ),
			'empty'          => array( '', false ),
		);
	}

	public function test_sanitize_stores_a_good_key_and_refuses_a_bad_one() {
		$clean = Goya24_Options::sanitize( array( 'key' => ' ' . self::KEY . ' ' ) );
		$this->assertSame( self::KEY, $clean['key'] );

		Goya24_Options::update( array( 'key' => self::KEY ) );
		$clean = Goya24_Options::sanitize( array( 'key' => 'nope' ) );
		$this->assertSame( self::KEY, $clean['key'], 'a bad key leaves the stored one alone' );
		$this->assertNotEmpty( get_settings_errors( Goya24_Options::OPTION ) );
	}

	public function test_sanitize_keeps_the_secret_when_the_field_is_blank() {
		Goya24_Options::update( array( 'secret' => self::SECRET ) );

		$clean = Goya24_Options::sanitize( array( 'secret' => '' ) );

		$this->assertSame( self::SECRET, $clean['secret'] );
	}

	public function test_sanitize_carries_over_what_the_form_does_not_have() {
		Goya24_Options::update(
			array(
				'workspace_name' => 'Shop',
				'connected_at'   => '2026-09-12T00:00:00+00:00',
			)
		);

		$clean = Goya24_Options::sanitize( array( 'locale' => 'fa' ) );

		$this->assertSame( 'fa', $clean['locale'] );
		$this->assertSame( 'Shop', $clean['workspace_name'] );
		$this->assertSame( '2026-09-12T00:00:00+00:00', $clean['connected_at'] );
	}

	public function test_sanitize_falls_back_for_choices_it_does_not_know() {
		$clean = Goya24_Options::sanitize(
			array(
				'locale'    => 'de',
				'theme'     => 'sepia',
				'alignment' => 'middle',
			)
		);

		$this->assertSame( 'auto', $clean['locale'] );
		$this->assertSame( 'auto', $clean['theme'] );
		$this->assertSame( 'right', $clean['alignment'] );
	}

	public function test_sanitize_reads_the_checkbox_only_from_a_full_form() {
		$clean = Goya24_Options::sanitize( array( 'locale' => 'en' ) );
		$this->assertTrue( $clean['identify'], 'no form marker: the checkbox is not judged' );

		$clean = Goya24_Options::sanitize( array( '_form' => '1' ) );
		$this->assertFalse( $clean['identify'], 'a submitted form without the box is off' );

		$clean = Goya24_Options::sanitize(
			array(
				'_form'    => '1',
				'identify' => '1',
			)
		);
		$this->assertTrue( $clean['identify'] );
	}

	public function test_sanitize_treats_the_default_origin_as_blank() {
		$clean = Goya24_Options::sanitize( array( 'origin' => 'https://goya24.com/' ) );
		$this->assertSame( '', $clean['origin'] );

		$clean = Goya24_Options::sanitize( array( 'origin' => 'https://goya24.com/app' ) );
		$this->assertSame( '', $clean['origin'] );
		$this->assertNotEmpty( get_settings_errors( Goya24_Options::OPTION ) );
	}
}
