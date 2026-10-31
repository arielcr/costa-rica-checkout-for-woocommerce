# Architecture Decision Records

Short ADRs for design decisions not covered by `docs/SPEC.md`. Format: context, decision, alternatives, consequences.

---

## ADR-0001: Module toggles in a single array option

- **Context:** Each module must be enabled or disabled separately from WooCommerce → Settings → Costa Rica (SPEC §3).
- **Decision:** One autoloaded option, `crkit_modules`, shaped `[ module_id => 'yes'|'no' ]`. Fields use the WooCommerce Settings API array-id syntax (`crkit_modules[sinpe_movil]`), so WooCommerce handles rendering, nonce, capability and saving.
- **Alternatives:** one option per module (four reads, four rows); a custom page with the WordPress Settings API (duplicates what WooCommerce already provides).
- **Consequences:** one option read per request. A missing key falls back to the module default, so new modules work without a migration.

## ADR-0002: Default module state

- **Decision:** `exchange_rate` and `sinpe_movil` are enabled by default; `locations` and `identification` are disabled.
- **Why:** the first two add nothing visible until the merchant enables the gateway in WooCommerce → Payments. The last two change checkout fields, so they are opt-in to avoid surprising a live store.
- **Alternatives:** all on (changes the checkout on activation); all off (extra step for the main use case).

## ADR-0003: PSR-4 file names vs. WPCS file naming

- **Context:** the SPEC requires PSR-4 autoloading (`src/Plugin.php`), while WPCS expects `class-plugin.php`.
- **Decision:** exclude `WordPress.Files.FileName` for `src/` and `tests/` only. All other WPCS rules apply unchanged (long array syntax, Yoda conditions, snake_case methods).

## ADR-0004: Boot on `woocommerce_loaded`

- **Decision:** the main file registers `crkit_boot()` on `woocommerce_loaded` and `Plugin::declare_wc_compatibility()` on `before_woocommerce_init`.
- **Why:** `woocommerce_loaded` only fires when WooCommerce is active, so the plugin exits cleanly without a `class_exists()` check, and it always runs after WooCommerce's classes are available, regardless of plugin load order.
- **Alternatives:** `plugins_loaded` + `class_exists( 'WooCommerce' )` (equivalent, one more branch).

## ADR-0005: Public functions file required from the bootstrap

- **Decision:** `src/functions.php` (`crkit_plugin()`, `crkit_boot()`, and the future `crkit_get_exchange_rate()`) is loaded with `require_once` from the main file, not with Composer `autoload.files`.
- **Why:** every PHP file starts with `defined( 'ABSPATH' ) || exit;`. Composer `files` are included as soon as `vendor/autoload.php` loads, which would silently stop PHPStan and any tool that loads the autoloader without WordPress.

## ADR-0006: PHPUnit 9.6 with two configurations

- **Context:** the WordPress 7.1 test suite still declares `yoast/phpunit-polyfills ^1.1` and is built for PHPUnit ≤ 9.
- **Decision:** PHPUnit 9.6 for both suites. `phpunit.xml.dist` runs the unit suite (Brain Monkey, no WordPress); `phpunit-integration.xml.dist` runs the integration suite inside wp-env. PHPUnit allows one bootstrap per configuration, hence two files.
- **Consequences:** `composer test` is fast and needs no Docker; `npm run test:php:integration` needs wp-env.

## ADR-0007: Single wp-env environment with a dedicated test table prefix

- **Context:** wp-env 12 deprecates the separate tests environment (`testsEnvironment`) and warns when it is on. With a single environment, the WordPress test installer shares the site database and prefix (`wp_`) and **drops the development site's tables**.
- **Decision:** `"testsEnvironment": false`, and the integration bootstrap sets `WORDPRESS_TABLE_PREFIX=wptests_` before loading the test suite.
- **Alternatives:** a second config file with `--config` (a second set of containers for a smoke suite).

## ADR-0008: Pinned versions in `.wp-env.json`

- **Context:** `downloads.wordpress.org/plugin/woocommerce.zip` served **11.3.0-beta.1** on 2026-10-31, not the stable release. `woocommerce.latest-stable.zip` would install into a `woocommerce.latest-stable` folder, which breaks `Requires Plugins: woocommerce`.
- **Decision:** pin WordPress 7.1.4, WooCommerce 11.2.1 and Plugin Check 2.1.0. Bump them manually together with `WC tested up to`.

## ADR-0009: Plugin Check runs against the built package

- **Decision:** `npm run plugin-check` builds the zip staging folder (`build/costa-rica-kit-for-woocommerce/`) and runs `wp plugin check <path> --slug=costa-rica-kit-for-woocommerce` on it. CI does the same with `WordPress/plugin-check-action@v1` and `build-dir`.
- **Why:** the development checkout contains dotfiles, tests and dev configs that are never shipped; checking them would report errors that do not exist in the release.

## ADR-0010: PHPStan `treatPhpDocTypesAsCertain: false`

- **Context:** `szepeviktor/phpstan-wordpress` infers `apply_filters()` return types from the hook docblock, but a filter can return anything at runtime.
- **Decision:** disable `treatPhpDocTypesAsCertain`, so runtime guards on filtered values (for example, discarding non-module entries from `crkit_registered_modules`) are not reported as redundant. Native types are still trusted.

## ADR-0011: PHPCompatibilityWP 2.1 (stable)

- **Decision:** `phpcompatibility/phpcompatibility-wp ^2.1`, the version WordPress core uses. 3.0 is still alpha.
- **Consequence:** PHPCompatibility 9.3 does not detect PHP 8.2+ only syntax. The CI unit job on PHP 8.1 covers that gap.

## ADR-0012: English plugin header description

- **Context:** SPEC §2 gives the header `Description` in Spanish; SPEC §4 requires English source strings. The header description is translatable through the plugin text domain.
- **Decision:** English description in the header and readme; Spanish comes from the `es_CR` translation.

## ADR-0013: `WC requires at least: 9.0`

- **Decision:** 9.0, the minimum allowed by the SPEC. Every WooCommerce API planned for phases 1–3 exists in 9.0 (HPOS, `FeaturesUtil`, block payment method integration, Additional Checkout Fields API from 8.9).
- **Consequence:** only the latest WooCommerce is exercised in the test matrix. Open question for the owner: raise the minimum to reduce the support surface (see the phase 0 report).

## ADR-0014: Contents of the distributable zip

- **Decision:** the zip ships `composer.json` (documents the autoloader) but not `composer.lock`. `vendor/` contains only the Composer autoloader generated with `--no-dev --optimize-autoloader`. Empty folders are pruned.
