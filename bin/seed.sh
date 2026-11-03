#!/usr/bin/env bash
#
# Demo data for manual review in wp-env. Idempotent: run it again to switch scenarios.
# Runs inside the wp-env cli container:
#
#   npm run seed                  # store in US dollars, site in es_CR (default)
#   npm run seed -- crc           # store in colones
#   npm run seed -- usd es_ES     # site language: es_CR (default), es_ES or en_US
#
set -euo pipefail

SCENARIO="${1:-usd}"
LOCALE="${2:-es_CR}"
case "${SCENARIO}" in
	crc | usd) ;;
	*)
		echo "Usage: seed.sh [crc|usd] [es_CR|es_ES|en_US]" >&2
		exit 1
		;;
esac
case "${LOCALE}" in
	es_CR | es_ES | en_US) ;;
	*)
		echo "Usage: seed.sh [crc|usd] [es_CR|es_ES|en_US]" >&2
		exit 1
		;;
esac

# wp-env mounts the plugin through a mapping (.wp-env.json), which does not activate it.
wp plugin activate costa-rica-checkout-for-woocommerce --quiet

echo "== Store (${SCENARIO})"
wp option update blogname "Tienda Demo Costa Rica" --quiet
wp option update woocommerce_coming_soon no --quiet
wp option update woocommerce_store_address "Avenida Central, calle 1" --quiet
wp option update woocommerce_store_city "San José" --quiet
wp option update woocommerce_default_country "CR:CR-SJ" --quiet
wp option update woocommerce_store_postcode "10101" --quiet
wp option update woocommerce_calc_taxes no --quiet
wp option update woocommerce_enable_guest_checkout yes --quiet
wp option update woocommerce_enable_checkout_login_reminder yes --quiet
wp rewrite structure '/%postname%/' --quiet

if [ "${SCENARIO}" = "crc" ]; then
	wp option update woocommerce_currency CRC --quiet
	wp option update woocommerce_price_num_decimals 0 --quiet
else
	wp option update woocommerce_currency USD --quiet
	wp option update woocommerce_price_num_decimals 2 --quiet
fi
wp option update woocommerce_price_thousand_sep "," --quiet
wp option update woocommerce_price_decimal_sep "." --quiet
wp option update woocommerce_currency_pos left --quiet

echo "== Language: ${LOCALE}"
if [ "${LOCALE}" = "en_US" ]; then
	wp site switch-language en_US --quiet
else
	wp language core install "${LOCALE}" --activate --quiet || echo "   (could not install ${LOCALE}, keeping the current language)"
	wp language plugin install woocommerce "${LOCALE}" --quiet || true
fi

echo "== Costa Rica Checkout settings"
wp option update crkit_modules '{"exchange_rate":"yes","sinpe_movil":"yes","locations":"yes","identification":"yes"}' --format=json --quiet
wp option update crkit_identification_settings '{"requirement":"invoice"}' --format=json --quiet
wp option update crkit_exchange_rate_settings '{"source":"hacienda","bccr_token":"","manual_enabled":"no","manual_rate":"","max_age_hours":"48"}' --format=json --quiet
wp option update woocommerce_crkit_sinpe_movil_settings '{"enabled":"yes","title":"SINPE Móvil","description":"Pague desde la app de su banco con SINPE Móvil. Después de hacer el pedido verá el número y el monto exacto en colones.","instructions":"Si tiene dudas, escríbanos por WhatsApp al 8888-0000.","phone":"88887777","holder":"Tienda Demo S.A.","require_receipt":"yes","max_upload_mb":"5","max_amount_crc":"500000","rate_type":"sell","surcharge":"","reject_status":"failed"}' --format=json --quiet
wp option update woocommerce_bacs_settings '{"enabled":"yes","title":"Transferencia bancaria","description":"Transfiera a nuestra cuenta IBAN.","instructions":"","account_details":""}' --format=json --quiet

echo "== Products, shipping, pages and customer"
wp eval-file bin/seed-data.php "${SCENARIO}"

echo "== Exchange rate (normally refreshed in the background twice a day)"
wp eval 'echo crkit_exchange_rate_service()->refresh() ? "   Updated from the Ministerio de Hacienda.\n" : "   Update failed; see WooCommerce → Status → Logs.\n";'
wp action-scheduler run --group=crkit --quiet 2>/dev/null || true

echo "== Done. Site: http://localhost:8888  Admin: admin / password  Customer: cliente / cliente"
