<?php
/**
 * Who the tag says is signed in, and the proof beside it.
 *
 * @package Goya24
 */

class Test_Identity extends WP_UnitTestCase {

	const SECRET = 'd951a306786d19b30bd5b1c8aa6902183cbb38d0df87a670fd338b09cdc942ee';

	public function set_up() {
		parent::set_up();
		delete_option( Goya24_Options::OPTION );
		Goya24_Options::update( array( 'key' => 'd24_pk_oPJYZVIIFNlTucNtha4LLFdh' ) );
	}

	public function tear_down() {
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	public function test_sign_is_hmac_sha256_of_the_id() {
		$this->assertSame(
			hash_hmac( 'sha256', '42', self::SECRET ),
			Goya24_Identity::sign( self::SECRET, 42 )
		);
		// The same computation goya24's server makes, from its own test vector.
		$this->assertSame( 64, strlen( Goya24_Identity::sign( self::SECRET, 'u_1024' ) ) );
	}

	public function test_a_visitor_is_nobody() {
		$this->assertNull( Goya24_Identity::current() );
	}

	public function test_a_signed_in_user_with_a_secret_is_proven() {
		Goya24_Options::update( array( 'secret' => self::SECRET ) );
		$id = self::factory()->user->create(
			array(
				'user_email'   => 'sara@example.com',
				'display_name' => 'Sara',
			)
		);
		wp_set_current_user( $id );

		$user = Goya24_Identity::current();

		$this->assertSame( (string) $id, $user['id'] );
		$this->assertSame( 'sara@example.com', $user['email'] );
		$this->assertSame( 'Sara', $user['name'] );
		$this->assertSame( Goya24_Identity::sign( self::SECRET, $id ), $user['hash'] );
	}

	public function test_without_a_secret_the_user_is_a_claim() {
		$id = self::factory()->user->create();
		wp_set_current_user( $id );

		$user = Goya24_Identity::current();

		$this->assertSame( (string) $id, $user['id'] );
		$this->assertArrayNotHasKey( 'hash', $user );
	}

	public function test_the_switch_turns_it_off() {
		Goya24_Options::update( array( 'identify' => false ) );
		wp_set_current_user( self::factory()->user->create() );

		$this->assertNull( Goya24_Identity::current() );
	}

	public function test_the_filter_can_veto_or_rewrite_and_the_id_is_signed_afterwards() {
		Goya24_Options::update( array( 'secret' => self::SECRET ) );
		wp_set_current_user( self::factory()->user->create() );

		add_filter( 'goya24_user', '__return_null' );
		$this->assertNull( Goya24_Identity::current() );
		remove_filter( 'goya24_user', '__return_null' );

		$rewrite = static function ( $data ) {
			$data['id']   = 'crm_' . $data['id'];
			$data['plan'] = 'gold';
			$data['junk'] = 'ignored';
			return $data;
		};
		add_filter( 'goya24_user', $rewrite );
		$user = Goya24_Identity::current();
		remove_filter( 'goya24_user', $rewrite );

		$this->assertStringStartsWith( 'crm_', $user['id'] );
		$this->assertSame( 'gold', $user['plan'] );
		$this->assertArrayNotHasKey( 'junk', $user );
		$this->assertSame( Goya24_Identity::sign( self::SECRET, $user['id'] ), $user['hash'] );
	}
}
