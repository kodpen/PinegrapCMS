---
name: marketplace-order-status-sync
description: mp_order_apply_status() rules for shipped/delivered/cancelled marketplace packages: first sight, fill-only dates, tracking once, no-refund cancel, stock restore, auto-accept only open.
---

## How are status/shipping/cancel updates applied? (`mp_order_apply_status()`)
- The order feed is queried with a `lastModifiedDate` window, so shipped/delivered/cancelled packages come back anyway; this is the only chance to update an imported package.
- Dates are only FILLED, not moved (a delivered package keeps returning while it stays in the window).
- The tracking number is written once (`shipping_tracking_numbers` has no unique key).

## What if a package is not open the first time it is seen? (`mp_import_order()`)
- Cancelled (n11 `Cancelled`/`UnSupplied`) at first sight: NO order is written and stock is not touched. The map row is kept as `imported` with `order_id` 0 and a note in `last_error`, so the package is never imported later. Writing and cancelling would not be stock-neutral: the take stops at zero, the return adds the whole quantity.
- Shipped/delivered at first sight: the order is written, then `mp_order_apply_status()` runs in the same pass (tracking, dates); the feed may never hand the package back. As on a re-poll, this queues `order.shipped`/`order.delivered` once and the dates are today's, not the marketplace's.
- Auto-accept (`mp_sync_approve()`) only runs for an `open` package; `mp_order_stage()` answers the stage.
- Symptom before the fix (#1015): a cancelled package was imported as a `complete` order, stock came off and the cancel was never applied; auto-accept sent Picking for it.

## How is a cancellation handled?
- Cancel with `process_order_cancellation(..., $attempt_refund = false)`: the gateway never took the money.
- Stock IS restored here. `process_order_cancellation()` never does this anywhere else, and for in-store cancellation that was deliberately left unchanged.
- The return value is `'success'`, NOT `'ok'`.

## Can the tracking number be pushed to n11?
- No. The n11 REST order integration has no such service. Shipping goes through n11's contracted carriers (`PUT /rest/delivery/v1/collectionRequest`, HLZ/CEVA/BL) and n11 assigns the number.
- `mp_n11_request_collection()` exists but is NOT called from the scheduler: it dispatches a courier to an address.

Related: docs/memory/commerce/n11-api-facts.md
