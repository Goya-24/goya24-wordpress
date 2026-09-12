<?php
/**
 * Who is signed in, and the proof.
 *
 * @package Goya24
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tells the messenger who is signed in, in a way it can trust.
 *
 * The site already knows who is on the page. Without this the agent meets
 * every customer as a stranger and asks for an email the site has had all
 * along. The mechanism is the one every messenger uses: the workspace has a
 * secret, this server computes hmac_sha256(secret, user id) and puts it on
 * the page beside the user's own details, and goya24 recomputes and compares
 * before it trusts a word. A hash never travels back; a customer cannot
 * forge one for somebody else without the secret, which only this server
 * and goya24 hold.
 *
 * Only the id is signed. A shop that renames somebody, or fixes a typo in an
 * email, changes nothing here — the id is what decides whose orders the
 * agent may look up, and that is the thing worth proving.
 */
final class Goya24_Identity {

	/**
	 * What the site's server is asked to compute.
	 *
	 * @param string     $secret  The workspace's identity secret.
	 * @param string|int $user_id The user's id.
	 * @return string Lower-case hex.
	 */
	public static function sign( $secret, $user_id ) {
		return hash_hmac( 'sha256', (string) $user_id, (string) $secret );
	}

	/**
	 * The signed-in user as the messenger tag wants it.
	 *
	 * Signed when the site has the secret; sent as a claim (which goya24
	 * marks as one) when it does not. Null for a visitor, or when the site
	 * chose not to identify anybody.
	 *
	 * @return array<string, string>|null
	 */
	public static function current() {
		if ( ! Goya24_Options::get( 'identify' ) || ! is_user_logged_in() ) {
			return null;
		}

		$user = wp_get_current_user();
		if ( ! $user->ID ) {
			return null;
		}

		$data = array(
			'id'    => (string) $user->ID,
			'email' => (string) $user->user_email,
			'name'  => (string) $user->display_name,
		);

		/**
		 * What the messenger is told about the signed-in user.
		 *
		 * Return null to keep this visitor anonymous. `id`, `email`, `name`
		 * and `plan` are the fields the messenger reads; the id is signed
		 * after this filter runs, so a changed id is still proven.
		 *
		 * @param array<string, string>|null $data The user as the tag will carry it.
		 * @param WP_User                    $user The signed-in user.
		 */
		$data = apply_filters( 'goya24_user', $data, $user );
		if ( ! is_array( $data ) || empty( $data['id'] ) ) {
			return null;
		}

		$out = array();
		foreach ( array( 'id', 'email', 'name', 'plan' ) as $field ) {
			if ( isset( $data[ $field ] ) && '' !== (string) $data[ $field ] ) {
				$out[ $field ] = (string) $data[ $field ];
			}
		}

		$secret = Goya24_Options::secret();
		if ( '' !== $secret ) {
			$out['hash'] = self::sign( $secret, $out['id'] );
		}

		return $out;
	}
}
