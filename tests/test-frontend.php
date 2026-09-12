<?php
/**
 * The tag on the page.
 *
 * @package Goya24
 */

class Test_Frontend extends WP_UnitTestCase {

	const KEY = 'd24_pk_oPJYZVIIFNlTucNtha4LLFdh';

	public function set_up() {
		parent::set_up();
		delete_option( Goya24_Options::OPTION );
		// A fresh registry: WP_Scripts remembers what it printed, across tests.
		$GLOBALS['wp_scripts'] = null;
		$this->go_to( home_url( '/' ) );
	}

	public function tear_down() {
		wp_set_current_user( 0 );
		$GLOBALS['wp_scripts'] = null;
		parent::tear_down();
	}

	private function site_locale( $locale ) {
		add_filter(
			'locale',
			static function () use ( $locale ) {
				return $locale;
			}
		);
	}

	public function test_nothing_loads_until_the_site_is_connected() {
		$this->assertFalse( Goya24_Frontend::should_load() );
		do_action( 'wp_enqueue_scripts' );
		$this->assertFalse( wp_script_is( Goya24_Frontend::HANDLE, 'enqueued' ) );
	}

	public function test_a_connected_site_loads_the_messenger_async_from_the_origin() {
		Goya24_Options::update( array( 'key' => self::KEY ) );

		do_action( 'wp_enqueue_scripts' );
		$html = get_echo( array( wp_scripts(), 'do_items' ), array( Goya24_Frontend::HANDLE ) );

		$this->assertTrue( wp_script_is( Goya24_Frontend::HANDLE, 'enqueued' ) );
		$this->assertStringContainsString( 'src="https://goya24.com/widget.js"', $html );
		$this->assertStringContainsString( 'async', $html );
		$this->assertStringContainsString( 'data-key="' . self::KEY . '"', $html );
		$this->assertStringNotContainsString( '?ver=', $html );
	}

	public function test_the_filter_can_keep_it_off_a_page() {
		Goya24_Options::update( array( 'key' => self::KEY ) );
		add_filter( 'goya24_should_load', '__return_false' );

		do_action( 'wp_enqueue_scripts' );

		remove_filter( 'goya24_should_load', '__return_false' );
		$this->assertFalse( wp_script_is( Goya24_Frontend::HANDLE, 'enqueued' ) );
	}

	public function test_feeds_get_no_tag() {
		Goya24_Options::update( array( 'key' => self::KEY ) );
		$this->go_to( home_url( '/?feed=rss2' ) );

		$this->assertFalse( Goya24_Frontend::should_load() );
	}

	public function test_the_locale_follows_the_site_unless_told_otherwise() {
		$this->assertSame( 'en', Goya24_Frontend::locale() );

		$this->site_locale( 'fa_IR' );
		$this->assertSame( 'fa', Goya24_Frontend::locale() );

		remove_all_filters( 'locale' );
		$this->site_locale( 'de_DE' );
		$this->assertSame( 'en', Goya24_Frontend::locale(), 'a language the messenger does not speak gets English' );

		Goya24_Options::update( array( 'locale' => 'fa' ) );
		$this->assertSame( 'fa', Goya24_Frontend::locale() );
	}

	public function test_attributes_carry_only_what_is_set() {
		Goya24_Options::update( array( 'key' => self::KEY ) );

		$attributes = Goya24_Frontend::tag_attributes();

		$this->assertSame(
			array(
				'data-key'    => self::KEY,
				'data-locale' => 'en',
			),
			$attributes
		);

		Goya24_Options::update(
			array(
				'theme'     => 'dark',
				'alignment' => 'left',
			)
		);
		$attributes = Goya24_Frontend::tag_attributes();
		$this->assertSame( 'dark', $attributes['data-theme'] );
		$this->assertSame( 'left', $attributes['data-alignment'] );
	}

	public function test_a_signed_in_user_rides_on_the_tag_as_json() {
		Goya24_Options::update(
			array(
				'key'    => self::KEY,
				'secret' => 'd951a306786d19b30bd5b1c8aa6902183cbb38d0df87a670fd338b09cdc942ee',
			)
		);
		$id = self::factory()->user->create( array( 'user_email' => 'sara@example.com' ) );
		wp_set_current_user( $id );

		do_action( 'wp_enqueue_scripts' );
		$html = get_echo( array( wp_scripts(), 'do_items' ), array( Goya24_Frontend::HANDLE ) );

		$this->assertMatchesRegularExpression( '/data-user="([^"]+)"/', $html );
		preg_match( '/data-user="([^"]+)"/', $html, $m );
		$user = json_decode( html_entity_decode( $m[1], ENT_QUOTES ), true );
		$this->assertSame( (string) $id, $user['id'] );
		$this->assertSame( 'sara@example.com', $user['email'] );
		$this->assertSame( 64, strlen( $user['hash'] ) );
	}

	public function test_other_scripts_are_left_alone() {
		Goya24_Options::update( array( 'key' => self::KEY ) );
		wp_enqueue_script( 'other', 'https://example.org/other.js', array(), '1', true );

		do_action( 'wp_enqueue_scripts' );
		$html = get_echo( array( wp_scripts(), 'do_items' ), array( 'other' ) );

		$this->assertStringNotContainsString( 'data-key', $html );
	}
}
