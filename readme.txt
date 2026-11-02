=== Costa Rica Kit for WooCommerce ===
Contributors: arielcr
Tags: costa rica, sinpe movil, payment gateway, exchange rate, woocommerce
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SINPE Móvil payments in colones, Hacienda and BCCR exchange rates, and provinces, cantons and districts for WooCommerce stores in Costa Rica.

== Description ==

Costa Rica Kit for WooCommerce brings the local needs of a Costa Rican online store together in one well-maintained plugin. Each feature is a module that you can enable or disable in **WooCommerce → Settings → Costa Rica**.

**SINPE Móvil payments**

* Manual payment method: customers pay from their bank app and you confirm the payment from the order screen.
* Shows the SINPE Móvil number, the account holder, the **exact amount in colones** and a reference on the order received page and in the on-hold email.
* Stores in dollars: the amount in colones is calculated with the exchange rate of the day (sell or buy, plus an optional surcharge) and frozen in the order.
* Customers upload the payment receipt (JPG, PNG, WEBP or PDF) from the order received page or from My Account. Receipts are stored privately, outside the Media Library, and only store staff and the customer can see them.
* "Confirm payment" and "Reject payment" buttons in the order screen, and an email to the store when a receipt arrives.
* Optional maximum amount per order, so the method is hidden above your bank's SINPE Móvil limit.
* Works with the block and classic checkouts.

**Exchange rate**

* US dollar buy and sell rates from the Ministerio de Hacienda (no credentials), from the Banco Central de Costa Rica (with your own BCCR token), or a manual rate.
* Updated in the background twice a day. Customers never wait for an external service: the checkout always reads the stored rate.
* If the source fails, the last good rate is kept. If it gets too old, you see a notice and SINPE Móvil is hidden in stores that sell in dollars.
* Functions for developers: `crkit_get_exchange_rate()` and `crkit_convert_to_crc()`.

**Provinces, cantons and districts**

* Official 2025 administrative division (Decreto Ejecutivo 44882-MGP): 7 provinces, 84 cantons and 492 districts. The provinces are WooCommerce's own.
* Classic checkout and My Account: dependent province → canton → district selects with search.
* Block checkout: after the province, a district select that lists only that province's districts.
* The canton is saved as the city and the postcode (5 digits) is filled in automatically, so shipping zones and other plugins keep working with standard address fields.
* Addresses read "District, Canton, Province" in the order screen, emails and My Account.

**Exchange rate on your pages**

* `[crkit_exchange_rate]` shortcode and an "Exchange rate (Costa Rica)" block. Shortcode attributes: `type="both|sell|buy"`, `show_date="yes|no"`, `show_source="yes|no"` and `hide_outdated="yes|no"`. An outdated rate is shown with its date unless you hide it.

**Coming in future versions**

* ID number (cédula) at checkout, for electronic invoicing plugins.

**Important:** this plugin does not process payments automatically. SINPE Móvil payments are confirmed manually by the store. It does not generate electronic invoices.

Compatible with WooCommerce High-Performance Order Storage (HPOS) and the block checkout. Includes Spanish translations for Costa Rica (es_CR) and Spain (es_ES).

== Installation ==

1. Install and activate WooCommerce.
2. Install this plugin from **Plugins → Add New** or upload the zip file, then activate it.
3. Go to **WooCommerce → Settings → Costa Rica** and check the exchange rate source.
4. Go to **WooCommerce → Settings → Payments → SINPE Móvil**, enter your SINPE Móvil number and the account holder, and enable the method.

== Frequently Asked Questions ==

= Does the plugin confirm SINPE Móvil payments automatically? =

No. SINPE Móvil does not offer a public API to verify transfers, so the store checks the receipt and its bank account and confirms the payment manually.

= Is there a limit on SINPE Móvil payments? =

Yes. The Central Bank (BCCR) limits SINPE Móvil payments made by SMS to ₡100,000 per day, and the limit in bank apps depends on each bank (usually up to ₡500,000). Set a maximum amount per order in the SINPE Móvil settings so the method is not offered for larger orders.

= Do I need a BCCR token? =

No. The exchange rate comes from the Ministerio de Hacienda by default, which needs no credentials. A BCCR token is only needed if you prefer the Central Bank's service. You can generate it in the BCCR economic indicators site, under My profile → Generate token.

= My store sells in dollars. What amount does the customer pay? =

The order total converted to colones with the exchange rate of the day (sell rate by default), plus your optional surcharge, rounded up to whole colones. The amount and the rate are saved in the order, so later rate changes do not affect it.

= Where are the payment receipts stored? Are they public? =

In `wp-content/uploads/crkit-receipts/`, with random file names. They are not in the Media Library and are only served through a protected link that checks the user's permissions, so they stay private on Apache and on Nginx.

= Can I change the payment instructions or the email layout? =

Yes. Copy the files from the plugin's `templates` folder to `yourtheme/costa-rica-kit-for-woocommerce/` and edit them there.

= Where do the provinces, cantons and districts come from? =

From the official 2025 administrative division of Costa Rica (Decreto Ejecutivo 44882-MGP, published in Alcance 12 to La Gaceta 17 on January 28, 2025). The postcode is the official code of the district: province (1 digit) + canton (2) + district (2).

= Why is there no canton select in the block checkout? =

The block checkout only allows extra fields that do not depend on each other. After the province, customers choose the district from a list of that province's districts, shown as "District (Canton)"; the canton is saved as the city automatically.

= Can customers choose the district in the cart's shipping calculator? =

No. For Costa Rican addresses the canton and district are chosen at checkout, where shipping is calculated again. The shipping calculator on the classic cart page does not show them, so rates that depend on the postcode are only exact at checkout. This is a known limitation.

= Does it work with shipping plugins? =

Yes. The canton is saved in the standard city field and the district code in the standard postcode field, so shipping zones by postcode and other plugins can use them. The district is also stored as order meta (`_billing_crkit_district` and `_shipping_crkit_district`).

= Does it generate electronic invoices? =

No. The future ID number module will store the customer's ID so that your electronic invoicing plugin can use it.

== External services ==

The exchange rate module connects to the services below to get the US dollar buy and sell rates. Requests run in the background on a schedule (twice a day) or when an administrator clicks "Update now"; they never run while a customer browses the store or checks out. No personal data about customers or orders is sent.

**Ministerio de Hacienda de Costa Rica (default source)**

* Request: `GET https://api.hacienda.go.cr/indicadores/tc`.
* Sends: only the request for the current exchange rate. No credentials and no personal data.
* API documentation: https://api.hacienda.go.cr/docs/
* Privacy policy: https://www.hacienda.go.cr/docs/PoliticadePrivacidadDirectrizN.DM-009-2021.pdf

**Banco Central de Costa Rica (BCCR) economic indicators API (optional)**

* Used only if the store selects the BCCR as the exchange rate source.
* Request: `GET https://apim.bccr.fi.cr/SDDE/api/Bccr.GE.SDDE.Publico.Indicadores.API/cuadro/1/series` with the date range of the last week.
* Sends: the date range and the API token that the store owner generated in the BCCR site. No customer data.
* Economic indicators site (where the token is generated): https://sdd.bccr.fi.cr/es/IndicadoresEconomicos/
* Data use policy: https://www.bccr.fi.cr/cr/es/politica-uso-datos.html
* Website policies: https://www.bccr.fi.cr/cr/es/politicas-del-sitio-web.html

== Screenshots ==

1. Module toggles in WooCommerce → Settings → Costa Rica.
2. Exchange rate settings with the current rate and the "Update now" button.
3. SINPE Móvil settings.
4. Block checkout with the estimated amount in colones.
5. Payment steps and receipt upload on the order received page.
6. SINPE Móvil box in the order screen, with the receipt and the confirm and reject buttons.
7. Province, canton and district selects in the classic checkout.
8. District select in the block checkout.
9. Exchange rate shortcode and block on a page.

== Changelog ==

= 0.1.0 =
* New: SINPE Móvil payment method with the amount in colones, receipt uploads, order screen actions and a store email.
* New: exchange rate from the Ministerio de Hacienda, the BCCR or a manual rate, updated in the background.
* New: WooCommerce → Settings → Costa Rica, with one toggle per module.
* New: provinces, cantons and districts (official 2025 division) in the classic and block checkouts, My Account and the order screen.
* New: `[crkit_exchange_rate]` shortcode and "Exchange rate (Costa Rica)" block.
* New: Spanish translations (es_CR and es_ES).

== Upgrade Notice ==

= 0.1.0 =
Initial development version.
