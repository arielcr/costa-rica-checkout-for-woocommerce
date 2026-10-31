# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-10-31

### Added
- Requirements: WordPress 6.7+, WooCommerce 10.0+, PHP 8.1+.
- Plugin scaffolding: bootstrap, PSR-4 autoloading, module container.
- WooCommerce → Settings → Costa Rica tab with one toggle per module.
- Compatibility declarations for HPOS (`custom_order_tables`) and the block checkout (`cart_checkout_blocks`).
- `uninstall.php` that removes the plugin options and scheduled actions.
- Tooling: wp-env, PHPCS (WPCS 3 + PHPCompatibilityWP), PHPStan, PHPUnit (unit and integration), GitHub Actions CI, zip build script.
