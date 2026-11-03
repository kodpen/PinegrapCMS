---
name: eu-vat-checkout
description: The EU VAT check at checkout (SF6b1): when it runs, what each VIES answer does to tax_exempt and the three orders.vat_* columns, where the customer sees it, what SF6b2 adds
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

## Where does the customer see it?
- Billing information page: the notice goes to the `order_preview` liveform, which keeps its notices until the order is placed, so an earlier VAT notice is removed before a new one is added (`pg_vat_checkout_notices()`).
- Express order: on the same form. A submit whose answer changes the tax is sent back by submit_order.php's "the total has changed" check, so nobody pays a total not seen.

## What else changed?
- The billing page's tax number box is 20 wide (`pg_vat_tax_number_width()`), 11 on a Turkish store. The express order starter widget (`widgets_express_order.php`) still has a 10-11 digit pattern for Turkish ids; widening it is left to SF6b2.
- Migration step `upgrade_2026_11_0_order_vat_id()` adds the three columns (#5559, `workflow/decisions-made-schema-8.md`). There is no install dump: install runs the migrations.
- No requester id is sent to VIES (no setting holds the site's own VAT id), so `vat_request_id` stays empty until one exists.

## What is left for SF6b2?
- The ERP invoice's reverse-charge note with the id and check time, the account card's tax number check, the order screen / print view / API `orders` resource showing the three columns.

## Which tests cover it?
- `tests/vat_checkout_test.php`: when the check applies, every outcome's flag, columns, error and notice, column widths, the box width.
