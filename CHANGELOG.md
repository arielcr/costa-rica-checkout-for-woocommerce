# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Locations module: official 2025 administrative division of Costa Rica (7 provinces, 84 cantons, 492 districts) in `data/cr-locations.json`, generated from the decree by `bin/extract-dta-2025.py`.
- Classic checkout and My Account: dependent province → canton → district selects (selectWoo); the canton is saved as the city and the district code as the postcode.
- Block checkout: one district field per province (Additional Checkout Fields API) shown only for the selected province, with options "Canton — District" sorted by canton; city and postcode filled on the server.
- Order screen: searchable district field; addresses formatted as "District, Canton, Province" everywhere WooCommerce prints them.
- `[crkit_exchange_rate]` shortcode and "Exchange rate (Costa Rica)" block (server-rendered); `hide_outdated` / "Hide when outdated" hides an outdated rate.
- Uninstall removes the district meta of orders and customers when "Delete data on uninstall" is on.

## [0.1.0] - 2026-10-31

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
