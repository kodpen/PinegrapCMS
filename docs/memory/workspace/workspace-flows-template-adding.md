---
name: workspace-flows-template-adding
description: How a built-in flow template is added and tested (flow_templates.php, tests/workspace_flow_templates_test.php), which built-in templates exist and which memory file holds each
---

## Why ready templates?
- The owner asked (2026-11-05) for ready flows people pick instead of building them box by box: a counter flow built from boxes alone was too hard to follow. Steps T1-T14 added fourteen of them (#7113, #7116, #7435, #7454, #7461, #7486, #7505).

## How is a template added?
- An entry in `ws_flow_templates()` (`includes/workspace/flow_templates.php`) in the form `workspace-flows-template-format.md` gives, with the settings asked when it is used (`workspace-flows-template-setup.md`); every text through `php tools/tr_add.php`.
- An event template is offered only when its event is in the viewer's catalogue (`ws_events_js_config()`), as `stock_event` is; a template waits until every box and event it uses is on development.
- A general mechanism only: a template is built from boxes and events that serve every scenario, never a box made for the one template.
- A built-in template asks no stock table or column: a row box needs a table only the person knows (`goods_received` tells instead).
- Up to three new templates in one PR.

## How is it tested?
- `tests/workspace_flow_templates_test.php` checks every template's filled graph without a database: shape, `ws_flow_graph_check()`, settings pointing at fields, translated texts, the start it needs. The default test fill offers every event, so a new event template is covered by these at once.
- Plus one test for what the new template must hold (its arrows on the right outcomes, its configs), e.g. `test_workspace_flow_templates_cart_quote_follow_up`.
- Then a run on dev from the template with sample data, its runs read back, and the dev flow binned.

## Which built-in templates are there, and where is each?
- Scheduled ones that came over from scheduled actions, the gallery and the format: `workspace-flows-template-format.md`, `workspace-flows-template-last-day.md`.
- new_customer, quote_approval, text_review: `workspace-flows-template-flow-only.md`.
- review_request, restock_approval, return_task, booking_cancelled, order_cancelled, booking_reminder, failed_runs, invoice_overdue_follow_up, cart_quote_follow_up, low_review, subscription_failed: `workspace-flows-template-events.md`.
- goods_received, cash_count (an approval with a number answer): `workspace-flows-approval-answer.md`.
- form_sort (Pinegrap AI picks sales, support or spam): `workspace-flows-template-form-sort.md`.
- Saved on the site by people: `workspace-flows-template-saved.md`, `workspace-flows-template-table.md`; over the API: `workspace-flows-api-templates.md`.
