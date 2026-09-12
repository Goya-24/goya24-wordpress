<?php
/**
 * The messenger tag on the site's pages.
 *
 * @package Goya24
 */

defined( 'ABSPATH' ) || exit;

/**
 * Puts the messenger on every public page.
 *
 * The tag is the same one the Install page hands out — `widget.js` from the
 * service, with the workspace key and a few data attributes on it. The
 * messenger itself lives in a frame the script opens, so nothing of the
 * site's CSS reaches it and this plugin never carries a copy of the UI: a
 * fix on the service reaches every site without a plugin update.
 */
final class Goya24_Frontend {

	/** The script handle; the tag's id is this plus `-js`. */
	const HANDLE = 'goya24-messenger';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'wp_script_attributes', array( $this, 'attributes' ) );
	}

	/**
	 * Whether this page gets the messenger.
	 *
	 * Public pages of a connected site, and nothing that is not a page: not
	 * the customizer's preview, not a feed, not an embed card.
	 *
	 * @return bool
	 */
	public static function should_load() {
		$load = Goya24_Options::is_connected()
			&& ! is_admin()
			&& ! is_customize_preview()
			&& ! is_feed()
			&& ! is_embed();

		/**
		 * Whether the messenger appears on the current page.
		 *
		 * `add_filter( 'goya24_should_load', fn( $load ) => $load && ! is_page( 'checkout' ) );`
		 *
		 * @param bool $load True when the plugin would load it.
		 */
		return (bool) apply_filters( 'goya24_should_load', $load );
	}

	/**
	 * Enqueue the loader, async, at the end of the page.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! self::should_load() ) {
			return;
		}
		wp_enqueue_script(
			self::HANDLE,
			Goya24_Options::origin() . '/widget.js',
			array(),
			null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- the service versions it; a ?ver= would only defeat its caching.
			array(
				'strategy'  => 'async',
				'in_footer' => true,
			)
		);
	}

	/**
	 * The locale the messenger opens in.
	 *
	 * The setting when it names one; otherwise the site's language. The
	 * messenger speaks Persian and English, so a site in anything else gets
	 * English rather than a guess.
	 *
	 * @return string `fa` or `en`.
	 */
	public static function locale() {
		$setting = Goya24_Options::get( 'locale' );
		if ( 'fa' === $setting || 'en' === $setting ) {
			return $setting;
		}
		return 0 === strpos( (string) get_locale(), 'fa' ) ? 'fa' : 'en';
	}

	/**
	 * The tag's data attributes: everything the loader reads.
	 *
	 * @return array<string, string>
	 */
	public static function tag_attributes() {
		$attributes = array(
			'data-key'    => Goya24_Options::key(),
			'data-locale' => self::locale(),
		);

		$theme = Goya24_Options::get( 'theme' );
		if ( 'light' === $theme || 'dark' === $theme ) {
			$attributes['data-theme'] = $theme;
		}

		if ( 'left' === Goya24_Options::get( 'alignment' ) ) {
			$attributes['data-alignment'] = 'left';
		}

		$user = Goya24_Identity::current();
		if ( null !== $user ) {
			$attributes['data-user'] = (string) wp_json_encode( $user );
		}

		/**
		 * The data attributes on the messenger tag.
		 *
		 * The loader reads `data-key`, `data-locale`, `data-theme`,
		 * `data-alignment`, `data-horizontal-padding`, `data-vertical-padding`
		 * and `data-user`.
		 *
		 * @param array<string, string> $attributes Attribute name to value.
		 */
		return (array) apply_filters( 'goya24_tag_attributes', $attributes );
	}

	/**
	 * Put the data attributes on our tag and nobody else's.
	 *
	 * @param mixed $attributes The tag's attributes so far.
	 * @return mixed
	 */
	public function attributes( $attributes ) {
		if ( ! is_array( $attributes ) || ! isset( $attributes['id'] ) || self::HANDLE . '-js' !== $attributes['id'] ) {
			return $attributes;
		}
		return array_merge( $attributes, self::tag_attributes() );
	}
}
