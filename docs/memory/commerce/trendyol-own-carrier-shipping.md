---
name: trendyol-own-carrier-shipping
description: Trendyol packages shipped with the shop's own carrier: the Shipping carrier choice, the alternative-delivery call, which packages and parcels the job reports, Picking first (unverified).
---

## How does an account say it ships itself?
- Trendyol's provider field `delivery` (select, label "Shipping carrier"): `trendyol` (default; Trendyol's contracted carrier, its number arrives with the package) or `own`, in the encrypted credentials blob like `environment`; an older row reads as the default. `mp_account_ships_itself()` (`connectors/base.php`) answers it.
- Create and Save stamp `delivery_since` (`mp_account_delivery_stamp()`): set when the account starts shipping itself, kept while it does, dropped when it stops. No stamp, no reports.

## Which call reports a shipment?
- `PUT /integration/order/sellers/{sellerId}/shipment-packages/{packageId}/alternative-delivery`, body `{"isPhoneNumber":false,"trackingInfo":"<carrier tracking URL>","params":{}}`; 200, no body; the package turns Shipped (`mp_trendyol_ship_package()`, entry `ship_package`). Package id 1–18 digits and an `http(s)://` link are checked first. No phone-number variant. Docs read 2026-10-24.
- `manual-deliver` is not used: the pull reads `Delivered`.

## Which packages does the job report? (`mp_sync_ship_pending()`)
- After a successful pull and the acceptance pass: map rows `imported`, `order_id > 0`, Created/Picking/Invoiced, no `last_error`, imported in the last 30 days and from `delivery_since` + 60 s (a save racing a pass, clock skew), whose order has a non-`marketplace` parcel that, without a site default, has a page, a carrier or a method code. Oldest first, pages of 200 by id, up to 5 pages, 50 calls a pass.
- Older imports, and a package refused before the switch and brought in after it (its import time is the first one), are shipped in the seller panel: an older import holds Trendyol's carrier name in its method code.
- The parcel: the first one of the order's addresses (`pg_shipment_rows()`) with a number and a link (`mp_sync_shipment_link()`, what `pg_shipment_view()` shows); never a `marketplace` row. Sent only when named: the row's `tracking_url`, the row's carrier, the method code's carrier (only the shop or POST /orders/{id}/shipment writes it here), or the site default while the method code is empty. Never the number's shape (11 digits read as UPS, 12 as FedEx), never the default instead of a method code the carrier list does not know (Sendeo, HepsiJET). Such a parcel waits until its carrier is chosen on the order screen.
- The import leaves Trendyol's carrier name out of `ship_tos.shipping_method_code` for an account that ships itself, reading the account again (`mp_order_apply_tracking(…, $account)`).
- Skipped until parcel rows carry their source (`pg_shipment_rows_ready()`).

## In what order, and what is written?
- A package still `Created` and not `approved` is accepted (Picking, `approve_lines`) first, whatever auto-accept says, only when two calls are left; the row is set `Picking`/`approved` at once. One the acceptance pass took (`approved = 1`, still `Created` here) goes straight to the shipment: Picking twice is refused and would stop it. Unverified: whether alternative delivery takes a Created package directly.
- Success → `remote_status = 'Shipped'`, `approved = 1`, `last_error = ''`, an activity-log line; the next pull confirms it and records Trendyol's cargo number as a second parcel (source marketplace).
- A refusal (retry false) → `last_error` on the map row: the Imported orders card shows it and the package leaves the pass; the operator ships it in the seller panel. A retryable failure (outage, 429) writes nothing.

## What is unverified?
- No live call. Picking-first; whether a feed that lags behind could set the row back to Picking and send the link twice (it would be refused and show an error).

Related: docs/memory/commerce/trendyol-orders.md, docs/memory/commerce/order-view-tracking-link.md, docs/memory/commerce/shipment-write-path.md
