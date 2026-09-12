=== goya24 ===
Contributors: goya24
Tags: live chat, ai chatbot, customer support, woocommerce, persian
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI customer support in Persian and English. Answers from your own content, hands off to your team, and finds a WooCommerce customer's order.

== Description ==

goya24 puts an AI support messenger in the corner of your site. It answers customers from what your site and documents already say, in Persian and English, and hands the conversation to a person on your team when it should — with the customer's email captured first, so nobody waits at a closed page.

**What this plugin does**

* Adds the messenger to every public page. Nothing to paste into a theme.
* Connects your site to your goya24 workspace with one button — you sign in, choose the workspace, and come back connected. No keys to copy.
* Introduces signed-in users to the messenger with proof: your server signs the user's id, so the agent greets them by name and nobody can pretend to be somebody else.
* Follows your site's language (Persian or English), colours and corner, or the ones you choose.

**With WooCommerce**

* The customer is introduced by the name they gave at checkout.
* Connect the store with WooCommerce's own authorization screen and the agent can find the order a customer asks about, check stock, and — only when a teammate approves — change an order's status. WooCommerce makes the API keys itself; nothing is copied by hand.

**For developers**

* `goya24_should_load` — keep the messenger off some pages.
* `goya24_user` — change or veto what the messenger is told about the signed-in user (the id is signed after your filter runs).
* `goya24_tag_attributes` — the data attributes on the tag.
* `GOYA24_KEY`, `GOYA24_IDENTITY_SECRET` and `GOYA24_ORIGIN` in `wp-config.php` pin the settings for a staging site.

Source and issues: [github.com/Goya-24/goya24-wordpress](https://github.com/Goya-24/goya24-wordpress).

= External service =

This plugin connects your site to **goya24**, a hosted customer-support service at [goya24.com](https://goya24.com). You need a goya24 workspace to use it.

Once connected, every public page loads `https://goya24.com/widget.js`, which opens the messenger in a frame served by goya24. The messenger sends goya24 the visitor's messages, the path and title of the page they are on, and — if "Tell the messenger who is signed in" is on — the signed-in user's id, name and email. With the store connected, goya24 reads orders, products and customers through WooCommerce's REST API using keys WooCommerce issued to it.

Nothing is sent before the site is connected. [Terms of service](https://goya24.com/terms) · [Privacy policy](https://goya24.com/privacy)

== Installation ==

1. In wp-admin go to **Plugins → Add New**, search for **goya24**, install and activate it.
2. Go to **Settings → goya24** and press **Connect to goya24**. Sign in (or create a workspace), choose the workspace, and you come back connected.
3. Open your site: the messenger is in the corner.
4. With WooCommerce: on the same settings page, press **Connect the store** and approve on WooCommerce's screen.

To install from a zip instead: **Plugins → Add New → Upload Plugin**.

== Frequently Asked Questions ==

= Do I need a goya24 account? =

Yes. The plugin is the connection between your site and your goya24 workspace; the agent, its knowledge and your team live there. A workspace takes a minute to create at [goya24.com](https://goya24.com).

= Which languages does the messenger speak? =

Persian and English. It follows the site's language by default; you can fix one in the settings.

= Does the plugin slow my site down? =

No. It adds one small script tag, loaded `async` at the end of the page, and the messenger itself lives in a frame the script opens. Your pages render exactly as before.

= What does "with proof" mean for signed-in users? =

Your server signs the user's id with a secret only it and goya24 hold. goya24 checks the signature before trusting the name and email, so a visitor cannot edit the page and claim to be another customer. Without the secret the details are still sent, marked as a claim.

= Can I hide the messenger on some pages? =

Yes: `add_filter( 'goya24_should_load', fn( $load ) => $load && ! is_page( 'checkout' ) );`

= Where are the WooCommerce keys? =

Under **WooCommerce → Settings → Advanced → REST API**, named goya24. Revoke them there to cut the store off.

= What happens when I delete the plugin? =

Everything it stored on this site is removed: the key, the secret and the settings. Your workspace on goya24 is untouched.

== Screenshots ==

1. Settings → goya24 before connecting: one button.
2. Connected: the workspace, the identity proof and the store at a glance.
3. The consent page on goya24 — choose the workspace, done.
4. The messenger on a WooCommerce store, in Persian.

== Changelog ==

= 0.1.0 =
* First release: the messenger on every page, one-button connection, signed identity for signed-in users, WooCommerce customer names and one-click store connection.

== Upgrade Notice ==

= 0.1.0 =
First release.
