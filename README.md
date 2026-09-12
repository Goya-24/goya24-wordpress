<p align="center">
  <img src="https://raw.githubusercontent.com/Goya-24/.github/main/profile/goya24-icon.svg" width="72" alt="">
</p>

<h1 align="center">goya24 for WordPress and WooCommerce</h1>

<p align="center">
  The <a href="https://goya24.com">goya24</a> AI support messenger on your site, connected with one button — and, on a WooCommerce store, an agent that knows who is asking and finds their order.
</p>

<p align="center">
  <a href="https://github.com/Goya-24/goya24-wordpress/actions/workflows/ci.yml"><img src="https://github.com/Goya-24/goya24-wordpress/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
  <a href="https://github.com/Goya-24/goya24-wordpress/releases/latest"><img src="https://img.shields.io/github/v/release/Goya-24/goya24-wordpress?label=release" alt="Release"></a>
  <img src="https://img.shields.io/badge/WordPress-6.3%2B-21759B" alt="WordPress 6.3+">
  <img src="https://img.shields.io/badge/PHP-7.4%2B-777BB4" alt="PHP 7.4+">
  <a href="./LICENSE"><img src="https://img.shields.io/badge/license-GPL--2.0--or--later-0e7c86" alt="GPL-2.0-or-later"></a>
</p>

## What it does

- **The messenger on every public page.** One `async` script tag, added by the plugin; the messenger itself lives in a frame served by goya24, so a fix on the service reaches every site without a plugin update.
- **One-button connection.** Settings → goya24 → _Connect to goya24_. You sign in, choose the workspace, and come back connected: the workspace key and the identity secret are posted to the site server-to-server, never through the address bar.
- **Signed-in users, with proof.** The server signs the user's id with the workspace's secret (`hmac_sha256(secret, id)`); goya24 checks it before trusting the name and email. Nobody can edit the page and claim to be another customer.
- **WooCommerce.** The customer is introduced by the name they gave at checkout, and _Connect the store_ takes the owner to WooCommerce's own authorization screen — WooCommerce issues the REST keys and posts them to goya24 itself. From then on the agent looks up the order a customer asks about, checks stock, and (only with a teammate's approval) changes an order's status.
- **Persian and English**, following the site's language, with the admin screen translated.

## Install

**From WordPress.org** (once listed): Plugins → Add New → search _goya24_ → Install → Activate.

**From a release zip**: download `goya24.zip` from the [latest release](https://github.com/Goya-24/goya24-wordpress/releases/latest), then Plugins → Add New → Upload Plugin.

Then Settings → goya24 → **Connect to goya24**. That's the whole setup.

## For developers

```php
// Keep the messenger off the checkout.
add_filter( 'goya24_should_load', fn( $load ) => $load && ! is_page( 'checkout' ) );

// Introduce users by your CRM id instead of the WordPress one; the id is
// signed after this filter runs, so it stays proven. Return null to send nothing.
add_filter( 'goya24_user', function ( $user, WP_User $wp_user ) {
    $user['id']   = get_user_meta( $wp_user->ID, 'crm_id', true ) ?: $user['id'];
    $user['plan'] = get_user_meta( $wp_user->ID, 'plan', true );
    return $user;
}, 10, 2 );

// Anything the loader reads: data-theme, data-alignment, data-horizontal-padding …
add_filter( 'goya24_tag_attributes', fn( $attrs ) => $attrs + [ 'data-vertical-padding' => '32' ] );
```

`wp-config.php` constants pin a setting so the screen cannot change it — for a staging site pointing at a staging workspace, say:

```php
define( 'GOYA24_KEY', 'd24_pk_…' );
define( 'GOYA24_IDENTITY_SECRET', '…' );
define( 'GOYA24_ORIGIN', 'https://goya24.com' ); // only for a self-hosted goya24
```

The site also answers `GET /wp-json/goya24/v1/status` with the plugin version, whether it is connected and whether WooCommerce is present — nothing the page's own tag does not already show.

## How the connection works

```
wp-admin ──"Connect"──▶ goya24.com/connect/wordpress?site=…&state=…&callback=…
                             │  admin signs in, picks a workspace
                             ▼
goya24 ──POST {state, key, secret, workspace}──▶ site/wp-json/goya24/v1/connect
                             │  the plugin checks the one-time state, stores the rest
                             ▼
goya24 ──redirect──▶ wp-admin/options-general.php?page=goya24&goya24=connected
```

The store uses WooCommerce's own flow: the plugin sends the owner to `/wc-auth/v1/authorize` with a short-lived token that names the workspace and is signed with the shared secret; WooCommerce creates the keys and posts them to `https://goya24.com/api/hooks/woocommerce`, where goya24 verifies the token before storing anything.

## Development

```sh
composer install
composer lint      # WordPress coding standards + PHP 7.4 compatibility (phpcs)
composer analyse   # phpstan, level 6, WordPress and WooCommerce stubs
composer test      # WordPress integration tests (needs a MySQL, see tests/wp-tests-config.php)
```

A WordPress to try the plugin in, with the repository mounted as the plugin:

```sh
docker compose up -d
docker compose run --rm cli wp core install --url=http://localhost:8080 --title=Shop \
  --admin_user=admin --admin_password=admin --admin_email=admin@example.com
docker compose run --rm cli wp plugin activate goya24
docker compose run --rm cli wp plugin install woocommerce --activate   # optional
```

Tests run against the same database container:

```sh
docker compose run --rm cli mariadb -h db -uroot -proot -e 'CREATE DATABASE IF NOT EXISTS wordpress_test'
docker compose run --rm -w /var/www/html/wp-content/plugins/goya24 \
  -e WP_TESTS_DB_HOST=db -e WP_TESTS_DB_NAME=wordpress_test -e WP_TESTS_DB_USER=root -e WP_TESTS_DB_PASSWORD=root \
  -e WP_TESTS_CONTENT_DIR=/tmp/wp-content cli php vendor/bin/phpunit
```

`bin/build-zip.sh` makes the same `goya24.zip` the release workflow attaches. See [CONTRIBUTING.md](CONTRIBUTING.md) for how changes ship and [SECURITY.md](SECURITY.md) for reporting vulnerabilities.

## License

[GPL-2.0-or-later](LICENSE) © Goya24. The JavaScript SDK lives in [goya24-js](https://github.com/Goya-24/goya24-js) under MIT.
