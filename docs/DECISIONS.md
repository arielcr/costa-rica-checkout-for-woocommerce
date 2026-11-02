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

## ADR-0007: Integration tests on a dedicated database

- **Context:** wp-env 12 deprecates the separate tests environment (`testsEnvironment`). With a single environment, wp-env's default `wp-tests-config.php` points the WordPress test suite to the site database (`wordpress`, prefix `wp_`), and the test installer **drops every table of its database** on each run. A first attempt with a different table prefix (`wptests_`) protected the site tables but still shared the database.
- **Decision:** `"testsEnvironment": false`, and the suite loads its own configuration (`tests/Integration/wp-tests-config.php`, through `WP_TESTS_CONFIG_FILE_PATH`) with a dedicated database, `crkit_tests`, on the same MySQL server. The bootstrap creates it if missing. The config refuses to run if the site database ever has the same name, and `TestEnvironmentTest` asserts the database and prefix in use.
- **Verified:** table statistics and checksums of the `wordpress` database are identical before and after a full integration run.
- **Alternatives:** a second wp-env config (`--config`) only for tests (a second set of containers for a smoke suite); prefix-only isolation (rejected: same database).

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

## ADR-0013: Minimum versions: WooCommerce 10.0, WordPress 6.7, tested in CI

- **Decision (owner, phase 0 review):** `WC requires at least: 10.0`, and `Requires at least: 6.7`, the minimum that WooCommerce 10.0.0 declares (`plugins.svn.wordpress.org/woocommerce/tags/10.0.0`).
- **Test:** `.wp-env.min.json` runs the integration suite on the exact floor: WordPress 6.7 (6.7.0), WooCommerce 10.0.0, PHP 8.1, on port 8890. CI job `integration-min`.
- **Why the exact floor:** it is what the headers promise. Later patch releases only add fixes.

## ADR-0014: Contents of the distributable zip

- **Decision:** the zip ships `composer.json` (documents the autoloader) but not `composer.lock`. `vendor/` contains only the Composer autoloader generated with `--no-dev --optimize-autoloader`. Empty folders are pruned.

## ADR-0015: npm `overrides` for `serialize-javascript`

- **Context:** `npm audit` reported one high-severity issue in `serialize-javascript@6.0.2` (dev only, used by `copy-webpack-plugin` inside `@wordpress/scripts`). `npm audit fix` only offers a breaking downgrade of `@wordpress/scripts`.
- **Decision:** `"overrides": { "serialize-javascript": "^7.1.4" }`. 7.x keeps the same CommonJS API (`serialize( value, options )`) and requires Node 20+, which the project already needs. The JS build was verified after the change.
- **Remaining:** low and moderate advisories in dev dependencies, without a compatible fix. They do not ship in the plugin zip.

## ADR-0016: Agent skills

- **Decision:** WordPress agent skills (`wp-plugin-development`, `wp-plugin-directory-guidelines`, `wp-phpstan`, `wp-env`) are installed at project level in `.claude/skills/` (gitignored). `skills-lock.json` is committed so they can be reinstalled with the same content; it is excluded from the zip.

---

# Phase 1

## ADR-0017: One manual rate for buying and selling

- **Decision:** the manual source is a single value (₡ per US$), used as both buy and sell rate.
- **Why:** SPEC §3B says the merchant "fija el valor" (one value). A store that fixes its own rate rarely distinguishes buy and sell.
- **Alternatives:** two fields (more UI and validation for a rare need).

## ADR-0018: "Usable rate" semantics in the public API

- **Decision:** `crkit_get_exchange_rate()` and `crkit_convert_to_crc()` return `null` when there is no rate or the automatic rate is older than the maximum age. `ExchangeRateService::get_current()` still exposes the last rate (with its date) for display.
- **Why:** a stale rate must never be used to charge a customer; callers get one clear signal.

## ADR-0019: Rate age measured from the last successful request

- **Decision:** a rate is outdated when `fetched_at` is older than the maximum age (default 48 h), not by the date the source publishes.
- **Why:** sources keep publishing the same date during weekends and holidays; what matters is that the store still talks to the source. A manual rate never goes stale.

## ADR-0020: Supported currencies: CRC and USD

- **Decision:** CRC totals need no rate; USD totals use the cached rate. Other currencies return `null` (SINPE Móvil is not offered) unless a plugin adds them through `crkit_convert_to_crc`.
- **Why:** the Hacienda and BCCR sources used here only cover the US dollar for both buy and sell.

## ADR-0021: Rounding to whole colones

- **Decision:** `ceil( round( amount, 2 ) )`: round to cents first, then up.
- **Why:** SPEC §3A asks to round up; rounding to cents first avoids charging an extra colón because of floating point noise (e.g. 4607.000000001). The block checkout script uses the same formula.

## ADR-0022: Payment reference = order number

- **Decision:** the reference shown to the customer is `$order->get_order_number()` (filters from sequential order number plugins apply).
- **Why:** short enough for the SINPE Móvil description field and easy to find in WooCommerce.

## ADR-0023: Rejected payments default to "Failed"

- **Decision:** the "Rejected payments" setting defaults to `failed` (the customer can pay again from My Account); `cancelled` is the alternative.
- **Why:** a rejected SINPE payment is usually a wrong amount or a missing transfer; letting the customer retry is the least destructive default.

## ADR-0024: Receipts can be replaced while the order is on hold

- **Decision:** a new upload replaces the previous receipt and deletes its file. Uploads are refused once the order leaves `on-hold`.
- **Why:** customers often upload the wrong screenshot first; keeping only the latest one avoids orphan files.

## ADR-0025: Receipt endpoints on admin-post.php

- **Decision:** uploads go to `admin-post.php` (`admin_post` and `admin_post_nopriv`, so guests can upload with their order key). Viewing is `admin_post` only: store staff (`edit_shop_orders`) or the logged-in owner, with a nonce. Guests cannot view the receipt they uploaded.
- **Why:** standard WordPress endpoints with nonces; no REST route or rewrite rules to maintain. SPEC §3A limits viewing to staff and the owner.

## ADR-0026: Content check on top of wp_check_filetype_and_ext()

- **Decision:** after `wp_check_filetype_and_ext()`, the file content is checked again with `fileinfo` and must match the allowed MIME type. Files are moved with `wp_handle_upload()` (random 32-hex names) into the private folder.
- **Note:** in WordPress 6.7+ `wp_check_filetype_and_ext()` already rejects every invalid case in the test suite (a mutation test that removes the second check still passes). The check stays as defense in depth: it also makes PDFs fail closed when `fileinfo` is missing.

## ADR-0027: Self-healing schedule on admin requests only

- **Decision:** besides activation and module toggles, the recurring refresh is re-created on `admin_init` if missing (not on AJAX). Storefront requests never query Action Scheduler.

## ADR-0028: Exchange rate settings hooks are not limited to is_admin()

- **Decision:** sanitization and "refresh after the source changes" are registered on every request.
- **Why:** WooCommerce settings can also be saved through the REST API, which is not an admin request. Found by an integration test.

## ADR-0029: Privacy eraser follows WooCommerce

- **Decision:** the eraser deletes receipts only when "Remove personal data from orders on request" is enabled in WooCommerce; otherwise it reports them as retained, like WooCommerce does with orders.

## ADR-0030: Store email as a WC_Email with a transactional action

- **Decision:** `crkit_sinpe_receipt_uploaded` is added to `woocommerce_email_actions`, and the email class listens to `crkit_sinpe_receipt_uploaded_notification`.
- **Why:** it is configurable in WooCommerce → Settings → Emails and honors deferred transactional emails.

## ADR-0031: Block checkout estimate computed in the browser

- **Decision:** `get_payment_method_data()` passes the effective rate (rate × surcharge) and the maximum amount; the script computes the estimate from the live cart total and `canMakePayment` applies the same limit. The server-side `is_available()` keeps the authoritative rules.
- **Why:** the cart total changes with shipping and coupons after the page loads; a static amount would go stale.

## ADR-0032: Bundled es_CR translation with load_plugin_textdomain()

- **Decision:** `load_plugin_textdomain()` on `init`, `.po/.mo` plus a JSON file for the block checkout script. Customer-facing strings use "usted".
- **Why:** SPEC §4 asks to ship es_CR until the community translation exists. Translations from translate.wordpress.org take precedence once installed.

## ADR-0033: Hacienda rate date

- **Decision:** the date stored for a Hacienda rate is the `dolar.venta.fecha` the service reports, with no "not after today" filter (that filter applies to the BCCR series, which includes tomorrow).

## ADR-0034: Asset manifest as JSON

- **Decision:** the build writes `sinpe-movil-blocks.asset.json` (dependency extraction `outputFormat: json`), read with `wp_json_file_decode()`.
- **Why:** a `require` of a generated PHP file breaks static analysis in CI before the build runs.

## ADR-0035: End-to-end matrix with Playwright and a dev mail catcher

- **Decision:** `bin/e2e-matrix.sh` runs the SPEC §8 matrix (PHP × currency × order storage × checkout × user) against wp-env; a development-only mu-plugin (`tests/e2e/mu-plugins/crkit-dev-mail.php`, mapped by `.wp-env.json`, never shipped) saves emails as HTML files for review.
- **Why:** checkout UI and user type can only be verified in a browser; wp-env has no mail server.

## ADR-0036: es_ES ships with the same texts as es_CR (phase 1 review)

- **Decision (owner):** `languages/` includes `es_ES` with the es_CR texts ("usted").
- **Why:** many Costa Rican sites run WordPress in es_ES and would otherwise see the plugin in English. `load_plugin_textdomain()` stays until phase 4, when the community translations are reviewed.
- **Verified:** the e2e matrix asserts the translated texts (payment steps, receipt confirmation, and the block checkout estimate, which comes from the JSON file) on a site in es_ES.

## ADR-0037: BCCR HTTP 500 is reported as "invalid token or service failure"

- **Context:** the BCCR API answers HTTP 500 for an invalid bearer token (verified 2026-10-31), the same status it uses for its own failures.
- **Decision:** for the BCCR source, the admin message for 5xx names both causes and asks to check the token first. Other sources get a generic internal-error message. The API base keeps the `Bccr.GE` casing, which the owner's production app uses.

## ADR-0038: E2E waits for WooCommerce's totals recalculation before placing block orders

- **Context:** the first es_ES matrix run failed 4 times in "block checkout, guest": the order was never submitted. The trace showed that selecting a payment method in WooCommerce 11.2.1 sends `POST /wc/store/v1/checkout?__experimental_calc_totals=true`, and a "Place order" click made while it is in flight is ignored. A control script reproduced it with Direct bank transfer (BACS) too, so it is not caused by this plugin.
- **Decision:** after selecting the method, the e2e helper waits for the network to settle; if a click is still ignored, it clicks again (up to 3 times). A real gateway failure never reaches the order received page, so it still fails the test.
- **Note for later:** report it upstream to WooCommerce if it still happens on the next version.

---

# Phase 2

## ADR-0039: Locations dataset generated from the official decree

- **Decision:** `data/cr-locations.json` is generated by `bin/extract-dta-2025.py` from the text of the official PDF (Decreto Ejecutivo 44882-MGP, Alcance 12 a La Gaceta 17, 28 de enero de 2025, Imprenta Nacional). Result: 7 provinces, 84 cantons, 492 districts.
- **Details:** codes are kept as in the decree (province 1 digit, canton 2, district 2); the postcode is their concatenation. Three codes are declared vacant ("desierto") and have no district: 203 06 (Río Cuarto became canton 216), 601 09 (Monteverde became canton 612) and 607 02 (Puerto Jiménez became canton 613). The decree prints "205 06: SAN JOSÉ:" with an extra colon; the extractor tolerates it. Names are converted from upper case to proper case, with two exceptions where the article is part of the name (San Isidro de El General, Valle La Estrella).
- **Provinces:** WooCommerce already ships the 7 provinces as states (`CR-SJ`, `CR-A`, `CR-C`, `CR-H`, `CR-G`, `CR-P`, `CR-L`); the dataset maps each province code to them instead of duplicating them.

## ADR-0040: Block checkout: one district field per province (owner's choice)

- **Context:** the Additional Checkout Fields API (WC 10.0+) supports `select` fields with JSON Schema `hidden`/`required` rules evaluated against the address being edited, but cannot filter a select's options by another field's value. It only applies to block (Store API) orders.
- **Options presented:** (1) one district select per province, shown only for that province; (2) one canton select per province plus one district select per canton (91 fields); (3) a single select with all 492 districts; (4) classic checkout only.
- **Decision (owner, 2026-11-02):** option 1. The province is WooCommerce's own select; a district select labelled "District (Canton)" lists only that province's districts. City (canton) and postcode are hidden for Costa Rica and filled from the district. The classic checkout keeps the three dependent selects (province → canton → district).
