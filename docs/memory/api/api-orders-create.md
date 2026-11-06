---
name: api-orders-create
description: POST /orders records a counter (local) sale through the counter screen's completion: lines, customer, payment, the checks before the write, dry run, Idempotency-Key, what it answers
---

## What does POST /orders create?
- A local sale, the order `add_order.php` completes (`includes/api/resources/orders_create.php`, PR #6148). No online order: no shipping address, payment gateway, offer or shipping cost. `orders:write`, `dry_run`, 201 `LocalSale` (`order` as GET /orders/{id}, `invoice`, `receipts`, `warnings`).
- The owner of the application is the operator: `orders.user_id` and the ERP rights (`local_sale_can_erp()`, `local_sale_can_cash()` read `manage_erp*` from `api_load_owner()`'s permission columns) decide whether the money is recorded, as at the till.

## How is it built?
- `pg_local_order_items_input()` reads `items` ({product_id, quantity}, whole numbers, 1 to 9999; one product named twice is one line). Each product must exist and be switched on; a donation enters at price 0 as at the counter.
- The customer is `pg_local_order_customer()` (the screen's `local_sale_set_customer()` uses it): a contact with its ERP account, or an ERP account with the ERP on; both sent → 422. No role-3 row rule: the counter screen does not narrow its customer search either.
- The lines are figured in memory (`pg_local_order_lines()`, the cart's own figures) and checked by `pg_local_order_check($user, 0, …)`; `api_dry_run_stop()` follows. Then `pg_local_order_open()` writes the incomplete order and its lines (`type = 'local'`, base currency, product price, `ship_to_id = 0`) and `pg_local_order_complete()` completes it (`erp/erp-counter-sale-completion.md`): number, stock, `order.created`, invoice, receipts.
- A completion refused after the write (a till sold the stock meanwhile) calls `pg_local_order_discard()`: no basket is left behind; the caller gets the 422.

## What do the refusals say?
- 422 `items` names the product (`Product 7: Only 2 in stock; lower the quantity.`); a cash `received` short of the total → `received`; split errors → `payment_parts` (`payment_parts` only with `split`, needs the ERP and the cash right).
- A `payment_parts` item that is not an object or whose `amount` is not a whole number from 0 up (a decimal, `"12.50"`, a negative, missing) is 422 `payment_parts` naming the item (`api_local_sale_parts()` `bad`); digit strings from a form body are taken. Before, it was read as 0, which the counter skips as an empty part, so the money it stood for was booked as owed (bug hunt, PR #7009).
- An invoice or receipt that could not be recorded is not a refusal: the order stays complete and the reason is in `warnings`, as on the screen.

## Idempotency?
- The bootstrap's Idempotency-Key covers it (`api-idempotency-key.md`): a retry answers the first sale. Without a key a retried call sells again; the description says to send one.
