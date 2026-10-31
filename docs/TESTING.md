# Testing

## How to run

| What | Command | Needs |
|---|---|---|
| Unit tests (no WordPress) | `composer test` or `npm run test:php:unit` | PHP 8.1+ |
| Integration tests (WP + WC) | `npm run env:start` then `npm run test:php:integration` | Docker, Node 22 (`nvm use`) |
| Integration tests on minimum versions (WP 6.7 + WC 10.0.0) | `npm run env:start:min` then `npm run test:php:integration:min` | Docker (port 8890) |
| Coding standards | `composer lint` (`composer lint:fix` to auto-fix) | — |
| Static analysis | `composer analyze` | — |
| JS lint / build | `npm run lint:js` / `npm run build` | Node 22 |
| Plugin Check (on the built package) | `npm run plugin-check` | wp-env running |
| Distributable zip | `npm run zip` → `dist/costa-rica-kit-for-woocommerce-<version>.zip` | rsync, zip, composer |

### Test database isolation

Integration tests never touch the development site database:

1. `tests/Integration/bootstrap.php` defines `WP_TESTS_CONFIG_FILE_PATH`, so the WordPress test suite loads `tests/Integration/wp-tests-config.php` instead of wp-env's default config (which points to the site database).
2. That config uses its own database, `crkit_tests` (prefix `wptests_`), on the wp-env MySQL server. The bootstrap creates it with `CREATE DATABASE IF NOT EXISTS`; that is the only statement that runs outside it.
3. The config exits if the site database (`WORDPRESS_DB_NAME`) is ever named `crkit_tests`, and `TestEnvironmentTest` asserts the database and prefix in use on every run.
4. The installer subprocess receives the same config path, so it also drops and creates tables only in `crkit_tests`.

Evidence: the table statistics of the `wordpress` database are identical before and after a full integration run, while a single WP-CLI command against the site does change them (cron data in `wp_options`).

Local site: http://localhost:8888 (`admin` / `password`). Switch PHP with `WP_ENV_PHP_VERSION=8.4 npx wp-env start --update`.

HPOS modes for manual checks:

```bash
npx wp-env run cli wp wc hpos status
npx wp-env run cli wp wc hpos compatibility-mode enable   # HPOS + sync
npx wp-env run cli wp wc hpos compatibility-mode disable
npx wp-env run cli wp wc hpos disable                     # posts storage
npx wp-env run cli wp wc hpos enable
```

## Phase 0 evidence (2026-10-31)

Environment: WordPress 7.1.4, WooCommerce 11.2.1, Plugin Check 2.1.0, wp-env 12.0.0. Minimum versions: WordPress 6.7, WooCommerce 10.0.0.

Phase 0 adds no checkout behavior, so the checkout, currency and guest/logged-in dimensions of the SPEC §8 matrix do not apply yet. What was verified:

| Check | Result |
|---|---|
| Activate / deactivate via WP-CLI, `debug.log` (WP_DEBUG on) | No notices |
| Front page, Dashboard, Plugins, WC Settings, Costa Rica tab: HPOS on | 200, no notices |
| Same pages: HPOS on + compatibility mode (sync) | 200, no notices |
| Same pages: HPOS off (posts storage) | 200, no notices |
| `FeaturesUtil` compatibility (`custom_order_tables`, `cart_checkout_blocks`) | Compatible, 0 incompatible plugins |
| Costa Rica tab renders four module toggles; "Settings" link on the Plugins screen | OK |
| Save the tab through the real admin form | Saved as `{"exchange_rate":"yes","sinpe_movil":"no","locations":"yes","identification":"no"}` |
| Save with an invalid nonce | Rejected (403 "link you followed has expired"), option unchanged |
| Unit tests, PHP 8.1 / 8.3 / 8.4 / 8.5 (local) | 23 tests, 49 assertions, OK |
| Integration tests, wp-env PHP 8.1 and 8.4 | 8 tests, 19 assertions, OK on both |
| Integration tests on minimum versions: WP 6.7.0 + WC 10.0.0 + PHP 8.1 (review follow-up) | 9 tests, 23 assertions, OK; `debug.log` empty |
| Integration run leaves the `wordpress` database untouched (review follow-up) | Table statistics identical before and after |
| Admin pages smoke test on PHP 8.4 | 200, no notices |
| Plugin Check on the built package (all checks, experimental, low severity) | 0 errors, 0 warnings |
| Plugin Check canary (unescaped `echo $_GET` added to the staging folder) | Detected, confirming the path is analysed |

Integration tests on PHP 8.3 run only in the CI matrix.
