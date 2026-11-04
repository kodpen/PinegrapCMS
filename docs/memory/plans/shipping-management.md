---
name: shipping-management
description: Shipping plan (2026-10-08; phases 1-7 and the phase 8 postcode check done): single write path, panel, customer e-mail and guest tracking, bulk CSV, API fields, more carriers
---

## What state is it in?
- Every phase is one PR; `tests/shipping_test.php` pins today's behaviour and changes only together with a test (red first).

## Which decisions are fixed?
- `orders.tracking_code` is a campaign code, unrelated to shipping. `orders.tracking_company` and `orders.notes` are reserved for ERP: do not write them; the carrier goes on the shipment row.
- `_order_has_shipped()` stays as is: any non-empty tracking number = shipped (cancel gate, docs/memory/commerce/order-cancel-shipping-gate.md). A format warning never changes that.
- The `get_express_order.php` / `get_order_preview.php` totals stay unchanged. ShipWorks too (#3443, #3460).
- Number format checks only warn, never block. Carrier label/create APIs are not in this plan; `status` and `source='carrier'` are ready for them.

## What are the phases?
- Done: 1 #601, 2 #694, 3 #879, 4 #931, 5a #1964, 5b #2102 #2116, 6 #1877, 7a #2004, 7b #2715, 8 #3011, D1 #3600, D2 #3641, D3a #3654, D3b #3917, print card #3365.
1. Carrier recognition, left (`commerce/order-view-tracking-link.md`; kolaygelsin #6670): hepsijet, trendyolexpress, horoz, ceva, sendeo, each once its tracking URL is checked on its site (candidates there), then n11 `HLZ`/`CEVA`/`BL` aliases.

## What must the owner check by hand?
- Live carrier tracking URLs, n11 province/district on a live order, the campaign e-mail via real SMTP, `^^tracking_url^^` on existing sites.
- Decided: `tracking_company` (`decisions-made-data.md`); old n11 rows: a 2026.11.0 step swaps them (#3836); delivery rule and named-only `carrier` confirmed (#3839; `-product-12.md`). Open: the `[NEW]` changelog line.
