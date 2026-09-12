<?php
/**
 * Everything the plugin remembers, in one option.
 *
 * @package Goya24
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the plugin's settings.
 *
 * Constants win over stored values, so a site can pin the origin or the key
 * in wp-config.php — a staging site pointing at a staging workspace, say —
 * without the setting being editable from the screen.
 */
final class Goya24_Options {

	/** The option name. */
	const OPTION = 'goya24';

	/** The service the messenger is loaded from and the API lives under. */
	const DEFAULT_ORIGIN = 'https://goya24.com';

	/** What a workspace's public key looks like. */
	const KEY_PATTERN = '/^d24_pk_[A-Za-z0-9_-]{16,64}$/';

	/** What an identity secret looks like: hex, made by the service. */
	const SECRET_PATTERN = '/^[A-Za-z0-9_-]{16,200}$/';

	/**
	 * Defaults for every setting.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'key'                => '',
			'secret'             => '',
			'origin'             => '',
			'locale'             => 'auto',
			'theme'              => 'auto',
			'alignment'          => 'right',
			'identify'           => true,
			'workspace_id'       => '',
			'workspace_name'     => '',
			'connected_at'       => '',
			'store_connected_at' => '',
		);
	}

	/**
	 * Every setting, with defaults filled in.
	 *
	 * @return array<string, mixed>
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return array_merge( self::defaults(), $stored );
	}

	/**
	 * One setting.
	 *
	 * @param string $name Setting name.
	 * @return mixed Null for a name that is not a setting.
	 */
	public static function get( $name ) {
		$all = self::all();
		return array_key_exists( $name, $all ) ? $all[ $name ] : null;
	}

	/**
	 * Store some settings, leaving the rest as they are.
	 *
	 * @param array<string, mixed> $values Settings to change.
	 *
	 * @return void
	 */
	public static function update( array $values ) {
		update_option( self::OPTION, array_merge( self::all(), $values ) );
	}

	/**
	 * Forget the workspace: key, secret and what came with them.
	 *
	 * The messenger's look and the identify switch stay, so reconnecting to
	 * another workspace does not mean setting them again.
	 *
	 * @return void
	 */
	public static function disconnect() {
		self::update(
			array(
				'key'                => '',
				'secret'             => '',
				'workspace_id'       => '',
				'workspace_name'     => '',
				'connected_at'       => '',
				'store_connected_at' => '',
			)
		);
	}

	/**
	 * The workspace's public key: the constant if defined, else the stored one.
	 *
	 * @return string
	 */
	public static function key() {
		if ( defined( 'GOYA24_KEY' ) && GOYA24_KEY ) {
			return (string) GOYA24_KEY;
		}
		return (string) self::get( 'key' );
	}

	/**
	 * The secret the site signs user ids with.
	 *
	 * @return string Empty when the site cannot prove who is signed in.
	 */
	public static function secret() {
		if ( defined( 'GOYA24_IDENTITY_SECRET' ) && GOYA24_IDENTITY_SECRET ) {
			return (string) GOYA24_IDENTITY_SECRET;
		}
		return (string) self::get( 'secret' );
	}

	/**
	 * The origin the messenger and the API are served from, no trailing slash.
	 *
	 * @return string
	 */
	public static function origin() {
		$origin = self::origin_is_pinned() ? (string) constant( 'GOYA24_ORIGIN' ) : (string) self::get( 'origin' );
		$origin = untrailingslashit( trim( $origin ) );
		return '' !== $origin ? $origin : self::DEFAULT_ORIGIN;
	}

	/**
	 * Whether wp-config.php fixes the origin.
	 *
	 * @return bool
	 */
	public static function origin_is_pinned() {
		return defined( 'GOYA24_ORIGIN' ) && '' !== (string) GOYA24_ORIGIN;
	}

	/**
	 * Whether wp-config.php fixes the key.
	 *
	 * @return bool
	 */
	public static function key_is_pinned() {
		return defined( 'GOYA24_KEY' ) && '' !== (string) GOYA24_KEY;
	}

	/**
	 * Whether the site has a workspace to talk to.
	 *
	 * @return bool
	 */
	public static function is_connected() {
		return '' !== self::key();
	}

	/**
	 * Whether a string is a workspace key.
	 *
	 * @param mixed $key Candidate.
	 * @return bool
	 */
	public static function valid_key( $key ) {
		return is_string( $key ) && 1 === preg_match( self::KEY_PATTERN, $key );
	}

	/**
	 * Whether a string is an identity secret.
	 *
	 * @param mixed $secret Candidate.
	 * @return bool
	 */
	public static function valid_secret( $secret ) {
		return is_string( $secret ) && 1 === preg_match( self::SECRET_PATTERN, $secret );
	}

	/**
	 * Whether a string is an origin the plugin may load the messenger from:
	 * http(s), a host, nothing else.
	 *
	 * @param mixed $origin Candidate.
	 * @return bool
	 */
	public static function valid_origin( $origin ) {
		if ( ! is_string( $origin ) || '' === $origin ) {
			return false;
		}
		$parts = wp_parse_url( $origin );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}
		if ( ! in_array( $parts['scheme'], array( 'http', 'https' ), true ) ) {
			return false;
		}
		foreach ( array( 'user', 'pass', 'query', 'fragment' ) as $forbidden ) {
			if ( ! empty( $parts[ $forbidden ] ) ) {
				return false;
			}
		}
		return empty( $parts['path'] ) || '/' === $parts['path'];
	}

	/**
	 * Clean what the settings form submitted.
	 *
	 * Only the fields the form has are taken from the input; a blank secret
	 * means "keep the one we have", because the field never shows it. What
	 * the connection wrote (workspace, timestamps) is not on the form and is
	 * carried over untouched.
	 *
	 * @param mixed $input Raw values from the form.
	 * @return array<string, mixed> The whole option, valid.
	 */
	public static function sanitize( $input ) {
		$current = self::all();
		$input   = is_array( $input ) ? $input : array();
		$clean   = $current;

		if ( isset( $input['key'] ) ) {
			$key = trim( sanitize_text_field( wp_unslash( $input['key'] ) ) );
			if ( '' === $key ) {
				$clean['key'] = '';
			} elseif ( self::valid_key( $key ) ) {
				$clean['key'] = $key;
			} else {
				add_settings_error(
					self::OPTION,
					'goya24_key',
					__( 'That does not look like a workspace key. It starts with d24_pk_ and is on the Install page of your goya24 workspace.', 'goya24' )
				);
			}
		}

		if ( isset( $input['secret'] ) ) {
			$secret = trim( sanitize_text_field( wp_unslash( $input['secret'] ) ) );
			if ( '' !== $secret ) {
				if ( self::valid_secret( $secret ) ) {
					$clean['secret'] = $secret;
				} else {
					add_settings_error(
						self::OPTION,
						'goya24_secret',
						__( 'That does not look like an identity secret. It is made on the Install page of your goya24 workspace and shown once.', 'goya24' )
					);
				}
			}
		}

		if ( isset( $input['origin'] ) ) {
			$origin = untrailingslashit( trim( sanitize_text_field( wp_unslash( $input['origin'] ) ) ) );
			if ( '' === $origin || self::DEFAULT_ORIGIN === $origin ) {
				$clean['origin'] = '';
			} elseif ( self::valid_origin( $origin ) ) {
				$clean['origin'] = $origin;
			} else {
				add_settings_error(
					self::OPTION,
					'goya24_origin',
					__( 'The service address must be an https address with nothing after the host, like https://goya24.com.', 'goya24' )
				);
			}
		}

		if ( isset( $input['locale'] ) ) {
			$locale          = sanitize_key( $input['locale'] );
			$clean['locale'] = in_array( $locale, array( 'auto', 'fa', 'en' ), true ) ? $locale : 'auto';
		}

		if ( isset( $input['theme'] ) ) {
			$theme          = sanitize_key( $input['theme'] );
			$clean['theme'] = in_array( $theme, array( 'auto', 'light', 'dark' ), true ) ? $theme : 'auto';
		}

		if ( isset( $input['alignment'] ) ) {
			$clean['alignment'] = 'left' === sanitize_key( $input['alignment'] ) ? 'left' : 'right';
		}

		// A checkbox that is off is simply absent from the post, so this
		// field decides from the whole submission, not from its own presence.
		if ( ! empty( $input['_form'] ) ) {
			$clean['identify'] = ! empty( $input['identify'] );
		}

		return $clean;
	}
}
