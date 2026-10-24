---
name: order-returns-api-timing
description: Returns over the API and the customer's timing rules (owner 2026-10-24): staff-like by default, customer_rules and its 409s, OrderReturn.withdrawal, pg_return_timing() shared with the panel
---

## Is a request through the API held to the customer's timing rules?
- Not by default (owner decision 2026-10-24): helpdesk, ERP and fulfilment tools record late or unusual returns and staff decide, as on the panel. Rules skipped: goods that have left, a known delivery date, the open withdrawal window.
- `customer_rules: true` holds it to them, for an app acting for the customer (a headless storefront, a shopping assistant). The handler passes the option `customer_rules` to `pg_return_create_check()` / `pg_return_create()`; `pg_return_customer_rules($source, $options)` (pure) decides: the customer always, `api` only with the option, staff never. The source stays `api`, so the repeat is still looked for among the app owner's requests.
- Refusals, by the check's code (`api_orders_returns_refuse()`): `not_sent` → 409 `not_sent` (cancel the order instead), `delivery_date_unknown` → 409 `delivery_date_unknown`, `window_closed` → 409 `return_period_ended`. The model's sentences, as My Account shows them; a rehearsal answers the same 409. Without the option nothing changed.

## How does an OrderReturn say whether it came in time?
- `withdrawal` (`OrderReturnWithdrawal?`): `delivered_on`, `delivered_estimated`, `deadline`, `requested_in_time`; null when no date is known for the delivery or it is gone. A staff-like integration sees a late request too.
- One reading for both: `pg_return_timing($window, $requested_day)` (pure). `view_return.php` calls it; its HTML stayed byte-identical apart from the header clock (sandbox: estimated in time, estimated late, no date, known in time, known late).
- The window is the delivery as known today (`pg_return_delivery_state()`), the day the request's own: a delivery date learned later moves the deadline and can change the answer, as on the panel.
- No query per row: `api_order_return_windows($rows)` reads each delivery once; GET and both POST answers pass its map to the presenter, which runs no query (a delivery missing from the map answers null).

## Tests?
- `tests/returns_withdrawal_test.php` (timing cases, the deadline day in time, the rule helper, the check and the panel use them), `tests/api_order_returns_test.php` (withdrawal shape and schema, every presenter call in both handlers passes the windows map, `customer_rules` row, the three 409s). Sandbox: PR #1572 body.
