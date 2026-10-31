#!/usr/bin/env bash
#
# Runs the integration suite once per order storage mode. Runs inside the wp-env cli container:
#   npm run test:php:integration:storage
#
set -euo pipefail

for mode in hpos posts sync; do
	echo "== Order storage: ${mode}"
	CRKIT_TEST_ORDER_STORAGE="${mode}" vendor/bin/phpunit -c phpunit-integration.xml.dist
done
