=== Costa Rica Kit for WooCommerce ===
Contributors: arielorozco
Tags: costa rica, sinpe movil, payment gateway, exchange rate, woocommerce
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SINPE Móvil payments, colón exchange rates, provinces, cantons, districts and ID numbers for WooCommerce stores in Costa Rica.

== Description ==

Costa Rica Kit for WooCommerce brings the local needs of a Costa Rican online store together in one well-maintained plugin. Each feature is a module that you can enable or disable in **WooCommerce → Settings → Costa Rica**.

**Modules**

* **SINPE Móvil:** a manual payment method. Customers pay from their banking app and upload the payment receipt; you confirm the payment from the order screen.
* **Exchange rate:** keeps the US dollar exchange rate up to date (Ministerio de Hacienda by default, BCCR with your own token, or a manual rate) so stores priced in dollars can show the exact amount in colones.
* **Provinces, cantons and districts:** dependent address fields based on the official administrative division of Costa Rica.
* **ID number (cédula):** collects the customer's ID type and number at checkout so electronic invoicing plugins can use it.

**Important:** this plugin does not process payments automatically. SINPE Móvil payments are confirmed manually by the store. It does not generate electronic invoices.

Compatible with the block and classic checkouts and with WooCommerce High-Performance Order Storage (HPOS).

== Installation ==

1. Install and activate WooCommerce.
2. Install this plugin from **Plugins → Add New** or upload the zip file, then activate it.
3. Go to **WooCommerce → Settings → Costa Rica** and enable the modules you need.

== Frequently Asked Questions ==

= Does the plugin confirm SINPE Móvil payments automatically? =

No. SINPE Móvil does not offer a public API to verify transfers, so the store reviews the receipt and confirms the payment manually.

= Is there a limit on SINPE Móvil payments? =

Yes. The Central Bank (BCCR) limits SINPE Móvil payments made by SMS to ₡100,000 per day, and the limit on authenticated channels (banking apps) depends on each bank. You can set a maximum order amount so the payment method is not offered for larger orders.

= Do I need a BCCR token? =

No. The exchange rate comes from the Ministerio de Hacienda by default, which requires no credentials. A BCCR token is only needed if you prefer the Central Bank's service as the source.

= Does it generate electronic invoices? =

No. The ID number module stores the customer's ID so that your electronic invoicing plugin can use it.

== External services ==

The exchange rate module connects to the services below to get the US dollar buy and sell rates. Requests run in the background on a schedule (twice a day) or when an administrator clicks "Update now"; they never run while a customer browses the store or checks out. No personal data about customers or orders is sent.

**Ministerio de Hacienda de Costa Rica (default source)**

* Sends: a request for the current exchange rate. No credentials and no personal data.
* Service: https://api.hacienda.go.cr/docs/
* Provider: https://www.hacienda.go.cr/

**Banco Central de Costa Rica (BCCR) economic indicators API (optional)**

* Used only if the store selects the BCCR as the exchange rate source.
* Sends: the date range of the requested rates and the API token that the store owner generated in the BCCR site. No customer data.
* Provider: https://www.bccr.fi.cr/

== Screenshots ==

1. Module toggles in WooCommerce → Settings → Costa Rica.

== Changelog ==

= 0.1.0 =
* Initial development version: plugin scaffolding, settings tab with module toggles, HPOS and block checkout compatibility declarations.

== Upgrade Notice ==

= 0.1.0 =
Initial development version.
