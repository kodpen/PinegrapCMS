---
name: order-returns
description: Customer return model (includes/fn/returns.php; order_returns, order_return_items, step 11.0.11): statuses and moves, held quantities, withdrawal window, refund deadline, what callers check
---

## What is stored?
- `order_returns`: one request to send back goods of one delivery (`ship_to_id`) of a `complete` or `exported` order: reason code (`pg_return_reasons()`), the customer's words, the decision note, carrier and return code, tracking number, `source` (customer, panel, api), who asked and who decided, one time column per status, the reminder stamps (11.0.25, order-returns-reminders.md) and the refund (11.0.34, order-returns-refund-columns.md). `order_return_items`: how many of each order line, one row per line and return.
- Both InnoDB; a request is written in one transaction. `status` is a VARCHAR checked in code, not an ENUM.
- Built in step 1 of `plans/customer-returns-flow.md` (PR #650). Customer: order-returns-customer.md; panel (PR #808): order-returns-panel.md; refund: order-returns-refund.md; API: order-returns-api.md; events: order-returns-events.md.

## Which statuses and moves are there?
- requested → approved, rejected, closed; approved → shipped, received, closed; shipped → received, closed; received → refunded, closed; refunded → closed. Rejected and closed are final: the customer asks again instead.
- A customer may only withdraw a request (requested → closed) and say the parcel left (approved → shipped). The caller says who asks (`by`).
- `pg_return_move()` updates `WHERE status = <the status it read>`, so two people answering at once cannot both win. Approve/reject write `decided_at`, `decided_by` and `decision_note`; the other statuses their own column.

## How much of a line can still be asked for?
- Its quantity less what the order's returns hold. Every open status holds; rejected frees; closed frees only when nothing was sent, received or refunded (`pg_return_holds_goods()`). Worked out in PHP from the rows (`pg_return_held()`), not in SQL, so there is one rule.
- `pg_return_create()` takes `GET_LOCK('pg_return_<database hash>_<order id>', 5)` around the quantity check (re-run there after `pg_return_create_check()`) and the insert. Sandbox race, three requests for the last unit: one winner in 15 rounds of 15; without the lock every round had several.

## How is the withdrawal window worked out?
- 14 days counted from the day after delivery, open up to and including the deadline, and before delivery too. Delivery is known when every numbered parcel has `shipping_tracking_numbers.delivered_at` (11.0.20): the day the last one arrived (`pg_return_parcel_delivery()`, read since PR #808; written by `pg_shipment_delivered()`, `shipment-write-path.md`). Otherwise it is the latest of `ship_tos.ship_date` + 3 days, `ship_tos.delivery_date` and the day a parcel that did arrive arrived (`parcel_delivered`). The first two may be dates the checkout planned; the latest never cuts the legal period short.
- No date at all: a customer request is refused with "contact us"; staff record it. A customer request also needs the goods to have left: a tracking number, a shipped quantity or a ship date that has come (`pg_return_goods_left()`); before that it is a cancellation, not a return.
- Refund deadline (`pg_return_refund_deadline()`): 14 days after `shipped_at`, when the goods reached the carrier (RG 32909, 24 May 2025), else after `received_at`.

## What does a caller still check itself?
- That the order is the customer's (`orders.user_id`), or the person's commerce rights; the activity log, the mails and the bell (`order_return.php` does all three).
- Before the upgrade `pg_returns_ready()` is false: reads give nothing, create and move refuse with "not installed yet". `delete_order()` removes an order's returns (`pg_returns_delete_for_order()`).

## Where are the tests?
- `tests/returns_test.php` for the pure rules. The database paths (create, move, held quantities, delete, the race, the step run twice) were run on a `tools/setup_sandbox.sh` sandbox.
