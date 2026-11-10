---
name: workspace-flows-template-events
description: The flow templates started by a site event that wait, ask for approval or follow up (T1-T5, T10-T13): their boxes, what each is offered with, how they are tested
---

## Which are they?
- All in category Sales, each offered only when its event is in the viewer's `events` (`ws_events_js_config()`), built with the `$on`, `$task` (task_open, due in 2 days, linking the record) and `$tell` (a post to the channel open, else a notify to the person) helpers of `ws_flow_templates()`.
- `review_request` (#7113): order.delivered → delay 7 days (a Wait box takes up to 90 days, `WS_FLOW_DELAY_MAX`) → a task to ask the customer for a review.
- `restock_approval` (#7113): stock.low → approval in a channel, 48 h → task_open on approved, notify on rejected or expired. Unlike `stock_event`, nothing is ordered before somebody approves.
- `return_task` (#7113): order.return.requested → a waiting task (due in 2 days, 48 h timeout) and a line at once → notify on timeout.
- `booking_cancelled` (#7116): booking.cancelled → a line and a task to offer another time.
- `order_cancelled` (#7116): order.cancelled (by the customer or the shop) → a line and a task to follow it up.
- `booking_reminder`: booking.confirmed → Read a record (the booking) → delay until `{{node.read.start}}` less 1 day → read it again → condition status = confirmed → notify the host (person setting) and an e-mail to the record's person (`info`). The second read keeps a cancelled booking from being reminded and gives its start as it is then; booking events declare no `start_at` output, the record box gives `start` in the site's zone, which the delay reads.
- `failed_runs` (category Team): workspace.flow.run.failed narrowed to a channel → a post in that same channel (one channel setting for both) with `{{record_title}}` (the flow), the run id and `{{message}}` (box: sentence). Always a channel, never the person alone: the owner is already told (`ws_flow_run_tell_owner()`); narrowing keeps a private channel's run out of another channel.
- `cart_quote_follow_up` (T13, #7486): cart_quote.created → delay 2 days → Read a record (the cart quote) → condition `{{node.read.status}}` eq open → a task to call the customer. Read after the wait: a quote ordered, cancelled or expired meanwhile opens nothing. E-mail after B8.
- `invoice_overdue_follow_up`: erp.invoice.overdue → a waiting task to collect it (due in 7 days, timeout 168 h) → notify on timeout. No box reads an invoice (record_get has no invoice kind), so the task stands for the payment: done once collected.

## Why no template for every received e-mail?
- `workspace.mail.received` fires on every incoming mail, replies too, and carries no "new conversation" output; the mail module already tells the holder (`mail-notices-and-event.md`). A task per mail would double that and its timeout would fire on tasks left open after the mail was answered. The plan's T5 became `order_cancelled` instead.

## Which follow a review or a failed payment? (T8, T9)
- `low_review` (`product.review.created`): a condition `{{trigger.rating}}` lte 2 (a review without stars has no rating and never holds), then a task and a line. `subscription_failed`: the person told and a task to contact the customer (the record is the contact). Test `test_workspace_flow_templates_low_review_and_failed_subscription`.

## How are they tested?
- `test_workspace_flow_templates_reminder_failed_runs_overdue`: T10-T12's arrows, settings and the configs above.
- `test_workspace_flow_templates_event_follow_ups`: each arrow on an outcome of its box, the filled graph passes `ws_flow_graph_check()`, a person setting (and a channel for the approval), the line to the channel open or the person. The default test fill offers their events, so the shape, settings and text tests cover them too.

Related: workspace-flows-template-format.md, workspace-flows-template-adding.md
