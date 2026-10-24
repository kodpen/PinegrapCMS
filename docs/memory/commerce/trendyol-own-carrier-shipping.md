---
name: trendyol-own-carrier-shipping
description: Trendyol packages shipped with the shop's own carrier: the Shipping carrier choice, the alternative-delivery call, which packages and parcels the job reports, Picking first (unverified).
---

## How does an account say it ships itself?
- Trendyol's provider field `delivery` (select, label "Shipping carrier"): `trendyol` (default; Trendyol's contracted carrier, its number arrives with the package) or `own`. It lives in the encrypted credentials blob like `environment`; an older row reads as the default. `mp_account_ships_itself()` (`connectors/base.php`) answers it.

## Which call reports a shipment?
- `PUT /integration/order/sellers/{sellerId}/shipment-packages/{packageId}/alternative-delivery`, body `{"isPhoneNumber":false,"trackingInfo":"<carrier tracking URL>","params":{}}`; 200 with no body; the package turns Shipped by itself (`mp_trendyol_ship_package()`, connector entry `ship_package`). Package id 1–18 digits and a link starting `http(s)://` are checked before the call. The phone-number variant is not used. Docs read 2026-10-24 (page modified 2026-10-13).
- `manual-deliver` (delivered after the buyer confirms) is not used: the pull reads `Delivered` when Trendyol sets it.

## Which packages does the job report? (`mp_sync_ship_pending()`)
- After a successful pull and the acceptance pass, for an account that ships itself: map rows `imported`, `order_id > 0`, `remote_status` Created/Picking/Invoiced, no `last_error`, imported in the last 30 days, oldest first, 200 read and at most 50 calls a pass (so waiting packages do not hide shipped ones).
- The parcel: the first one of the order's addresses (`pg_shipment_rows()`, never a raw read) with a number and a link (`mp_sync_shipment_link()`: what `pg_shipment_view()` shows on the order screen). A row with source `marketplace` is never sent: that number is Trendyol's. No parcel or no link → no call, looked at again next pass. A link needs a carrier `pg_shipping_carriers()` knows (row, method code or site default) or the row's own `tracking_url`.
- Skipped entirely until parcel rows carry their source (`pg_shipment_rows_ready()`).

## In what order, and what is written?
- A package still `Created` is accepted (Picking, `approve_lines`) first, whatever auto-accept says, and the row is set `Picking`/`approved` at once so a retried shipment does not send Picking twice. Unverified: whether alternative delivery would take a Created package directly.
- Success → `remote_status = 'Shipped'`, `approved = 1`, `last_error = ''`, one activity-log line. The next pull confirms it and brings Trendyol's own cargo number, recorded as a second parcel (source marketplace).
- A refusal (retry false) → `last_error` on the map row: the Imported orders card shows it and the package leaves the pass; the operator ships it in the seller panel. A retryable failure (outage, 429) writes nothing.

## What is unverified?
- No live call. Picking-first; whether a feed that lags behind could set the row back to Picking and send the link twice (it would be refused and show an error).

Related: docs/memory/commerce/trendyol-orders.md, docs/memory/commerce/order-view-tracking-link.md, docs/memory/commerce/shipment-write-path.md
