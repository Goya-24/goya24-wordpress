<?php
/**
 * The settings screen's wiring: menu, links, notice, uninstall.
 *
 * @package Goya24
 */

class Test_Admin extends WP_UnitTestCase {

	const KEY = 'd24_pk_oPJYZVIIFNlTucNtha4LLFdh';

	/**
	 * @var Goya24_Admin
	 */
	private $admin;

	public function set_up() {
		parent::set_up();
		delete_option( Goya24_Options::OPTION );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'dashboard' );
		$this->admin = new Goya24_Admin();
	}

	public function tear_down() {
		set_current_screen( 'front' );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	public function test_the_screen_sits_under_settings() {
		global $submenu;
		$submenu = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->admin->menu();

		$slugs = wp_list_pluck( $submenu['options-general.php'], 2 );
		$this->assertContains( 'goya24', $slugs );
	}

	public function test_the_option_is_registered_with_its_sanitizer() {
		$this->admin->register();

		$registered = get_registered_settings();
		$this->assertArrayHasKey( Goya24_Options::OPTION, $registered );
		$this->assertSame( array( 'Goya24_Options', 'sanitize' ), $registered[ Goya24_Options::OPTION ]['sanitize_callback'] );
		$this->assertFalse( $registered[ Goya24_Options::OPTION ]['show_in_rest'] );
	}

	public function test_the_plugins_list_offers_connect_then_settings() {
		$links = $this->admin->action_links( array( 'deactivate' => '<a href="#">Deactivate</a>' ) );
		$this->assertStringContainsString( '>Connect<', $links[0] );
		$this->assertStringContainsString( 'page=goya24', $links[0] );

		Goya24_Options::update( array( 'key' => self::KEY ) );
		$links = $this->admin->action_links( array() );
		$this->assertStringContainsString( '>Settings<', $links[0] );
	}

	public function test_the_notice_shows_until_connected_and_can_be_dismissed() {
		$html = get_echo( array( $this->admin, 'connect_notice' ) );
		$this->assertStringContainsString( 'not connected yet', $html );
		$this->assertStringContainsString( 'page=goya24', $html );

		update_user_meta( get_current_user_id(), Goya24_Admin::DISMISSED_META, 1 );
		$this->assertSame( '', get_echo( array( $this->admin, 'connect_notice' ) ) );
		delete_user_meta( get_current_user_id(), Goya24_Admin::DISMISSED_META );

		Goya24_Options::update( array( 'key' => self::KEY ) );
		$this->assertSame( '', get_echo( array( $this->admin, 'connect_notice' ) ) );
	}

	public function test_the_notice_stays_off_other_screens_and_away_from_other_roles() {
		set_current_screen( 'edit-post' );
		$this->assertSame( '', get_echo( array( $this->admin, 'connect_notice' ) ) );

		set_current_screen( 'dashboard' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( '', get_echo( array( $this->admin, 'connect_notice' ) ) );
	}

	public function test_the_screen_renders_both_states() {
		$html = get_echo( array( $this->admin, 'render' ) );
		$this->assertStringContainsString( 'Connect to goya24', $html );
		$this->assertStringContainsString( 'admin-post.php?action=goya24_connect', $html );
		$this->assertStringNotContainsString( 'Disconnect', $html );

		Goya24_Options::update(
			array(
				'key'            => self::KEY,
				'secret'         => 'd951a306786d19b30bd5b1c8aa6902183cbb38d0df87a670fd338b09cdc942ee',
				'workspace_name' => 'Shop',
				'connected_at'   => '2026-09-12T00:00:00+00:00',
			)
		);
		$html = get_echo( array( $this->admin, 'render' ) );
		$this->assertStringContainsString( 'Connected', $html );
		$this->assertStringContainsString( 'Shop', $html );
		$this->assertStringContainsString( 'introduced with proof', $html );
		$this->assertStringContainsString( 'action=goya24_disconnect', $html );
		$this->assertStringNotContainsString( 'd951a306', $html, 'the secret is never printed' );
	}

	public function test_uninstall_removes_everything_the_plugin_stored() {
		Goya24_Options::update( array( 'key' => self::KEY ) );
		set_transient( Goya24_Connect::STATE_TRANSIENT, 'x', 60 );
		update_user_meta( get_current_user_id(), Goya24_Admin::DISMISSED_META, 1 );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'goya24/goya24.php' );
		}
		require dirname( __DIR__ ) . '/uninstall.php';

		$this->assertFalse( get_option( Goya24_Options::OPTION ) );
		$this->assertFalse( get_transient( Goya24_Connect::STATE_TRANSIENT ) );
		$this->assertSame( '', get_user_meta( get_current_user_id(), Goya24_Admin::DISMISSED_META, true ) );
	}
}
