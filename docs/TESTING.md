# Testing

## How to run

| What | Command | Needs |
|---|---|---|
| Unit tests (no WordPress) | `composer test` or `npm run test:php:unit` | PHP 8.1+ |
| Integration tests (WP + WC) | `npm run env:start` then `npm run test:php:integration` | Docker, Node 22 (`nvm use`) |
| Integration tests on minimum versions (WP 6.7 + WC 10.0.0) | `npm run env:start:min` then `npm run test:php:integration:min` | Docker (port 8890) |
| Integration tests on the 3 order storages (HPOS, posts, sync) | `npm run test:php:integration:storage` (`:storage:min` for the minimum versions) | wp-env running |
| Demo data (CRC or USD store) | `npm run seed` / `npm run seed -- crc` | wp-env running |
| End-to-end matrix (SPEC §8) | `npm run test:e2e:matrix` (`PHP_VERSIONS="8.1 8.3"` by default) | wp-env, Playwright (`npx playwright install chromium`) |
| Screenshots for the phase report | `CRKIT_E2E_SCREENSHOTS=1 CRKIT_E2E_CURRENCY=usd npx playwright test screenshots` | Seeded wp-env |
| Coding standards | `composer lint` (`composer lint:fix` to auto-fix) | — |
| Static analysis | `composer analyze` | — |
| JS lint / build | `npm run lint:js` / `npm run build` | Node 22 |
| Plugin Check (on the built package) | `npm run plugin-check` | wp-env running |
| Distributable zip | `npm run zip` → `dist/costa-rica-checkout-for-woocommerce-<version>.zip` | rsync, zip, composer |

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


## Phase 1 evidence (2026-10-31)

Environment: WordPress 7.1.4, WooCommerce 11.2.1, Plugin Check 2.1.0, theme Twenty Twenty-Five (block theme), site language es_CR. Minimum versions: WordPress 6.7, WooCommerce 10.0.0.

### SPEC §8 matrix (end to end, Playwright)

Each cell places a real order with SINPE Móvil through the browser, checks the payment steps and the amount in colones on the order received page (and the CRC estimate in the USD block checkout), uploads a PNG receipt, opens it as the store (HTTP 200, `image/png`), checks that the shopper's session cannot open it, and confirms the payment from the order screen (status → processing). "Max. amount" checks that SINPE Móvil is not offered in either checkout for an order above ₡500,000. No JavaScript errors on any page.

| PHP | Currency | Order storage | Classic · guest | Classic · logged in | Blocks · guest | Blocks · logged in | Max. amount |
|---|---|---|---|---|---|---|---|
| 8.1 | CRC | hpos | ✅ | ✅ | ✅ | ✅ | ✅ |
| 8.1 | CRC | posts | ✅ | ✅ | ✅ | ✅ | ✅ |
| 8.1 | CRC | sync | ✅ | ✅ | ✅ | ✅ | ✅ |
| 8.1 | USD | hpos | ✅ | ✅ | ✅ | ✅ | ✅ |
| 8.1 | USD | posts | ✅ | ✅ | ✅ | ✅ | ✅ |
| 8.1 | USD | sync | ✅ | ✅ | ✅ | ✅ | ✅ |
| 8.3 | CRC | hpos | ✅ | ✅ | ✅ | ✅ | ✅ |
| 8.3 | CRC | posts | ✅ | ✅ | ✅ | ✅ | ✅ |
| 8.3 | CRC | sync | ✅ | ✅ | ✅ | ✅ | ✅ |
| 8.3 | USD | hpos | ✅ | ✅ | ✅ | ✅ | ✅ |
| 8.3 | USD | posts | ✅ | ✅ | ✅ | ✅ | ✅ |
| 8.3 | USD | sync | ✅ | ✅ | ✅ | ✅ | ✅ |

60 of 60 checks passed. `debug.log` stayed empty during the run.

**Same matrix with the site in es_ES** (phase 1 review, 2026-11-01): 60 of 60 checks passed on PHP 8.1 and 8.3 × CRC/USD × HPOS/posts/sync. In this run the specs also assert the translated plugin texts: the payment steps heading, the receipt confirmation and the block checkout estimate (which comes from the JSON translation of the script).

The first es_ES run failed 4 times in "Blocks · guest" because of a WooCommerce 11.2.1 race (see ADR-0038): a "Place order" click made while the totals recalculation that follows a payment method change is in flight is ignored. It was reproduced with Direct bank transfer as well. The helper now waits for that request; the second run passed every cell.

### Automated suites

| Suite | Result |
|---|---|
| Unit (Brain Monkey), PHP 8.1 / 8.3 / 8.4 / 8.5 locally | 105 tests, 216 assertions, OK (incl. the real BCCR production response) |
| Integration, wp-env PHP 8.1, HPOS / posts / sync | 68 tests, 276 assertions, OK on each storage (phase 1); 86 tests, 345 assertions (phase 2) |
| Integration in CI | PHP 8.1, 8.3, 8.4 and the minimum versions (WP 6.7 + WC 10.0.0), each on the 3 storages |
| Mutation checks (manual) | Removing the order owner check fails 2 receipt tests. Removing the second MIME check fails none: `wp_check_filetype_and_ext()` already rejects every invalid file in the suite (see ADR-0026). |

### Not covered

- The classic theme thank-you template (`checkout/thankyou.php`): the e2e runs use a block theme. The hook it relies on (`woocommerce_thankyou_crkit_sinpe_movil`) is covered by an integration test.
- PHP 8.4 in the e2e matrix (integration tests run on 8.4 in CI).
- ~~A real BCCR token~~: verified by the owner on 2026-11-02 with his production token through "Update now" (source `bccr`, rate of 2026-11-02, sell ₡456.04 / buy ₡449.70, same values as Hacienda, no error).

## Phase 2 evidence (2026-11-02)

Environment as in phase 1, with the Locations module on (seeded), site in es_CR.

### SPEC §8 matrix with the Locations module

Same 60 checks as phase 1, now choosing the province, canton and district in both checkouts (guests) or using the saved district (logged-in customer), and asserting that the order received page shows the address as "District, Canton, Province" (e.g. "Carmen, San José, San José").

| PHP | Currency | Order storage | Classic · guest | Classic · logged in | Blocks · guest | Blocks · logged in | Max. amount |
|---|---|---|---|---|---|---|---|
| 8.1 | CRC | hpos | ✅ | ✅ | ✅ | ✅ | ✅ |
| 8.1 | CRC | posts | ✅ | ✅ | ✅ | ✅ | ✅ |
| 8.1 | CRC | sync | ✅ | ✅ | ✅ | ✅ | ✅ |
| 8.1 | USD | hpos | ✅ | ✅ | ✅ | ✅ | ✅ |
| 8.1 | USD | posts | ✅ | ✅ | ✅ | ✅ | ✅ |
| 8.1 | USD | sync | ✅ | ✅ | ✅ | ✅ | ✅ |
| 8.3 | CRC | hpos | ✅ | ✅ | ✅ | ✅ | ✅ |
| 8.3 | CRC | posts | ✅ | ✅ | ✅ | ✅ | ✅ |
| 8.3 | CRC | sync | ✅ | ✅ | ✅ | ✅ | ✅ |
| 8.3 | USD | hpos | ✅ | ✅ | ✅ | ✅ | ✅ |
| 8.3 | USD | posts | ✅ | ✅ | ✅ | ✅ | ✅ |
| 8.3 | USD | sync | ✅ | ✅ | ✅ | ✅ | ✅ |

### Locations e2e (`tests/e2e/specs/locations.spec.js`)

| Check | Result |
|---|---|
| Classic: province → canton → district options, hidden city/postcode filled (Barva, 40206), province change clears the district | ✅ |
| Blocks: the district field follows the province (San José → Limón), "Limón — Valle La Estrella" listed | ✅ |
| My Account: saving a district shows "San José de la Montaña, Barva, Heredia" and prefills the block checkout | ✅ |

### Block checkout on mobile (390 px)

The block district field is a native `<select>` (WooCommerce's own select component, like Country and Province): no search; phones open the system picker. San José has the longest list (123 districts). Options read "Canton — District" and are sorted by canton, then district (ADR-0045). Screenshots 11–14 in `docs/screenshots/fase-2/` (`CRKIT_E2E_SCREENSHOTS=2 npx playwright test screenshots-fase-2`); 14 renders the options as a list box because a screenshot cannot capture the system picker.

### Dataset

`data/cr-locations.json` is generated from the official PDF (see ADR-0039). Unit tests check 7 provinces, 84 cantons, 492 districts, the cantons and districts per province, unique five-digit postcodes, known districts, the vacant codes (203 06, 601 09, 607 02) and the cantons that replaced them (216, 612, 613). The per-province counts in the tests come from the same extraction; the decree has no summary table to compare against, so a manual spot check against the PDF is still recommended.

## Phase 3 evidence (2026-11-02)

Environment as in phase 2, with the ID module on (seeded, "required only for an electronic invoice"), site in es_CR.

### ID e2e (`tests/e2e/specs/identification.spec.js`)

| Check | Result |
|---|---|
| Classic: the invoice checkbox makes type and number required; "0-2345-6789" rejected (física starts with 0); "1-2345 6789" saved as 123456789 and shown with the billing address on the order received page and the order screen, with "Electronic invoice: Requested" | ✅ |
| Blocks: "(optional)" disappears when the checkbox is checked; DIMEX with 10 digits rejected by the server; cédula jurídica "3-101-a00001" saved as 3101A00001 | ✅ |
| My Account → Account details: saved ID shown, invalid number rejected, a 12-digit DIMEX saved and prefilled in the classic and block checkouts | ✅ |

Order emails with an electronic invoice request (integration test, HTML and plain text): the store's say "The customer asked for an electronic invoice: DIMEX 155812345678" and the customer's "Electronic invoice requested in the name of: DIMEX 155812345678". Screenshots 09 (store) and 12 (customer) in `docs/screenshots/fase-3/`.

With the "Required" setting (e2e): in Account details the ID is optional — removing it shows the normal success message and then the reminder "Add your ID number to speed up your next purchases."; a customer without an ID who changes the last name gets the normal success message; "12345" as a cédula física is an error. The block checkout still marks the ID as required and blocks the order without it, and the classic checkout shows it as required. Screenshot 13 in `docs/screenshots/fase-3/`.

The ID and Locations e2e specs (7 tests) pass with HPOS, posts storage and sync mode (PHP 8.1, USD).

### SPEC §8 matrix with the ID module

Same 60 checks as phases 1 and 2, with the ID module on ("required only for an electronic invoice", checkbox left unchecked; the logged-in customer has a saved cédula física): 60/60 on PHP 8.1 and 8.3 × CRC and USD × HPOS, posts and sync.

Screenshots in `docs/screenshots/fase-3/` (`CRKIT_E2E_SCREENSHOTS=3 npx playwright test screenshots-fase-3`).

