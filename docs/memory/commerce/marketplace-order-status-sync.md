---
name: marketplace-order-status-sync
description: mp_order_apply_status() rules for shipped/delivered/cancelled marketplace packages: fill-only dates, tracking once, no-refund cancel, stock restore.
---

## How are status/shipping/cancel updates applied? (`mp_order_apply_status()`)
- The order feed is queried with a `lastModifiedDate` window, so shipped/delivered/cancelled packages come back anyway; this is the only chance to update an imported package.
- Dates are only FILLED, not moved (a delivered package keeps returning while it stays in the window).
- The tracking number is written once (`shipping_tracking_numbers` has no unique key).

## How is a cancellation handled?
- Cancel with `process_order_cancellation(..., $attempt_refund = false)`: the gateway never took the money.
- Stock IS restored here. `process_order_cancellation()` never does this anywhere else, and for in-store cancellation that was deliberately left unchanged.
- The return value is `'success'`, NOT `'ok'`.

## Can the tracking number be pushed to n11?
- No. The n11 REST order integration has no such service. Shipping goes through n11's contracted carriers (`PUT /rest/delivery/v1/collectionRequest`, HLZ/CEVA/BL) and n11 assigns the number.
- `mp_n11_request_collection()` exists but is NOT called from the scheduler: it dispatches a courier to an address.

Related: docs/memory/commerce/n11-api-facts.md
