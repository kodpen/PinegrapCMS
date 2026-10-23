---
name: product-tax-rate
description: How product tax_rate and tax_zones combine, NULL vs 0.000 semantics, line-total tax in order_items.tax_total, deprecated order_items.tax, PayPal Express tax field.
---

## Who decides whether tax applies and how much? (2026.4.4)
- `tax_zones` holds one rate per zone and the buyer's address selects the zone: the zone still decides WHETHER tax is charged.
- `products.tax_rate` (`DECIMAL(6,3) NULL DEFAULT NULL`, step 4.26; no UNSIGNED, deprecated on DECIMAL since MySQL 8.0.17 and gone in MySQL 9, where the ALTER would fail) only changes the rate:
  - `NULL` = product has no own rate, the zone rate applies (meaning of every old row).
  - `0.000` = zero-rated, 0% wherever tax applies.
  - Address in no zone = no tax, whatever rate the product carries.
- Single decision point: `get_effective_tax_rate($product_rate, $zone_rate)`; `update_order_item_taxes()` calls it per line.
- `parse_tax_rate()` makes input storable (empty -> NULL, comma -> dot, 0-100 bound); `format_tax_rate()` prints it (0.000 does not turn into an empty box). Site default: `get_default_tax_rate()`.

## Is tax computed per unit or per line?
- Per LINE (since 2026.4.4): `order_items.tax_total` = `round(rate/100 x unit_price x quantity)`, the tax of the whole line. Sum with `SUM(tax_total)`; do NOT multiply by quantity.
- `order_items.tax_rate` (DECIMAL(6,3) NULL, 11.0.59, #1055): the rate the line was charged at, written with `tax_total` by `update_order_item_taxes()` (0 when exempt or untaxed) and by the till; NULL on older lines and marketplace imports. The order's invoice takes it (`erp_order_lines()`).
- `order_items.tax` was the unit tax (every path that summed it did `tax * quantity`); checkout no longer writes it (`update_order_item_taxes()` sets `0`). Still on it (2026-10-20): the marketplace import writes the connector's per-unit `tax` there and no `tax_total` (always 0 from n11), and the order items CSV export in `view_orders.php` reads it. The column stays for old rows and no migration drops it (the drop once dated "2026.5.0" never came: 2026.11.0 followed 2026.4.10). Seeing 0 tax somewhere usually means `tax` is being read.
- Why: `round(rate x unit) x qty` vs `round(rate x unit x qty)` are equal at qty 1 but differ by 1-3 kuruş at qty 2+ (40-90% chance). Cart preview (`widgets_cart.php`), Paraşüt and UBL-TR e-document were already line-based; the payment path was aligned to them and the invoice now matches the collected amount. The old "tutar değişti" rejection in `widgets_cart.php` is gone.

## What does PayPal Express receive?
- Per-line `L_PAYMENTREQUEST_0_TAXAMT` is NOT sent any more: that field wants unit tax and a line amount cannot always be split into whole units. Payment-level `PAYMENTREQUEST_0_TAXAMT` already carries the order tax.

Related: docs/memory/commerce/products-variant-model.md
