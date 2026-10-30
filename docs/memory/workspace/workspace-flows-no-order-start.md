---
name: workspace-flows-no-order-start
description: Flows start "a customer has not ordered for N days" (CRM seams 10.2, decided in #3416, PR #3522): hourly anchor, threshold window, start key per customer and last order, budget, outputs, rights
---

## What is it?
- Start box `no_order` ("When a customer stops ordering"), an anchor kind (`ws_flow_anchor_kinds()`, rule `at`). Setting `days` (1-3650, new box 30, `ws_flow_anchor_normalize('no_order')`). In the start filter it counts as `event` (`ws_flow_start_names()`). Code `includes/workspace/flow_no_order.php`; tests `tests/workspace_flow_no_order_test.php`.
- No hard-wired task and no site setting: the flow decides what happens (task, notify, post…). No schema.

## How does it scan?
- The anchor's rule is an hourly `at` rule from the publish moment (`ws_flow_no_order_rule()`, first scan an hour later); N stays in the box. Each anchor run, `ws_flow_anchor_start()` hands the box to `ws_flow_no_order_scan()` instead of starting one run.
- A customer is a contact (`orders.contact_id > 0`) with orders in status `complete`/`exported` (incomplete and cancelled never count, so a cancelled order does not reset). Crossed: last order date + N days <= now, and > now - `WS_FLOW_NO_ORDER_WINDOW` (25 days) (`ws_flow_no_order_crossed()`). Customers who never ordered start nothing.
- Read by the `idx_order_date` range only: pages of 500 contacts by their latest order in the window, oldest first, at most 10 pages an hour; contacts with an order after the threshold dropped (`$later`), the last order per contact picked (`ws_flow_no_order_last()`).

## How is it de-duplicated without schema?
- Each run's `ws_flow_runs.start_key` is `noorder:<contact id>:<last order id>` (`ws_flow_no_order_start_key()`), under the unique (flow_id, start_key): the same customer and last order start once; a second scan reads the existing keys first and skips them (`ws_flow_no_order_pick()`), and a key taken by a parallel scan is a refused insert. A new order changes the last order, so the key, and the count starts again.
- Why the 25-day window: ended runs are swept after `WS_FLOW_RUNS_KEEP` (30 days); a crossing is only looked at while its run (the key) is surely still kept, so a swept run never brings the customer back. A customer whose crossing is older than the window when the flow is published is not started.

## Which budget?
- `ws_flow_no_order_budget()`: what the flow has left of `WS_FLOW_STARTS_HOURLY` and `WS_FLOW_MAX_OPEN_RUNS`, and for a role-3 owner of `WS_FLOW_MEMBER_STARTS_HOURLY` over all their flows. Customers left over wait for the next hour (their crossing stays true within the window).

## What does a run carry?
- `subject_type` `contact`, `subject_id` the contact (runs about a customer, as a run started with `contact_id`); context `subject_name` and `customer`: contact_id, name, email, last_order_id, last_order_number, last_order_date (Y-m-d), days_since. Start box result "Started on its own for <name>."
- `{{trigger.*}}` (`ws_flow_no_order_outputs()` via `ws_flow_trigger_outputs()`): those seven keys; `ws_step_trigger_values()` fills them only while the owner still holds both rights and may open the contact (`ws_step_trigger_values_for()`), else all ''.

## Who may use it?
- Builders with both the orders (`ecommerce`) and contacts rights (`ws_flow_no_order_refusal()`), as the order and contact events ask. Publish refuses otherwise (`ws_flow_anchor_check()`); each scan asks again and reads only contacts the owner may open (`ws_viewer_contacts_open()`, the address book's row rule).
- Role-3 members: allowed (`ws_flow_member_kinds()`), by the #3345 rule that a member may start on events they can see: with both rights they see these orders and contacts; the member start cap applies.
- Canvas: palette entry only while `FL.steps.no_order` (`ws_flow_js_config()`); `noOrderFields()` (days), meta `fl_no_order_meta`, strings `fl_kind_no_order`, `fl_no_order_*`; line icon `FLOW_ICONS.no_order` (workspace.js).
