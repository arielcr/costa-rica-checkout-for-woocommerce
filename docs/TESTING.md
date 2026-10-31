# Testing

## How to run

| What | Command | Needs |
|---|---|---|
| Unit tests (no WordPress) | `composer test` or `npm run test:php:unit` | PHP 8.1+ |
| Integration tests (WP + WC) | `npm run env:start` then `npm run test:php:integration` | Docker, Node 22 (`nvm use`) |
| Coding standards | `composer lint` (`composer lint:fix` to auto-fix) | — |
| Static analysis | `composer analyze` | — |
| JS lint / build | `npm run lint:js` / `npm run build` | Node 22 |
| Plugin Check (on the built package) | `npm run plugin-check` | wp-env running |
| Distributable zip | `npm run zip` → `dist/costa-rica-kit-for-woocommerce-<version>.zip` | rsync, zip, composer |

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

Environment: WordPress 7.1.4, WooCommerce 11.2.1, Plugin Check 2.1.0, wp-env 12.0.0.

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
| Admin pages smoke test on PHP 8.4 | 200, no notices |
| Plugin Check on the built package (all checks, experimental, low severity) | 0 errors, 0 warnings |
| Plugin Check canary (unescaped `echo $_GET` added to the staging folder) | Detected, confirming the path is analysed |

Not verified locally: the GitHub Actions workflow (no remote yet; validated with `actionlint` only) and integration tests on PHP 8.3 (covered by the CI matrix).
