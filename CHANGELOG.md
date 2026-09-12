# Changelog

All notable changes to the goya24 WordPress plugin. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [Semantic Versioning](https://semver.org).

## [Unreleased]

## [0.1.0] — 2026-09-12

### Added

- The messenger on every public page, loaded `async` from goya24, with the site's language, the chosen colours and corner.
- One-button connection: _Settings → goya24 → Connect to goya24_. goya24 posts the workspace key and identity secret to `POST /wp-json/goya24/v1/connect`, guarded by a one-time state; nothing is copied by hand. The key and secret can still be pasted under _Advanced_, or pinned with `GOYA24_KEY` / `GOYA24_IDENTITY_SECRET` / `GOYA24_ORIGIN` in `wp-config.php`.
- Signed-in users introduced with proof: the server signs the user's id with the workspace secret. `goya24_user` can rewrite or veto what is sent, `goya24_should_load` keeps the messenger off a page, `goya24_tag_attributes` edits the tag.
- WooCommerce: the customer's checkout name on the tag, HPOS compatibility declared, and _Connect the store_ through WooCommerce's own `wc-auth` screen with a short-lived, site-bound token that goya24 verifies before storing the keys.
- `GET /wp-json/goya24/v1/status` for goya24's install check.
- Persian translation of the settings screen.

[Unreleased]: https://github.com/Goya-24/goya24-wordpress/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/Goya-24/goya24-wordpress/releases/tag/v0.1.0
