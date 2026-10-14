---
name: erp-invoice-editor-catalog-offers
description: Invoice/waybill editor catalogue read (product name rule, erp_product_for_line shape), product offers, two-discount order, field-level errors, barcode box.
---

## How do the editors read the product catalogue? (migration 4.58)
- `includes/erp/products.php`. Product name is `products.short_description`; `products.name` is the stock code. Every ERP place that shows a product follows this: `COALESCE(NULLIF(short_description,''), name)`.
- Endpoint `get_erp_products.php?q=` / `?barcode=`.
- `erp_product_for_line()` shape: `name`, `sku`, `price` (kuruş), `tax_rate` + `tax_rate_source` (`product` | `zone`; when the product's `tax_rate` is NULL, `erp_default_tax_rate()` = `get_tax_rate_for_address(default country, '')`), `stock` (null when untracked), `out_of_stock`, `enabled`, `offer`.

## What does `erp_product_offer()` cover?
- Deliberately only a campaign knowable from the product: `status='enabled'`, within dates, `require_code=0`, `upsell=0`, `offer_rule_id=0`, empty `offer_conditions`, and the `discount product` action targets the product or its group (`pg_offer_action_targets_product()`; a `cheapest` target is skipped). A cart- or recipient-bound offer is checkout's job.
- The amount becomes a 3-decimal rate. Per-request product cache (static).

## How are two discounts applied? (`erp_manual_lines_build()`)
- In order: `offer_amount = apply_rate(line_total, offer_rate)`; `typed = apply_rate(line_total − offer_amount, discount_rate)`; `discount_amount` = the sum.
- Document, return and export see the single `discount_amount`; none changed. The columns are not written if 4.58 has not run (`erp_invoice_lines_have_offers()`). Issue rebuilds lines, so the offer survives issue. No offer on a purchase (`isPurchase()` in JS, `direction` on the server).

## How are field-level errors reported?
- `erp_manual_lines_build()` / `erp_manual_header_build()` return `field` (`lines[n][description]`, `account_id`, …); screens call `mark_error($result['field'] ?? '_error', …)`.
- liveform `output_field` takes no custom `data-*`; JS finds the field with `[name$="[field]"]`. On load the script scrolls to the first `form .is-invalid`.

## How does the barcode box work?
- The box in the card header: Enter → `?barcode=` → if the line exists quantity +1, else an empty/new line. Without `BARCODE_ENABLED` the stock code is the barcode (same read as `add_order.php`).
- The waybill editor uses the same script; if the card is absent the script silently disables the feature.

## What lesson came from the 4.56 regression?
- The `edit_erp_invoice.php` reminder card reused `$lines` / `$output_lines` and emptied the invoice lines → renamed `$reminder_lines`. When adding a card to a long screen file, its variable names must not collide with the rest of the screen; give the card its own prefix.
