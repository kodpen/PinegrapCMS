---
name: shipping-management
description: Shipping plan (2026-10-08; phases 1-4, 5, 6 and 7a done, phase 1 waits for new carriers): single write path, panel, customer e-mail and guest tracking, bulk CSV, API fields, more carriers
---

## What state is it in?
- Phase 1 built (#601), phase 2 built (#694, step 11.0.20; `commerce/shipment-row-schema.md`), phase 3 built (#879; `commerce/shipment-write-path.md`), phase 4 built (#931; `commerce/order-screen-shipments.md`). Schema labels: free ones of the open version (`upgrade_to_…()`). Old plan text: `git show 41aea113:docs/_plan_kargo.md`.
- Every phase is one PR; `tests/shipping_test.php` pins today's behaviour and changes only together with a test (red first).

## Which decisions are fixed?
- `orders.tracking_code` is a campaign code, unrelated to shipping. `orders.tracking_company` and `orders.notes` are reserved for ERP: do not write them; the carrier goes on the shipment row.
- `_order_has_shipped()` stays as is: any non-empty tracking number = shipped (cancel gate, docs/memory/commerce/order-cancel-shipping-gate.md). A format warning never changes that.
- ShipWorks path and the `get_express_order.php` / `get_order_preview.php` totals stay unchanged.
- Number format checks only warn, never block. Carrier label/create APIs are not in this plan; `status` and `source='carrier'` are ready for them.

## What are the phases?
1. Carrier recognition done in #601 (`commerce/order-view-tracking-link.md`). Left: hepsijet, trendyolexpress, kolaygelsin, horoz, ceva, sendeo, each once its tracking URL is checked on its site (candidates there), then n11 `HLZ`/`CEVA`/`BL` aliases.
2. Schema - done (#694): carrier, link, status, date, source and warning columns, unique `uk_parcel`, `shipping_methods.carrier`, `order_delivered` in `email_campaign_profiles.action`.
3. One write path - done (#879): `pg_shipment_record()`, `pg_shipment_prune()`, `pg_shipment_delivered()` in `includes/fn/shipping.php`, used by `update_order()` (API source `api`) and the marketplace import; ShipWorks still inserts directly.
4. Panel - done (#931): one card per address, per-number carrier select, status badge, format warning; Save no longer empties undrawn addresses.
5. Customer side (M), two PRs: (a) done (#1964; `commerce/shipment-mail-fields.md`) e-mail: tracking mail-merge fields, `order_delivered` in the campaign profile screens, and the step widening `email_campaigns.action` (owner approved 2026-10-20). (b) widget `__tracking_*` tokens read the row first - done (#2102); guest tracking by order number + billing e-mail - done (#2116; `commerce/guest-shipment-lookup.md`).
6. Bulk - done (#1877; `commerce/shipping-bulk-import.md`).
7. API (S–M), two PRs: (a) done (#2004; `commerce/shipment-api-fields.md`): `parcels` (carrier, name, URL, status, dates) in shipments and the `order.shipped` / `order.delivered` payloads, a recognised `carrier` of `api_orders_ship()` kept on each parcel, n11 import city = district, state = province for new imports only. (b) decided (#2433): an unknown `carrier` is accepted and kept on the parcel; the answer gives `carrier: null`, `carrier_name` and a warning, no 422.
8. Optional: `ship_tos.district`, `states.plate_code`, postcode-province check in warning mode; no district data set (licence decision).

## What must the owner check by hand?
- Live carrier tracking URLs, n11 province/district on a live order, the campaign e-mail via real SMTP, `^^tracking_url^^` on existing sites.
- Open owner questions: `orders.tracking_company`, ShipWorks through the write path, old n11 records, the delivery rule and the named-only `carrier` (`commerce/shipment-write-path.md`), when the `[NEW]` changelog line comes.
- Seen, not changed: `print_order.php` builds `$output_shipping_details` but never prints it.
