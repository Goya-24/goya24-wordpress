# Contributing

Thanks for looking. This repo holds the goya24 WordPress plugin — the messenger on the page, the one-button connection, the signed identity, and the WooCommerce store hand-off.

## Setup

- PHP 7.4 or newer and [Composer](https://getcomposer.org). `composer install` brings phpcs, phpstan and WordPress's own test framework.
- Docker, if you want a WordPress to click around in: `docker compose up -d` and the commands in the README mount this repository as the plugin.

## Working on it

- `composer lint` — WordPress coding standards and PHP 7.4+ compatibility. `composer lint:fix` fixes what it can.
- `composer analyse` — phpstan at level 6 with the WordPress and WooCommerce stubs. New code needs its parameters and return values documented; that is what makes the analysis worth running.
- `composer test` — the integration tests in `tests/`, run inside a real WordPress against a throwaway MySQL database (`tests/wp-tests-config.php` reads `WP_TESTS_DB_*` from the environment). The README shows how to run them in the Docker setup.
- Strings: everything a person sees goes through `__()` with the `goya24` domain. After changing one, regenerate `languages/goya24.pot` (`wp i18n make-pot . languages/goya24.pot`), update `languages/goya24-fa_IR.po`, and rebuild the `.mo` (`wp i18n make-mo languages`). Persian copy is reviewed by a native speaker before release; write the English first.

## What a change needs

1. **A test.** A bug fix comes with the test that would have caught it. A feature comes with tests for what it does and for what it refuses.
2. **A changelog line** under _Unreleased_ in `CHANGELOG.md` and, for anything a site owner would notice, in the changelog section of `readme.txt`.
3. **The docs.** `readme.txt` is what WordPress.org shows; `README.md` is for people reading the source. If behaviour changed, both say the new thing.

## Releasing

Bump the version in `goya24.php` (the header and `GOYA24_VERSION`) and `readme.txt` (`Stable tag`), move the _Unreleased_ notes under the version, and tag `vX.Y.Z`. The release workflow builds `goya24.zip` from `.distignore` and attaches it to a GitHub release. Deploying that release to the WordPress.org directory is a separate step in the same workflow, switched on once the plugin is listed there.

## Things that are already decided

- **One plugin.** WooCommerce support lives here, switched on when WooCommerce is present. There is no separate WooCommerce plugin, and nothing here requires WooCommerce.
- **The plugin carries no UI of the messenger.** It prints one script tag; the messenger is served by goya24 in its own frame. A change to how the messenger looks belongs in goya24, not here.
- **The loader's names do not change.** `widget.js` installs `window.dastyar24` and posts messages with `source: "dastyar24"`; those predate the goya24 brand and are a contract with every site that embeds the script.
- **Secrets stay on the server.** The identity secret signs the user's id in PHP and is never printed, never returned by an endpoint, never put on the page.
- **Everything is prefixed `goya24_` / `Goya24_`.** Functions, classes, options, transients, hooks, REST namespace. phpcs enforces it.

## Reporting a bug

Open an issue with the WordPress, WooCommerce and PHP versions, what you pressed, and what happened instead. Anything under _Tools → Site Health → Info_ that looks relevant helps.

## Code of conduct

Be kind and be specific. See [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).
