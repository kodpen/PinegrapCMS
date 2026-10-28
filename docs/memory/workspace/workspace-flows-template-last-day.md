---
name: workspace-flows-template-last-day
description: The forms, orders and chain flow templates (R1b, #2787): counts of the last day once a day, the orders webhook, chain a manual start a "Start a flow" box starts
---

## How did forms, orders and chain come over? (#2787, #2753 answer 2a)
- A flow's count reads the last day (its hidden anchor has no last run), not "since the last run": hourly it would repeat the same count, so forms and orders run once a day and their texts say "in the last day". A "since the last run" count for the condition box would be a step of its own.
- forms: daily 09:00 (tomorrow), `forms_new` > 0, tell; its title is new ("every day"; the test lists it in `ws_flow_templates_test_reworded()`). Needs `forms_new`.
- orders: daily 18:00 (today) so "Sales today" is the day's, `orders_new` > 0, a webhook box (url '' asked by the url setting, format text). Needs `orders_new` and `sales_today`.
- chain: a manual start telling `{{source}} finished: {{source_result}}`; a "Start a flow" box of another flow starts it and fills both (`ws_flow_chain_context()`). Offered to everyone.

Related: workspace-flows-template-format.md
