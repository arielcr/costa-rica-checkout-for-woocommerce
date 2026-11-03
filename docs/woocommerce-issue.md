# Draft issue for WooCommerce (not published)

Target: https://github.com/woocommerce/woocommerce/issues/new (bug report template).
Status: **not reproducible on demand as of 2026-11-03** (see "Reproducibility"). Publish only after it
happens again with a trace, or after reproducing it with the steps below on an es_ES site.

---

## Title

Block checkout: "Place order" click is sometimes ignored right after changing the payment method (while `__experimental_calc_totals` is in flight)

## Prerequisites

- [x] I have searched for similar issues in both open and closed tickets and cannot find a duplicate.
- [x] The issue still exists against the latest stable WooCommerce release (11.2.1).
- [x] I have attempted to find the simplest possible steps to reproduce the issue.
- [ ] I have included a failing test as a pull request (not possible: it is timing dependent).

## Describe the bug

In the Checkout block, selecting a payment method sends
`POST /wp-json/wc/store/v1/checkout?__experimental_calc_totals=true`. When the shopper clicks
"Place order" while that request is still in flight, the click is sometimes ignored: no
`POST /wc/store/v1/checkout` request is sent, no notice is shown, and the button looks idle again. A
second click places the order normally.

## Expected behavior

The click places the order, either right away or as soon as the totals recalculation finishes (for
example by queuing the submission or keeping the button disabled until it can be submitted).

## Actual behavior

The first click does nothing. Shoppers who click once and wait never get their order placed.

## Steps to reproduce

1. WordPress 7.1.4, WooCommerce 11.2.1, Twenty Twenty-Five, site language es_ES, store country Costa Rica.
2. Enable two payment methods, e.g. Direct bank transfer (BACS) and Cash on delivery.
3. Pages: the default Checkout page (Checkout block).
4. As a guest, add a product to the cart and open the checkout.
5. Fill in the contact and address fields.
6. Click another payment method, and immediately click "Place order" (within a few hundred
   milliseconds, while `POST /wc/store/v1/checkout?__experimental_calc_totals=true` is pending in the
   browser's network panel).
7. Observed: no checkout request is sent and the page stays on the checkout. Clicking again places the order.

## Reproducibility

- **Observed (2026-10-31):** 4 failures in one run of an automated Playwright suite (12 block-checkout
  guest orders, site in es_ES, PHP 8.1 and 8.3, HPOS and legacy order storage). The Playwright trace
  showed the click landing while `POST /wc/store/v1/checkout?__experimental_calc_totals=true` was
  in flight and no checkout POST afterwards. A control script that selected Direct bank transfer instead of
  the plugin's payment method failed the same way (the plugin was still active).
- **Not reproduced (2026-11-03, es_CR):**
  - 0 of 10 runs of the same flow with a single click and no retry.
  - 0 of 8 runs delaying the `__experimental_calc_totals` response by 2 s with Playwright
    (`page.route`) and clicking while it was pending, with and without the third-party plugin
    (WooCommerce, BACS and COD only). In those runs the click was honored once the request finished.
- The difference may be timing (the first click landing between the recalculation and the button
  state update) or the site language. A Playwright trace of a failing run would settle it.

## Isolating the problem

- [ ] This bug happens with only WooCommerce plugin active (not verified: the 2026-10-31 control used
      BACS with the third-party plugin still active; the 2026-11-03 runs with WooCommerce alone did not fail).
- [x] This bug happens with a default WordPress theme active (Twenty Twenty-Five).
- [ ] I can reproduce this bug consistently using the steps above.

## WordPress Environment

- WordPress 7.1.4, WooCommerce 11.2.1, PHP 8.1.34 and 8.3, MySQL from `@wordpress/env` 12.
- HPOS enabled (also seen with the legacy posts storage).
- Browser: Chromium (Playwright).

## Workaround

In our end-to-end tests, after selecting the payment method we wait for the network to settle and,
if the order received page does not load within 20 s, click "Place order" again (up to 3 times).
A real failure never reaches the order received page, so it still fails the test.
See `tests/e2e/utils.js` (`placeBlockOrder`) and docs/DECISIONS.md, ADR-0038.
For shoppers there is no workaround on the plugin side: the Checkout block owns the button.
