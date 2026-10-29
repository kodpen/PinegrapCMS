---
name: order-completion-after-lines
description: Order completion stage functions after the product loop (A2d1, #2914): private access, reward points and goal, contact groups, abandoned campaigns, visitor record, log and notification
module: order_completion
---

## Which functions? (A2d1, #2914)
- Called by `pg_order_complete()` after `pg_order_complete_lines()` (#2972; `tests/order_completion_test.php` pins the order), in this order:
- `pg_order_complete_private_access($user_id)`: private access for ever to `ECOMMERCE_PRIVATE_FOLDER_ID`, not over edit rights or a never-ending access (`pg_order_folder_access_extends()`).
- `pg_order_complete_rewards($user_id, $contact_id, $reward_points, $order_number, $billing_email_address)`: adds the points; the order that first reaches the goal grants or extends the membership (order number as member id when none) and e-mails the reward page. Returns true when the membership was given; the completion adds `MEMBERSHIP_CONTACT_GROUP_ID`.
- `pg_order_complete_contact_groups($contact_id, $contact_groups)`: each group once, a deleted group skipped.
- `pg_order_complete_abandoned_campaigns($order_id, $contact_id, $billing_email_address)`: ready or paused `order_abandoned` campaigns of the order or the same customer cancelled.
- `pg_order_complete_visitor($visitor_id, $total, $billing)`: the visit's `order_completed`, total, billing place. The context's `visitor_id` (`submit_order()` passes the session's); a payment service's message has none (0 → nothing).
- `pg_order_complete_announce($order_id, $order_number, $total, $billing)`: the "Order was completed" log line and the `new_order` notification.
- Pure: `pg_order_line_reward_points($line, $program_on)` (points × quantity, summed in the loop), `pg_order_reward_membership_until()` (0 days → `0000-00-00`; else `pg_order_extended_until()`; a lifetime end gets the days from today, as before).

## What stays in submit_order()?
- The post-save PHP hook (`eval` over the request's variables) runs after `pg_order_complete()` returns, then `pg_erp_auto_invoice($order_id, 'order_paid', true)`, so the hook still comes before the invoice. A payment service's message (A3) calls the invoice itself; it has no hook (`order-completion.md`).

## What changed?
- The folder access read and the queries are `db_q()` with placeholders; a failed query ends the request (`output_error`), as the other completion queries do. Before, a failed access read counted as no row.

Related: commerce/order-completion.md, commerce/order-completion-lines.md
