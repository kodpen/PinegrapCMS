---
name: classic-store-pages-language
description: Classic store pages and checkout steps (cart, express order, receipt, order preview, shipping, billing) print every visitor text through lang(); {var:n} keys (PR #4472, #4795)
---

## Which pages, and what changed?
- `get_shopping_cart.php`, `get_express_order.php`, `get_order_receipt.php` (the system layouts of the classic store pages). Headings, column titles, field labels, totals rows, button values, placeholders, notices and the "no payment method" error go through `lang()` (PR #4472, issue #4422). A Turkish site showed "Ship to", "Item", "Subtotal:" and "does not include any applicable taxes…" beside Turkish text.
- The multi-step checkout pages followed: `get_order_preview.php`, `get_shipping_address_and_arrival.php`, `get_billing_information.php` and the preview's Change link (`pg_op_change_link_html()`); the preview names the billing country with `pg_geo_country_label_site()` instead of printing its code (PR #4795, issue #4794).
- Only the texts changed; totals, field names and page behaviour did not.

## How is a sentence with a value written?
- As one key with `{var:n}`, never English pieces around a variable: `'{var:1} does not include any applicable taxes…'` (the Subtotal/Total label), `'Click the {var:1} button to update totals, or click the {var:2} button…'`, `'Allow offline payment option for this {var:1} (and click update to apply).'`, `'I agree to the {var:1}.'` (the terms link), `'{var:1}% surcharge has been added.'`, `'Remaining Balance: {var:1}'`, `'{var:1} of {var:2}'` (gift card n of m).
- Multi-recipient headings on the express order page: `'Shipping Address for {var:1}'`, `'Requested Arrival Date for {var:1}'`, `'Shipping Method for {var:1}'`; one recipient keeps the plain key.
- A label's colon or required `*` stays outside the key: `h(lang('Subtotal')) . ':'`.

## Payment periods?
- Stored and compared in English ('Monthly', 'Unknown'…); translated only where printed: `h(lang($payment_period))`.

## What keeps it this way?
- `tests/site_language_texts_test.php` reads the six files' string literals and fails on any text between tags that is not a `lang()` call, plus fixed button values and the payment-method error.
