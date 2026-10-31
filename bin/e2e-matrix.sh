#!/usr/bin/env bash
#
# Runs the SPEC §8 matrix end to end against wp-env:
#   PHP versions × store currency (CRC, USD) × order storage (HPOS, posts, sync)
#   and, inside Playwright, classic and block checkout × guest and logged-in customer.
#
#   bin/e2e-matrix.sh                       # PHP 8.1 and 8.3
#   PHP_VERSIONS="8.4" bin/e2e-matrix.sh
#
# Results: tests/e2e/.results/matrix/<php>-<currency>-<storage>.json, summarized by
# bin/e2e-matrix-report.mjs. The environment is left on the .wp-env.json PHP version, seeded in USD.
#
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RESULTS="${ROOT}/tests/e2e/.results/matrix"
PHP_VERSIONS="${PHP_VERSIONS:-8.1 8.3}"
cd "${ROOT}"
mkdir -p "${RESULTS}"

wp_cli() {
	npx wp-env run cli bash -c "$1" 2>&1 | grep -v -E '^(ℹ|✔)' || true
}

set_storage() {
	case "$1" in
		hpos) wp_cli 'wp wc hpos compatibility-mode enable; wp wc hpos sync; wp wc hpos enable; wp wc hpos compatibility-mode disable' ;;
		posts) wp_cli 'wp wc hpos compatibility-mode enable; wp wc hpos sync; wp wc hpos disable; wp wc hpos compatibility-mode disable' ;;
		sync) wp_cli 'wp wc hpos compatibility-mode enable; wp wc hpos sync; wp wc hpos enable' ;;
	esac >/dev/null
	wp_cli 'wp wc hpos status' | grep -E 'enabled\?'
}

status=0
for php in ${PHP_VERSIONS}; do
	echo "######## PHP ${php}"
	WP_ENV_PHP_VERSION="${php}" npx wp-env start >/dev/null 2>&1 || { echo "wp-env failed to start on PHP ${php}"; exit 1; }
	actual_php="$(npx wp-env run cli php -r 'echo PHP_VERSION;' 2>/dev/null | grep -E '^[0-9]')"
	echo "   running PHP ${actual_php}"

	for currency in usd crc; do
		npm run --silent seed -- "${currency}" >/dev/null 2>&1 || { echo "seed failed"; exit 1; }

		for storage in hpos posts sync; do
			echo "== PHP ${php} · ${currency} · ${storage}"
			set_storage "${storage}"
			PLAYWRIGHT_JSON_OUTPUT_NAME="${RESULTS}/${php}-${currency}-${storage}.json" \
				CRKIT_E2E_PHP="${actual_php}" CRKIT_E2E_CURRENCY="${currency}" CRKIT_E2E_STORAGE="${storage}" \
				npx playwright test tests/e2e/specs/matrix.spec.js --reporter=list,json || status=1
		done
	done
done

echo "######## Restoring the default environment"
npx wp-env start >/dev/null 2>&1
set_storage hpos
npm run --silent seed -- usd >/dev/null 2>&1

node bin/e2e-matrix-report.mjs
exit "${status}"
