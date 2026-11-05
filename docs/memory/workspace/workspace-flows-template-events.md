---
name: workspace-flows-template-events
description: The flow templates started by a site event that wait, ask for approval or follow up (plan flows-next T1-T5): their boxes, what each is offered with, how they are tested
---

## Which are they?
- All in category Sales, each offered only when its event is in the viewer's `events` (`ws_events_js_config()`), built with the `$on`, `$task` (task_open, due in 2 days, linking the record) and `$tell` (a post to the channel open, else a notify to the person) helpers of `ws_flow_templates()`.
- `review_request` (#7113): order.delivered → delay 7 days (`WS_FLOW_DELAY_MAX` is a week) → a task to ask the customer for a review.
- `restock_approval` (#7113): stock.low → approval in a channel, 48 h → task_open on approved, notify on rejected or expired. Unlike `stock_event`, nothing is ordered before somebody approves.
- `return_task` (#7113): order.return.requested → a waiting task (due in 2 days, 48 h timeout) and a line at once → notify on timeout.
- `booking_cancelled`: booking.cancelled → a line and a task to offer another time.
- `order_cancelled`: order.cancelled (by the customer or the shop) → a line and a task to follow it up.

## Why no template for every received e-mail?
- `workspace.mail.received` fires on every incoming mail, replies too, and carries no "new conversation" output; the mail module already tells the holder (`mail-notices-and-event.md`). A task per mail would double that and its timeout would fire on tasks left open after the mail was answered. The plan's T5 became `order_cancelled` instead.

## How are they tested?
- `test_workspace_flow_templates_event_follow_ups`: each arrow on an outcome of its box, the filled graph passes `ws_flow_graph_check()`, a person setting (and a channel for the approval), the line to the channel open or the person. The default test fill offers their events, so the shape, settings and text tests cover them too.

Related: workspace-flows-template-format.md, plans/flows-next-templates.md
