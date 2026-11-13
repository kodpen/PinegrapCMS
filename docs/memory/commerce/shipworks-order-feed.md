---
name: shipworks-order-feed
description: ShipWorks feed (getcount, getorders): which orders it sends (complete, exported, never cancelled), the exported mark's Audit trail line, no order status sent (#9138, #9142)
module: order_status
---

## Which orders does the feed send?
- `getcount` and `getorders` take orders changed after ShipWorks' `start` (`orders.last_modified_timestamp`) whose status is `complete` or `exported`: `pg_order_to_ship_sql()` (`includes/fn/order_status.php`), the same condition in both, so the count matches the download. The shipping report and sales reports take the same two statuses.
- Left out: `cancelled`, an order whose status was lost (`''`, the old single-L cancellation) and a cart (`incomplete`).
- Before #9142 both took every status but `incomplete`, so a cancelled order was counted and downloaded with its address and items as an order to ship, and sent again when the cancellation moved its stamp.

## What does ShipWorks still not see?
- `getmodule` declares `OnlineStatus supported="false"` and no `<Order>` carries a status. An order cancelled after ShipWorks downloaded it is no longer sent, but stays in ShipWorks as it was; the warehouse learns of the cancellation only from the store. Sending each order's status needs the ShipWorks module's status capability, untried against ShipWorks.

## What does a download write?
- A sent `complete` order becomes `exported` (`pg_order_export_mark_sql()`, only while still `complete`), and when the UPDATE changed it, the same Audit trail line as the order list's exports: `update`, status complete → exported (#9138). The request has no panel session, so the line's source is `job`.
- `updateshipment` writes tracking numbers through the shipment write path (`shipment-write-path.md`).

## Tests
- `tests/shipworks_order_feed_test.php` (status condition, both queries, the audit line), `tests/shipworks_write_path_test.php`.
