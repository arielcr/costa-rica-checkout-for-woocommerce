=== Costa Rica Checkout for WooCommerce ===
Contributors: arielcr
Tags: costa rica, sinpe, woocommerce, payment gateway, exchange rate
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

SINPE Móvil payments, Hacienda and BCCR exchange rates, provinces, cantons and districts, and ID numbers for WooCommerce stores in Costa Rica.

== Description ==

Costa Rica Checkout for WooCommerce brings the local needs of a Costa Rican online store together in one well-maintained plugin. Each feature is a module that you can enable or disable in **WooCommerce → Settings → Costa Rica**.

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

* `[crkit_exchange_rate]` shortcode and an "Exchange rate (Costa Rica)" block. Shortcode attributes: `type="both|sell|buy"`, `show_date="yes|no"`, `show_source="yes|no"`, `hide_outdated="yes|no"` and `inline="yes|no"` (use `inline="yes"` inside a sentence). An outdated rate is shown with its date unless you hide it.

**ID number (cédula)**

* ID type (cédula física, cédula jurídica, DIMEX or NITE) and number in the classic and block checkouts.
* The number is checked against the format Hacienda sets for each type (for example, a cédula física has 9 digits and does not start with 0). Hyphens and spaces are removed. Cédulas jurídicas with letters, announced by the Registro Nacional, are accepted.
* Choose whether the ID is optional, required, or required only when the customer checks "I need an electronic invoice".
* Shown with the billing address in the order screen, emails, the order received page and My Account. Customers can change it in My Account → Account details, where it is always optional; with "Required", customers without one see a reminder there.
* When the customer asks for an electronic invoice, the store's emails say so with the ID, and the customer's emails show the ID the invoice will be issued to, so typing mistakes are caught early.
* For developers: `crkit_get_order_identification( $order )`, the `crkit_order_identification` filter and the `crkit_order_identification_saved` action.

**Important:** this plugin does not process payments automatically. SINPE Móvil payments are confirmed manually by the store. It does not generate electronic invoices.

**Known limitations**

* SINPE Móvil payments are not verified automatically: there is no public API to check transfers.
* No electronic invoices: the ID number is stored for your electronic invoicing plugin.
* ID numbers are checked by format only (there is no public check digit). Hacienda's "Extranjero No Domiciliado" and "No Contribuyente" types are not offered.
* The classic cart's shipping calculator does not ask for canton and district; shipping rates that depend on them are exact at checkout.
* In the block checkout the district is a regular dropdown without search, sorted by canton.
* The exchange rate depends on the Ministerio de Hacienda or BCCR services. If they fail, the last good rate is kept; if it gets too old, SINPE Móvil is hidden in stores that sell in dollars until a new rate arrives or you set a manual one.

Compatible with WooCommerce High-Performance Order Storage (HPOS) and the block checkout. Includes Spanish translations for Costa Rica (es_CR) and Spain (es_ES).

Development happens on GitHub: https://github.com/arielcr/costa-rica-checkout-for-woocommerce. Bug reports and pull requests are welcome.

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

Yes. Copy the files from the plugin's `templates` folder to `yourtheme/costa-rica-checkout-for-woocommerce/` and edit them there.

= Where do the provinces, cantons and districts come from? =

From the official 2025 administrative division of Costa Rica (Decreto Ejecutivo 44882-MGP, published in Alcance 12 to La Gaceta 17 on January 28, 2025). The postcode is the official code of the district: province (1 digit) + canton (2) + district (2).

= Why is there no canton select in the block checkout? =

The block checkout only allows extra fields that do not depend on each other. After the province, customers choose the district from a list of that province's districts, shown as "Canton — District" and sorted by canton (it is a regular dropdown without search); the canton is saved as the city automatically.

= Can customers choose the district in the cart's shipping calculator? =

No. For Costa Rican addresses the canton and district are chosen at checkout, where shipping is calculated again. The shipping calculator on the classic cart page does not show them, so rates that depend on the postcode are only exact at checkout. This is a known limitation.

= Does it work with shipping plugins? =

Yes. The canton is saved in the standard city field and the district code in the standard postcode field, so shipping zones by postcode and other plugins can use them. The district is also stored as order meta (`_billing_crkit_district` and `_shipping_crkit_district`).

= Does it generate electronic invoices? =

No. The ID number module stores the customer's ID type and number so that your electronic invoicing plugin can use them.

= How does an electronic invoicing plugin read the customer's ID? =

With `crkit_get_order_identification( $order )`, which returns `type` (Hacienda's code: 01 cédula física, 02 cédula jurídica, 03 DIMEX, 04 NITE), `type_label`, `number` (without hyphens or spaces) and `electronic_invoice` (whether the customer asked for one), or null. The `crkit_order_identification_saved` action fires when a customer places an order with an ID. The values are stored as order meta `_wc_other/crkit/id-type`, `_wc_other/crkit/id-number` and `_wc_other/crkit/electronic-invoice`.

= Can customers from abroad buy? =

Yes, without asking for an electronic invoice. Checkout offers the four ID types that people and companies in Costa Rica use: cédula física, cédula jurídica, DIMEX and NITE. Hacienda's "Extranjero No Domiciliado" and "No Contribuyente" types are only valid on specific receipts, so they are not offered. If you sell abroad, keep the ID as "Required only if the customer asks for an electronic invoice" or "Optional": with "Required", a customer without one of these IDs cannot place an order.

= Where is the source code of the scripts? =

The minified JavaScript in `assets/build` is built with `@wordpress/scripts` from the readable sources in `assets/src`, which ship with the plugin. The PHP code is not minified. The full source, build tools and tests are on GitHub: https://github.com/arielcr/costa-rica-checkout-for-woocommerce

= Is the ID number verified with Hacienda? =

No. There is no public check digit, so the plugin only checks the format of each type. It does not look the number up in any registry.

== External services ==

The exchange rate module connects to the services below to get the US dollar buy and sell rates. Requests are made:

* in the background right after the plugin is activated or the exchange rate module is enabled (the module is on by default and the Ministerio de Hacienda is the default source), and then every 12 hours;
* when an administrator changes the source or the BCCR token in the settings;
* when an administrator clicks "Update now".

They never run while a customer browses the store or checks out: the store always reads the saved rate. No personal data about customers or orders is sent. Each request identifies the plugin in its User-Agent header (plugin name, version and its WordPress.org page); the service also sees the server's IP address, as with any web request. The site address is not sent. To stop all requests, disable the exchange rate module.

**Ministerio de Hacienda de Costa Rica (default source)**

* Request: `GET https://api.hacienda.go.cr/indicadores/tc`.
* Sends: only the request for the current exchange rate. No credentials and no personal data.
* API documentation, with its usage policy and limits: https://api.hacienda.go.cr/docs/
* Terms of use: the Ministerio de Hacienda does not publish separate terms of use for this API; the usage policy is in the API documentation above.
* Privacy policy: https://www.hacienda.go.cr/docs/PoliticadePrivacidadDirectrizN.DM-009-2021.pdf

**Banco Central de Costa Rica (BCCR) economic indicators API (optional)**

* Used only if the store selects the BCCR as the exchange rate source.
* Request: `GET https://apim.bccr.fi.cr/SDDE/api/Bccr.GE.SDDE.Publico.Indicadores.API/cuadro/1/series` with the date range of the last week.
* Sends: the date range and the API token that the store owner generated in the BCCR site. No customer data.
* Economic indicators site (where the token is generated): https://sdd.bccr.fi.cr/es/IndicadoresEconomicos/
* Data use policy: https://www.bccr.fi.cr/cr/es/politica-uso-datos.html
* Website policies: https://www.bccr.fi.cr/cr/es/politicas-del-sitio-web.html

== Screenshots ==

1. WooCommerce → Settings → Costa Rica: enable only the modules your store needs.
2. Block checkout with SINPE Móvil, the estimated amount in colones and the district of the selected province.
3. Order received page with the SINPE Móvil payment steps, the exact amount in colones and the receipt upload.
4. SINPE Móvil box in the order screen, with the uploaded receipt and the "Confirm payment" and "Reject payment" buttons.
5. Exchange rate settings with the current rate, its source and the "Update now" button.
6. ID type and number in the block checkout, with the "I need an electronic invoice" checkbox.
7. Dependent province, canton and district selects in the classic checkout.
8. The exchange rate on a page with the `[crkit_exchange_rate]` shortcode and the block.

== Changelog ==

= 1.0.0 =
* First public release.
* SINPE Móvil payment method: exact amount in colones (converted with the rate of the day in stores that sell in dollars), payment steps, private receipt uploads, "Confirm payment" and "Reject payment" in the order screen, and an email to the store when a receipt arrives.
* Exchange rate from the Ministerio de Hacienda, the BCCR or a manual rate, updated in the background, with the `[crkit_exchange_rate]` shortcode and an "Exchange rate (Costa Rica)" block.
* Provinces, cantons and districts (official 2025 division) in the classic and block checkouts, My Account and the order screen.
* ID type and number (cédula física, cédula jurídica, DIMEX, NITE) in both checkouts, with an optional "I need an electronic invoice" checkbox, for electronic invoicing plugins.
* WooCommerce → Settings → Costa Rica, with one toggle per module.
* Spanish translations (es_CR and es_ES).

== Upgrade Notice ==

= 1.0.0 =
First public release.
