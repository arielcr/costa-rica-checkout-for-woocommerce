# CLAUDE.md — Costa Rica Checkout for WooCommerce

Permanent rules for this repo. Full spec: `docs/SPEC.md`. Design decisions: `docs/DECISIONS.md`.

## Identity
- Slug / folder / text domain: `costa-rica-checkout-for-woocommerce` (text domain always **literal**).
- Prefix: `crkit_` (functions, options, hooks, meta) / `crkit-` (handles, CSS). Namespace: `CRKit\`.
- `Version` header, `CRKIT_VERSION` and readme `Stable tag` must always match.
- Requires: WP 6.7+ (the minimum of WooCommerce 10.0), PHP 8.1+, WooCommerce 10.0+. Both minimums are tested in CI (`.wp-env.min.json`).

## Workflow
- Work **by phases** (SPEC §9). At the end of each phase: stop, deliver the report (SPEC §11), wait for explicit approval.
- **One branch per phase** (`phase/N-name`) and **one PR per phase** into `main` (repo `arielcr/costa-rica-checkout-for-woocommerce`). Ariel merges; never merge or push to `main` yourself. CI must be green on the PR before reporting.
- Never invent external API shapes. Verify with a real request and save it as a fixture in `tests/fixtures/`. No network → code against a fixture and flag it in the report.
- **No runtime dependencies** (Composer or npm) without asking. Dev dependencies are fine.
- Small, frequent commits, Conventional Commits (`feat(sinpe): …`, `test(rates): …`, `chore(ci): …`).
- If the SPEC contradicts current official WP/WC docs, official docs win: apply them and report the discrepancy.
- Uncovered design decision → simplest standard WP/WC option, record it in `docs/DECISIONS.md` (short ADR), mention it in the report.
- Never run `npm audit fix --force`. Apply only compatible fixes (e.g. `overrides`); document the rest.
- Out of scope: e-invoicing, automatic SINPE verification, shipping, anything "Pro" (no upsells, no locked features, no Pro menus).

## Phase closure checklist (from phase 1 on)
Before delivering a phase report, leave wp-env ready for Ariel to review:
- Test data created by a **reproducible WP-CLI script** (`bin/seed.sh`): simple and variable products, a page with the classic checkout (shortcode) and another with the block checkout, sample plugin settings.
- A store scenario in **CRC** and another in **USD** when the phase touches prices (the seed script switches between them).
- **Playwright screenshots** of the key screens in `docs/screenshots/fase-N/`.
- In the report: site URL, admin user and password, and step-by-step instructions to walk through the flow by hand.

## Compatibility (mandatory)
- Declare compatibility on `before_woocommerce_init` via `FeaturesUtil::declare_compatibility()` for `custom_order_tables` and `cart_checkout_blocks`.
- **Never** use `get_post_meta` / `update_post_meta` / `WP_Query` on orders. Always WC CRUD: `wc_get_order()`, `$order->get_meta()`, `$order->update_meta_data()`, `$order->save()`.
- Order metaboxes on `wc_get_page_screen_id( 'shop-order' )`.
- Must work with HPOS on, HPOS off, and compatibility (sync) mode.
- Boot on `plugins_loaded` / `woocommerce_loaded`; exit cleanly if WooCommerce is missing.

## Security (WordPress.org review standard)
- `defined( 'ABSPATH' ) || exit;` in every PHP file.
- Sanitize and validate all input; **escape late** all output (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post`).
- Nonce + `current_user_can()` on every state-changing action.
- Never print `<script>`/`<style>` directly: `wp_enqueue_*` + `wp_add_inline_script`. Enqueue assets only where used.
- No minified code without its source (`assets/src/` ships alongside `assets/build/`). No CDNs, tracking or telemetry; only essential external calls.
- Never call external services during checkout/frontend requests: read from cache.
- Personal data: `wp_add_privacy_policy_content()` + personal data exporter/eraser (ID number, receipts).

## Code quality
- PHP 8.1+, `declare( strict_types=1 );` in `src/`, typed params and returns.
- Composer PSR-4 autoload for own code only; the zip ships `vendor/autoload.php` built with `--no-dev --optimize-autoloader`. Third-party runtime libs only if unavoidable, after asking, prefixed with Strauss/PHP-Scoper.
- WPCS 3.x (WordPress-Extra + WordPress-Docs) + PHPCompatibilityWP `testVersion 8.1-`: zero errors, justified warnings only.
- PHPStan level 6+ with `szepeviktor/phpstan-wordpress` and WooCommerce stubs.
- Code, comments, identifiers and source strings **in English**. Every UI string translatable, literal text domain, `/* translators: */` on placeholders, `_n()` for plurals. Ship `es_CR` `.po`/`.mo` in `/languages`.
- `uninstall.php` removes options and scheduled actions; receipts and order meta are kept unless "Delete all data on uninstall" is enabled.
- Activation/deactivation schedule/unschedule Action Scheduler actions.
- Extensibility via prefixed hooks at key points (`crkit_sinpe_payment_confirmed`, `crkit_sinpe_receipt_uploaded`, `crkit_exchange_rate_updated`, `crkit_rate_providers`, …).

## Repository layout
```
costa-rica-checkout-for-woocommerce.php  header, constants, bootstrap only
uninstall.php · readme.txt · CHANGELOG.md (Keep a Changelog)
composer.json · package.json · phpcs.xml.dist · phpstan.neon.dist · phpunit*.xml.dist
.wp-env.json (WP 7.1 + WooCommerce + Plugin Check) · .wp-env.min.json (minimum versions) · .distignore
docs/ SPEC.md DECISIONS.md TESTING.md
src/ Plugin.php · Admin/SettingsPage.php · Modules/ModuleInterface.php
     Modules/{SinpeMovil,ExchangeRate,Locations,Identification}/ · Support/
assets/src/ (JS/CSS source) · assets/build/ (generated by @wordpress/scripts)
data/cr-locations.json · languages/ · templates/ (theme-overridable)
tests/ Unit/ (no WP, Brain Monkey) · Integration/ (WP + WC via wp-env) · fixtures/
bin/ build-zip.sh · seed.sh (dev data) · docs/screenshots/fase-N/
.github/workflows/ci.yml
```

## Tooling and commands
- Node from `.nvmrc` (`nvm use`). wp-env (Docker) runs WP 7.1 + latest stable WooCommerce + Plugin Check.
- npm scripts: `env:start`, `env:stop`, `test:php`, `lint:php`, `lint:js`, `build`, `plugin-check`, `zip`.
- Composer scripts: `composer lint`, `composer analyze`, `composer test`.
- Plugin Check: `npm run plugin-check` (runs `wp plugin check` on the built package inside wp-env).
- Minimum versions env: `npm run env:start:min` + `npm run test:php:integration:min` (WP 6.7 + WC 10.0.0, port 8890).
- CI (GitHub Actions): phpcs, phpstan, phpunit (PHP 8.1, 8.3, 8.4), integration on minimum versions, JS build, Plugin Check (`wordpress/plugin-check-action`).
- `npm run zip` builds a clean zip honoring `.distignore` (no tests, node_modules or dev configs; includes `assets/build`, `vendor/autoload` and `languages`).

## Gotchas (see `docs/DECISIONS.md`)
- wp-env mounts the plugin with a mapping (`wp-content/plugins/costa-rica-checkout-for-woocommerce` → `.`), so the folder name inside WordPress does not depend on the local checkout folder. Mapped plugins are not activated automatically: `npm run env:start` and `bin/seed.sh` activate it.
- Integration tests use their own database (`crkit_tests`, `tests/Integration/wp-tests-config.php`). Never point them to `wordpress`: the test installer drops every table of its database.
- WP/WC/Plugin Check versions are pinned in `.wp-env.json` (`woocommerce.zip` may serve a beta). Bump them together with `WC tested up to`.
- Plugin Check runs on the built package (`npm run plugin-check`), not on the dev checkout.
- Do not move `src/functions.php` to Composer `autoload.files`: its `ABSPATH` guard would stop PHPStan.
- `npx wp-env run cli bash -c "…"` from the host: `$var` and `$(…)` are expanded by the **host** shell. Put loops in a script under `bin/` and run that script inside the container.
- The WooCommerce mailer is created in the integration bootstrap; a mailer created inside a test loses its hooks after that test (WP_UnitTestCase restores hooks).
- `.wp-env.json` maps `tests/e2e/mu-plugins/` as the site's mu-plugins folder (a folder, not a single file: Docker loses single-file mounts when git replaces the file). It holds a dev-only mail catcher (`crkit-dev-mail.php`); emails land in `wp-content/uploads/crkit-dev-mail/`. It is disabled inside the integration suite.
- After a phase: `npm run seed` (USD, es_CR) leaves the review store ready; `npm run seed -- crc` switches to colones; a second argument sets the site language (`es_CR`, `es_ES`, `en_US`). `SITE_LOCALE=es_ES bin/e2e-matrix.sh` runs the matrix in another language.
- Locations: the Store API validates addresses against the country locale, so classic-only fields must never be required there (`WC()->is_store_api_request()`). WooCommerce inserts block fields into admin address fields with `array_splice()` (numeric keys): filter them by `id`, not by key.
- Plugin Check's WPCS does not treat `wc_clean()` as a sanitizer on superglobals; use `sanitize_text_field( wp_unslash( … ) )` there.
- Block contact fields also render in My Account → Account details, where WooCommerce evaluates their rules without checkout values: never give them `hidden` rules (they would disappear there). WooCommerce only displays block fields for `store-api` orders; for both checkouts, display them yourself (ADR-0047).
- E2E: block themes print notices as `.wc-block-components-notice-banner`, not `.woocommerce-error`/`.woocommerce-message`; match notices by text.
- Translations: `languages/` ships es_CR and es_ES with the same texts. After changing strings: `npm run i18n:pot`, update both `.po` files, `npm run i18n:compile`.
