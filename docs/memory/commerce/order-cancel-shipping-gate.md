---
name: order-cancel-shipping-gate
description: Who the shipping gate blocks on order cancel (customer only, by tracking code), why ship_date is ignored, USER_MANAGE_ECOMMERCE check, validate_user id key.
---

## Who is blocked by the shipping gate?
- Admin/operator is NEVER blocked: they have context the code lacks (customer called, parcel returned, duplicate order). In `process_order_cancellation()` the gate is wrapped in `!$is_admin`.
- A customer is blocked only if a tracking code exists. Criterion `_order_has_shipped()`:
  - order-wide: `orders.tracking_code`;
  - per recipient (multi-recipient): `shipping_tracking_numbers.number` (empty rows do not count).
- `$is_admin` is now load-bearing: besides the log note it bypasses the shipping gate. NEVER pass `true` from a visitor-facing path without verifying the role.

## Why is `ship_date` deliberately ignored?
- Many installs fill it at order time as the PLANNED ship date; the old rule that looked at it closed cancellation for customers on orders that had not left the building.
- The ERP bridge reads the same column AS the ship date (`erp_order_shipment()`, 2026-09-17), but with a future-date gate: the earliest non-zero `ship_tos.ship_date`, only if not later than today. A planned date never becomes the invoice ship date.

## How is "operator" decided in `cancel_order.php`?
- "Operator" does not mean role 0. `cancel_order.php` checks the `USER_MANAGE_ECOMMERCE` constant (= `validate_ecommerce_access()` rule: role < 3, or role-3 user with the `manage_ecommerce` flag). It used to check `USER_ROLE === 0`.
- Why: `view_orders.php` admits Manager and Designer too; their per-row İptal fell into the customer branch and was rejected as "not the owner of this order" while bulk cancel on the same screen worked (`edit_orders.php` passes `true` unconditionally). Same screen, same user, two answers.

## Which key does `validate_user()` return?
- `validate_user()` returns the PK under the key `id`, NOT `user_id`. (`user_id` is the raw column name; `api.php` and `includes/api/auth.php` of the external API use that name.)
- Reading the wrong key wrote `cancelled_by = 0`, which reports read as "customer cancelled it themselves".

Related: docs/memory/commerce/order-cancellation-flow.md
