# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-11-03

First public release on WordPress.org. Includes everything in 0.1.0 (internal development version) plus the changes below.

### Changed
- Plugin renamed to "Costa Rica Checkout for WooCommerce" (slug and text domain `costa-rica-checkout-for-woocommerce`). Settings, order data and hooks are unchanged.
- The Exchange rate module is off by default: no external request until the merchant enables it. Stores that sell in dollars need it to offer SINPE Móvil.
- The outdated rate warning appears only on WooCommerce screens.

### Removed
- `load_plugin_textdomain()`: WordPress 6.8 and later load the included translations from the `Domain Path`. On WordPress 6.7 the plugin appears in English.

### Added
- Locations module: official 2025 administrative division of Costa Rica (7 provinces, 84 cantons, 492 districts) in `data/cr-locations.json`, generated from the decree by `bin/extract-dta-2025.py`.
- Classic checkout and My Account: dependent province → canton → district selects (selectWoo); the canton is saved as the city and the district code as the postcode.
- Block checkout: one district field per province (Additional Checkout Fields API) shown only for the selected province, with options "Canton — District" sorted by canton; city and postcode filled on the server.
- Order screen: searchable district field; addresses formatted as "District, Canton, Province" everywhere WooCommerce prints them.
- `[crkit_exchange_rate]` shortcode and "Exchange rate (Costa Rica)" block (server-rendered); `hide_outdated` / "Hide when outdated" hides an outdated rate.
- ID number module: ID type (cédula física, cédula jurídica, DIMEX, NITE) and number in both checkouts, checked against Hacienda's format for each type; optional, required, or required only with "I need an electronic invoice".
- The ID is shown with the billing address (order screen, emails, order received, My Account), editable in the order screen and in My Account → Account details, and included in WooCommerce's personal data export and erasure.
- `crkit_get_order_identification()`, the `crkit_order_identification` filter and the `crkit_order_identification_saved` action for e-invoicing plugins.
- Uninstall removes the district and ID meta of orders and customers when "Delete data on uninstall" is on.
- Deployment workflows for WordPress.org (release on a `vX.Y.Z` tag; readme and assets on `main`) and the directory banners, icons and screenshots in `.wordpress-org/`.

### Fixed
- Block checkout address cards printed the `{crkit_district}` token; the district now goes into the standard `{city}` replacement.
- A `[crkit_exchange_rate]` shortcode on its own line sat outside the content width in block themes; it is now block-level (`inline="yes"` for use inside a sentence).
- Receipt upload errors are tied to the file field (`aria-invalid`, `aria-describedby`) and announced; the "View receipt" link says it opens a new tab.
- Personal data exporters and erasers stay available when their module is disabled.
- Uninstall deletes receipts regardless of the filesystem method and removes pending actions when WooCommerce is inactive.
- My Account address handlers verify the form nonce and the user, and read only the fields they need; the exchange rate requests send the plugin's current name in the User-Agent.

## [0.1.0] - 2026-10-31 (internal, not published)

### Added
- SINPE Móvil payment gateway (`crkit_sinpe_movil`): amount in colones frozen in the order (rate, source, date, reference, payee), on-hold status, stock reduction, payment steps on the order received page and in the on-hold email.
- Receipt uploads (JPG, PNG, WEBP, PDF) from the order received page and My Account, stored privately in `uploads/crkit-receipts/` and served through an authenticated endpoint.
- Order screen box (HPOS and legacy) with "Confirm payment" and "Reject payment"; "SINPE Móvil receipt uploaded" store email.
- Block checkout integration with the estimated amount in colones and the same availability rules as the classic checkout.
- Exchange rate module: Ministerio de Hacienda, BCCR SDDE (table 1) and manual sources; cache, Action Scheduler refresh twice a day, "Update now", outdated-rate notice; `crkit_get_exchange_rate()` and `crkit_convert_to_crc()`.
- Hooks: `crkit_rate_providers`, `crkit_exchange_rate`, `crkit_convert_to_crc`, `crkit_exchange_rate_updated`, `crkit_exchange_rate_update_failed`, `crkit_sinpe_payment_pending`, `crkit_sinpe_receipt_uploaded`, `crkit_sinpe_payment_confirmed`, `crkit_sinpe_payment_rejected`, `crkit_registered_modules`, `crkit_loaded`.
- Privacy policy text, personal data exporter and eraser for receipts.
- "Delete data on uninstall" option.
- Spanish translations (es_CR and es_ES, same texts), including the block checkout script.
- Requirements: WordPress 6.7+, WooCommerce 10.0+, PHP 8.1+.
- Plugin scaffolding: bootstrap, PSR-4 autoloading, module container.
- WooCommerce → Settings → Costa Rica tab with one toggle per module.
- Compatibility declarations for HPOS (`custom_order_tables`) and the block checkout (`cart_checkout_blocks`).
- `uninstall.php` that removes the plugin options and scheduled actions.
- Tooling: wp-env, PHPCS (WPCS 3 + PHPCompatibilityWP), PHPStan, PHPUnit (unit and integration), GitHub Actions CI, zip build script.
