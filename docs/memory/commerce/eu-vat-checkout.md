---
name: eu-vat-checkout
description: The EU VAT check at checkout: when it runs, what each VIES answer does to tax_exempt and the three orders.vat_* columns, where the customer, the order screen, the API and the ERP invoice show it
module: vat_checkout
---

## Where does the check run?
- `pg_vat_checkout_apply($form, $tax_exempt, $order_id, $notice_form)` (`includes/fn/vat_checkout.php`), called just before the orders UPDATE in `billing_information.php`, the update path of `express_order.php` and the express branch of `submit_order.php`. It answers the `tax_exempt` value the UPDATE writes and writes `orders.vat_id`, `vat_checked_at`, `vat_request_id` itself.
- It runs only with tax on (`ECOMMERCE_TAX`), the columns present (`pg_vat_order_ready()`), the tax number box on the form and `pg_vat_checkout_applies()`: an id typed, billing country and site country (`pg_erp_store_country()`, the default country) both member states and different. A Turkish VKN on a Turkish store never reaches VIES.

## What does each answer do? (`pg_vat_checkout_outcome()`, pure)
- Valid, same member state as the billing country: `tax_exempt = 1` (reverse charge on the existing flag; no totals code changed), the id, check time and consultation number written, notice "VAT id verified …".
- VIES down (`unavailable`): the posted tax-exempt box decides as before (normally taxed), columns cleared, notice that VAT is charged. Never cached, so the next submit asks again.
- Invalid, malformed or another member state's id: an error on the tax number box; the customer corrects it or clears the box to order with VAT.
- Not applicable: columns cleared, box decides. The tax-exempt checkbox (ECOMMERCE_TAX_EXEMPT) still works as before: a failed check never clears it, so sites that use it keep working.
- The posted box counts only with tax and exemption on (`pg_tax_exempt_box()`); a hand-made `tax_exempt=1` post used to drop the tax on a store without the box. VIES still sets the flag.

## Where does the customer see it?
- Billing information page: the notice goes to the `order_preview` liveform, which keeps notices until the order is placed, so an earlier VAT notice is removed first (`pg_vat_checkout_notices()`).
- Express order: on the same form. A submit whose answer changes the tax is sent back by submit_order.php's "the total has changed" check.

## Where do the order screen, the printout, the API and the invoice show it?
- `pg_vat_order_note($order)` (pure) gives the line: reverse charge, the id, the check time and the VIES consultation number; '' for an order without a confirmed id.
- `view_order.php`: an "EU VAT ID" row under Tax-Exempt with the note; `print_order.php`: the note under the billing block.
- API `orders` (list and get): `vat_id`, `vat_checked_at` (ISO, null when never), `vat_request_id`, read through `api_order_state_columns()`, so a site without the columns answers null.
- ERP: an order's invoice is issued with the note in `erp_invoices.notes` (`erp_order_invoice_notes()` in `order_bridge.php`); the invoice template prints it.

## What about the tax number box?
- The billing page's box is 20 wide (`pg_vat_tax_number_width()`), 11 on a Turkish store.
- The express order box was drawn for Turkish ids (pattern `[0-9]{10,11}`). At render `_eo_fit_tax_number_field()` (`widgets_express_order.php`) drops the pattern outside Turkey, widens it to 20, makes it a text box and swaps the hint for the VAT id hint (`pg_vat_tax_number_attrs_fit()`, `pg_vat_tax_number_hint()`).

## What else?
- Migration step `upgrade_2026_11_0_order_vat_id()` adds the three columns (#5559). The ERP account card's id: `eu-vat-check.md`.
- No requester id is sent to VIES (no setting holds the site's own VAT id): `vat_request_id` stays empty.

## Which tests cover it?
- `tests/vat_checkout_test.php`: when the check applies, the box gate, every outcome's flag, columns, error and notice, column widths, the box width, the order note, the express box fit.
- `tests/api_order_present_test.php`: the three API fields; `tests/erp_order_invoice_writer_test.php`: the invoice note.
