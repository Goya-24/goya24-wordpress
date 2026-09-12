# Security

## Reporting a vulnerability

Please **do not open a public issue** for a security problem.

Email **security@goya24.com** with what you found, how to reproduce it, and what you think the impact is. You will get an acknowledgement within two working days and a fix or a plan within a week for anything confirmed.

## What is in scope

- This plugin: the settings screen, the REST endpoints under `/wp-json/goya24/v1/`, the tag it prints, and the two hand-offs (connecting the site, connecting the store).

The messenger itself, the goya24 API and goya24.com are covered by the same address but are not in this repository.

## What the plugin holds and does

- It stores the workspace key, the identity secret and its settings in one WordPress option. The secret is never printed on the settings screen or returned by any endpoint.
- `POST /wp-json/goya24/v1/connect` is unauthenticated by design — goya24's server calls it, not a signed-in user — and accepts exactly one post per pressed _Connect_ button, matched against a 32-character random state that expires in 15 minutes. `GET …/status` returns only what the page's tag already shows.
- Every admin action checks `manage_options` and a nonce.
- On the page it prints one `<script>` from the configured origin (goya24.com unless pinned otherwise) with the signed-in user's id, name, email and the HMAC of the id. The secret itself never reaches the browser.
- The store hand-off is WooCommerce's own `wc-auth` flow: the keys WooCommerce creates go straight from WooCommerce to goya24 and never pass through this plugin.

## Supported versions

The latest release receives security fixes.
