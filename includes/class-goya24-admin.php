<?php
/**
 * The settings screen.
 *
 * @package Goya24
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings → goya24.
 *
 * One screen, and the first thing on it is the only thing most sites need:
 * a button that connects the site to a workspace. Everything else — the
 * messenger's language and colours, the WooCommerce store, the addresses
 * for people who run their own goya24 — sits under it.
 */
final class Goya24_Admin {

	/** The screen's slug, and the settings group. */
	const PAGE = 'goya24';

	/** Capability for everything here. */
	const CAP = 'manage_options';

	/** User meta remembering a dismissed "not connected yet" notice. */
	const DISMISSED_META = 'goya24_dismissed_connect_notice';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_action( 'admin_init', array( $this, 'store_returned' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_notices', array( $this, 'connect_notice' ) );
		add_action( 'admin_post_goya24_connect', array( $this, 'connect' ) );
		add_action( 'admin_post_goya24_connect_store', array( $this, 'connect_store' ) );
		add_action( 'admin_post_goya24_disconnect', array( $this, 'disconnect' ) );
		add_action( 'admin_post_goya24_dismiss_notice', array( $this, 'dismiss_notice' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( GOYA24_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * The screen's address.
	 *
	 * @param array<string, string> $args Query arguments to add.
	 * @return string
	 */
	public static function url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'options-general.php' ) );
	}

	/**
	 * Settings → goya24.
	 *
	 * @return void
	 */
	public function menu() {
		add_options_page(
			__( 'goya24', 'goya24' ),
			__( 'goya24', 'goya24' ),
			self::CAP,
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * The option, through the Settings API, so options.php does the saving.
	 *
	 * @return void
	 */
	public function register() {
		register_setting(
			self::PAGE,
			Goya24_Options::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'Goya24_Options', 'sanitize' ),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Styles for the screen only.
	 *
	 * @param string $hook The current admin page.
	 *
	 * @return void
	 */
	public function assets( $hook ) {
		if ( 'settings_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_enqueue_style( 'goya24-admin', GOYA24_URL . 'admin/goya24-admin.css', array(), GOYA24_VERSION );
	}

	/**
	 * "Settings" beside the plugin on the plugins list.
	 *
	 * @param string[] $links The plugin's action links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		$label = Goya24_Options::is_connected() ? __( 'Settings', 'goya24' ) : __( 'Connect', 'goya24' );
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html( $label ) . '</a>' );
		return $links;
	}

	/**
	 * Until the site is connected, say so where the admin will look:
	 * the dashboard and the plugins list. Dismissable, per person.
	 *
	 * @return void
	 */
	public function connect_notice() {
		if ( Goya24_Options::is_connected() || ! current_user_can( self::CAP ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins' ), true ) ) {
			return;
		}
		if ( get_user_meta( get_current_user_id(), self::DISMISSED_META, true ) ) {
			return;
		}
		$dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=goya24_dismiss_notice' ), 'goya24_dismiss_notice' );
		?>
		<div class="notice notice-info goya24-notice">
			<p>
				<strong><?php esc_html_e( 'goya24 is installed but not connected yet.', 'goya24' ); ?></strong>
				<?php esc_html_e( 'The messenger appears on your site once it knows which workspace answers.', 'goya24' ); ?>
				<a class="button button-primary" href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Connect to goya24', 'goya24' ); ?></a>
				<a class="goya24-notice-dismiss" href="<?php echo esc_url( $dismiss ); ?>"><?php esc_html_e( 'Not now', 'goya24' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * The admin pressed "Connect": make the state now, then go.
	 *
	 * @return void
	 */
	public function connect() {
		$this->guard( 'goya24_connect' );
		$this->redirect_away( Goya24_Connect::start_url( self::url() ) );
	}

	/**
	 * The admin pressed "Connect the store": WooCommerce's screen, now.
	 *
	 * @return void
	 */
	public function connect_store() {
		$this->guard( 'goya24_connect_store' );
		if ( ! Goya24_WooCommerce::can_connect_store() ) {
			wp_safe_redirect( self::url( array( 'goya24' => 'store-needs-connection' ) ) );
			exit;
		}
		wp_safe_redirect( Goya24_WooCommerce::authorize_url( self::url( array( 'goya24' => 'store' ) ) ) );
		exit;
	}

	/**
	 * Forget the workspace.
	 *
	 * @return void
	 */
	public function disconnect() {
		$this->guard( 'goya24_disconnect' );
		Goya24_Options::disconnect();
		wp_safe_redirect( self::url( array( 'goya24' => 'disconnected' ) ) );
		exit;
	}

	/**
	 * Hide the "not connected yet" notice for this person.
	 *
	 * @return void
	 */
	public function dismiss_notice() {
		$this->guard( 'goya24_dismiss_notice' );
		update_user_meta( get_current_user_id(), self::DISMISSED_META, 1 );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * WooCommerce sent the browser back from its authorization screen.
	 *
	 * `success=1` means it made the keys and posted them to goya24. That is
	 * all this side learns, and all it records; the keys themselves never
	 * pass through here.
	 *
	 * @return void
	 */
	public function store_returned() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- a redirect from WooCommerce carries no nonce; all it can do is set a timestamp.
		if ( ! isset( $_GET['page'], $_GET['goya24'] ) || self::PAGE !== $_GET['page'] || 'store' !== $_GET['goya24'] ) {
			return;
		}
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$success = isset( $_GET['success'] ) ? sanitize_text_field( wp_unslash( $_GET['success'] ) ) : '';
		$error   = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
		// phpcs:enable
		if ( '1' === $success ) {
			Goya24_Options::update( array( 'store_connected_at' => gmdate( 'c' ) ) );
			wp_safe_redirect( self::url( array( 'goya24' => 'store-connected' ) ) );
			exit;
		}
		wp_safe_redirect(
			self::url(
				array(
					'goya24' => 'store-failed',
					'reason' => rawurlencode( mb_substr( $error, 0, 200 ) ),
				)
			)
		);
		exit;
	}

	/**
	 * Capability and nonce, or nothing.
	 *
	 * @param string $action The nonce action.
	 *
	 * @return void
	 */
	private function guard( $action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'goya24' ), 403 );
		}
		check_admin_referer( $action );
	}

	/**
	 * Leave for goya24's site.
	 *
	 * `wp_safe_redirect` refuses hosts it does not know, which is right
	 * everywhere else on this screen; here the destination is the service
	 * the plugin exists for, so it is allowed by name.
	 *
	 * @param string $url The destination.
	 *
	 * @return void
	 */
	private function redirect_away( $url ) {
		$host = wp_parse_url( Goya24_Options::origin(), PHP_URL_HOST );
		add_filter(
			'allowed_redirect_hosts',
			static function ( $hosts ) use ( $host ) {
				$hosts[] = $host;
				return $hosts;
			}
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * The screen.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$connected = Goya24_Options::is_connected();
		$settings  = Goya24_Options::all();
		?>
		<div class="wrap goya24-wrap">
			<h1 class="goya24-title">
				<img class="goya24-mark" src="<?php echo esc_url( GOYA24_URL . 'admin/goya24-mark.svg' ); ?>" alt="" width="28" height="28">
				<?php esc_html_e( 'goya24', 'goya24' ); ?>
			</h1>

			<?php settings_errors( Goya24_Options::OPTION ); ?>
			<?php $this->flash(); ?>

			<?php if ( $connected ) : ?>
				<?php $this->connected_card( $settings ); ?>
			<?php else : ?>
				<?php $this->connect_card(); ?>
			<?php endif; ?>

			<form method="post" action="options.php" class="goya24-form">
				<?php settings_fields( self::PAGE ); ?>
				<input type="hidden" name="<?php echo esc_attr( Goya24_Options::OPTION ); ?>[_form]" value="1">

				<h2><?php esc_html_e( 'Messenger', 'goya24' ); ?></h2>
				<p class="description"><?php esc_html_e( 'How the messenger looks on your site. Everything about what it says — the agent, its knowledge, your team — is set in your goya24 workspace.', 'goya24' ); ?></p>
				<table class="form-table" role="presentation">
					<?php
					$this->radio_row(
						'locale',
						__( 'Language', 'goya24' ),
						array(
							'auto' => __( 'Same as the site', 'goya24' ),
							'fa'   => __( 'Persian', 'goya24' ),
							'en'   => __( 'English', 'goya24' ),
						),
						$settings['locale']
					);
					$this->radio_row(
						'theme',
						__( 'Colours', 'goya24' ),
						array(
							'auto'  => __( 'Match the page', 'goya24' ),
							'light' => __( 'Light', 'goya24' ),
							'dark'  => __( 'Dark', 'goya24' ),
						),
						$settings['theme']
					);
					$this->radio_row(
						'alignment',
						__( 'Corner', 'goya24' ),
						array(
							'right' => __( 'Bottom right', 'goya24' ),
							'left'  => __( 'Bottom left', 'goya24' ),
						),
						$settings['alignment']
					);
					?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Signed-in customers', 'goya24' ); ?></th>
						<td>
							<label for="goya24-identify">
								<input type="checkbox" id="goya24-identify" name="<?php echo esc_attr( Goya24_Options::OPTION ); ?>[identify]" value="1" <?php checked( ! empty( $settings['identify'] ) ); ?>>
								<?php esc_html_e( 'Tell the messenger who is signed in', 'goya24' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'The agent greets a signed-in customer by name and, with WooCommerce connected, can find their orders without asking who they are. Their id is signed by this server, so nobody can pretend to be somebody else.', 'goya24' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php if ( Goya24_WooCommerce::is_active() ) : ?>
					<?php $this->store_section( $settings ); ?>
				<?php endif; ?>

				<details class="goya24-advanced" <?php echo ( ! $connected || '' !== $settings['origin'] ) ? 'open' : ''; ?>>
					<summary><?php esc_html_e( 'Advanced', 'goya24' ); ?></summary>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="goya24-key"><?php esc_html_e( 'Workspace key', 'goya24' ); ?></label></th>
							<td>
								<?php if ( Goya24_Options::key_is_pinned() ) : ?>
									<code><?php echo esc_html( Goya24_Options::key() ); ?></code>
									<p class="description"><?php esc_html_e( 'Set by GOYA24_KEY in wp-config.php.', 'goya24' ); ?></p>
								<?php else : ?>
									<input type="text" id="goya24-key" class="regular-text code" dir="ltr" name="<?php echo esc_attr( Goya24_Options::OPTION ); ?>[key]" value="<?php echo esc_attr( $settings['key'] ); ?>" placeholder="d24_pk_…" autocomplete="off" spellcheck="false">
									<p class="description"><?php esc_html_e( 'The Connect button fills this in. To do it by hand, copy it from Settings → Install in your workspace.', 'goya24' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="goya24-secret"><?php esc_html_e( 'Identity secret', 'goya24' ); ?></label></th>
							<td>
								<?php if ( defined( 'GOYA24_IDENTITY_SECRET' ) && GOYA24_IDENTITY_SECRET ) : ?>
									<p class="description"><?php esc_html_e( 'Set by GOYA24_IDENTITY_SECRET in wp-config.php.', 'goya24' ); ?></p>
								<?php else : ?>
									<input type="password" id="goya24-secret" class="regular-text code" dir="ltr" name="<?php echo esc_attr( Goya24_Options::OPTION ); ?>[secret]" value="" placeholder="<?php echo '' !== $settings['secret'] ? esc_attr__( '•••••••• (set — leave blank to keep)', 'goya24' ) : ''; ?>" autocomplete="new-password" spellcheck="false">
									<p class="description"><?php esc_html_e( 'Signs the ids of signed-in customers. Made on the Install page of your workspace and shown once; the Connect button brings it over on its own.', 'goya24' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="goya24-origin"><?php esc_html_e( 'Service address', 'goya24' ); ?></label></th>
							<td>
								<?php if ( Goya24_Options::origin_is_pinned() ) : ?>
									<code><?php echo esc_html( Goya24_Options::origin() ); ?></code>
									<p class="description"><?php esc_html_e( 'Set by GOYA24_ORIGIN in wp-config.php.', 'goya24' ); ?></p>
								<?php else : ?>
									<input type="url" id="goya24-origin" class="regular-text code" dir="ltr" name="<?php echo esc_attr( Goya24_Options::OPTION ); ?>[origin]" value="<?php echo esc_attr( $settings['origin'] ); ?>" placeholder="<?php echo esc_attr( Goya24_Options::DEFAULT_ORIGIN ); ?>">
									<p class="description"><?php esc_html_e( 'Only for a self-hosted goya24. Leave blank for the service.', 'goya24' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					</table>
				</details>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * The card a site sees before it is connected.
	 *
	 * @return void
	 */
	private function connect_card() {
		$connect = wp_nonce_url( admin_url( 'admin-post.php?action=goya24_connect' ), 'goya24_connect' );
		?>
		<div class="goya24-card goya24-card--connect">
			<h2><?php esc_html_e( 'Put the messenger on your site', 'goya24' ); ?></h2>
			<p><?php esc_html_e( 'One button. You sign in to goya24, choose the workspace that answers for this site, and come back connected — the key and the identity secret arrive on their own, nothing to copy.', 'goya24' ); ?></p>
			<p class="goya24-actions">
				<a class="button button-primary button-hero" href="<?php echo esc_url( $connect ); ?>"><?php esc_html_e( 'Connect to goya24', 'goya24' ); ?></a>
			</p>
			<p class="description">
				<?php
				printf(
					/* translators: %s: link to goya24 sign-up */
					esc_html__( 'No workspace yet? %s — it takes a minute, and the messenger works from the first message.', 'goya24' ),
					'<a href="' . esc_url( Goya24_Options::origin() . '/signup' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Create one', 'goya24' ) . '</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * The card a connected site sees.
	 *
	 * @param array<string, mixed> $settings Every setting.
	 *
	 * @return void
	 */
	private function connected_card( array $settings ) {
		$reconnect  = wp_nonce_url( admin_url( 'admin-post.php?action=goya24_connect' ), 'goya24_connect' );
		$disconnect = wp_nonce_url( admin_url( 'admin-post.php?action=goya24_disconnect' ), 'goya24_disconnect' );
		$name       = '' !== $settings['workspace_name'] ? $settings['workspace_name'] : Goya24_Options::key();
		$since      = '' !== $settings['connected_at'] ? $this->date( $settings['connected_at'] ) : '';
		$proven     = '' !== Goya24_Options::secret();
		?>
		<div class="goya24-card goya24-card--connected">
			<div class="goya24-card-body">
				<h2>
					<span class="goya24-dot goya24-dot--on" aria-hidden="true"></span>
					<?php esc_html_e( 'Connected', 'goya24' ); ?>
				</h2>
				<p>
					<?php
					if ( '' !== $since ) {
						printf(
							/* translators: 1: workspace name, 2: date */
							esc_html__( 'This site talks to the workspace %1$s, since %2$s. The messenger is on every public page.', 'goya24' ),
							'<strong>' . esc_html( $name ) . '</strong>',
							esc_html( $since )
						);
					} else {
						printf(
							/* translators: %s: workspace name or key */
							esc_html__( 'This site talks to the workspace %s. The messenger is on every public page.', 'goya24' ),
							'<strong>' . esc_html( $name ) . '</strong>'
						);
					}
					?>
				</p>
				<ul class="goya24-facts">
					<li>
						<?php if ( $proven ) : ?>
							<span class="goya24-dot goya24-dot--on" aria-hidden="true"></span>
							<?php esc_html_e( 'Signed-in customers are introduced with proof.', 'goya24' ); ?>
						<?php else : ?>
							<span class="goya24-dot goya24-dot--off" aria-hidden="true"></span>
							<?php esc_html_e( 'No identity secret: signed-in customers are introduced as a claim, and the agent will not look their orders up. Reconnect to fix this.', 'goya24' ); ?>
						<?php endif; ?>
					</li>
					<?php if ( Goya24_WooCommerce::is_active() ) : ?>
						<li>
							<?php if ( '' !== $settings['store_connected_at'] ) : ?>
								<span class="goya24-dot goya24-dot--on" aria-hidden="true"></span>
								<?php
								printf(
									/* translators: %s: date */
									esc_html__( 'Store connected on %s: the agent can find a customer\'s order.', 'goya24' ),
									esc_html( $this->date( $settings['store_connected_at'] ) )
								);
								?>
							<?php else : ?>
								<span class="goya24-dot goya24-dot--off" aria-hidden="true"></span>
								<?php esc_html_e( 'Store not connected yet: the agent cannot see orders. See WooCommerce below.', 'goya24' ); ?>
							<?php endif; ?>
						</li>
					<?php endif; ?>
				</ul>
			</div>
			<p class="goya24-actions">
				<a class="button" href="<?php echo esc_url( Goya24_Options::origin() . '/app' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Open goya24', 'goya24' ); ?></a>
				<a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'See it on the site', 'goya24' ); ?></a>
				<?php if ( ! Goya24_Options::key_is_pinned() ) : ?>
					<a class="button" href="<?php echo esc_url( $reconnect ); ?>"><?php esc_html_e( 'Connect a different workspace', 'goya24' ); ?></a>
					<a class="goya24-link-danger" href="<?php echo esc_url( $disconnect ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Disconnect? The messenger disappears from the site until you connect again.', 'goya24' ) ); ?>');"><?php esc_html_e( 'Disconnect', 'goya24' ); ?></a>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	/**
	 * The WooCommerce part of the form.
	 *
	 * @param array<string, mixed> $settings Every setting.
	 *
	 * @return void
	 */
	private function store_section( array $settings ) {
		$connect_store = wp_nonce_url( admin_url( 'admin-post.php?action=goya24_connect_store' ), 'goya24_connect_store' );
		$can           = Goya24_WooCommerce::can_connect_store();
		$done          = '' !== $settings['store_connected_at'];
		?>
		<h2><?php esc_html_e( 'WooCommerce', 'goya24' ); ?></h2>
		<p class="description"><?php esc_html_e( 'With the store connected, the agent looks up the order a customer asks about, checks what is in stock, and — only when a teammate approves — changes an order\'s status. WooCommerce makes the keys itself on its own screen; nothing is copied by hand.', 'goya24' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Orders', 'goya24' ); ?></th>
				<td>
					<?php if ( $done ) : ?>
						<p>
							<span class="goya24-dot goya24-dot--on" aria-hidden="true"></span>
							<?php
							printf(
								/* translators: %s: date */
								esc_html__( 'Connected on %s.', 'goya24' ),
								esc_html( $this->date( $settings['store_connected_at'] ) )
							);
							?>
						</p>
						<p><a class="button" href="<?php echo esc_url( $connect_store ); ?>"><?php esc_html_e( 'Connect the store again', 'goya24' ); ?></a></p>
						<p class="description"><?php esc_html_e( 'The keys live in WooCommerce → Settings → Advanced → REST API, named goya24. Revoke them there to cut the store off.', 'goya24' ); ?></p>
					<?php elseif ( $can ) : ?>
						<p><a class="button button-primary" href="<?php echo esc_url( $connect_store ); ?>"><?php esc_html_e( 'Connect the store', 'goya24' ); ?></a></p>
						<p class="description"><?php esc_html_e( 'WooCommerce will ask you to approve read and write access for goya24, then bring you back here.', 'goya24' ); ?></p>
					<?php else : ?>
						<p><span class="button button-primary disabled" aria-disabled="true"><?php esc_html_e( 'Connect the store', 'goya24' ); ?></span></p>
						<p class="description"><?php esc_html_e( 'Connect the site to a workspace with the button at the top first; the store connection is signed with what that brings over.', 'goya24' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * One row of radio buttons.
	 *
	 * @param string                $name    Setting name.
	 * @param string                $label   Row label.
	 * @param array<string, string> $choices Value to label.
	 * @param string                $current The stored value.
	 *
	 * @return void
	 */
	private function radio_row( $name, $label, array $choices, $current ) {
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo esc_html( $label ); ?></span></legend>
					<?php foreach ( $choices as $value => $text ) : ?>
						<label class="goya24-radio">
							<input type="radio" name="<?php echo esc_attr( Goya24_Options::OPTION . '[' . $name . ']' ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( $current, $value ); ?>>
							<?php echo esc_html( $text ); ?>
						</label>
					<?php endforeach; ?>
				</fieldset>
			</td>
		</tr>
		<?php
	}

	/**
	 * The notice for what just happened, from the query string.
	 *
	 * @return void
	 */
	private function flash() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- reading which notice to show; nothing changes.
		$what   = isset( $_GET['goya24'] ) ? sanitize_key( $_GET['goya24'] ) : '';
		$reason = isset( $_GET['reason'] ) ? sanitize_text_field( wp_unslash( $_GET['reason'] ) ) : '';
		// phpcs:enable
		if ( '' === $what ) {
			return;
		}

		$messages = array(
			'connected'              => array( 'success', __( 'Connected. The messenger is on your site now — open a page and look in the corner.', 'goya24' ) ),
			'disconnected'           => array( 'info', __( 'Disconnected. The messenger is off the site until you connect again.', 'goya24' ) ),
			'store-connected'        => array( 'success', __( 'Store connected. The agent can now find a customer\'s order.', 'goya24' ) ),
			'store-failed'           => array( 'error', __( 'WooCommerce did not connect the store.', 'goya24' ) ),
			'store-needs-connection' => array( 'error', __( 'Connect the site to a workspace first, then the store.', 'goya24' ) ),
			'error'                  => array( 'error', __( 'goya24 could not connect this site.', 'goya24' ) ),
		);
		if ( ! isset( $messages[ $what ] ) ) {
			return;
		}
		list( $kind, $text ) = $messages[ $what ];
		if ( '' !== $reason ) {
			$text .= ' ' . $reason;
		}
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $kind ),
			esc_html( $text )
		);
	}

	/**
	 * A stored ISO timestamp as the site would write a date.
	 *
	 * @param string $iso The timestamp.
	 * @return string
	 */
	private function date( $iso ) {
		$time = strtotime( (string) $iso );
		if ( false === $time ) {
			return (string) $iso;
		}
		return wp_date( get_option( 'date_format' ), $time );
	}
}
