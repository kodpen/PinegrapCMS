---
name: flows-next-steps
description: Steps A1-C4 of plans/flows-next.md: answers and formulas, new flow boxes, and site events flows can start on; claim as [plan:flows-next] <step>
---

## How are the steps taken?
- Claim one with a draft `[plan:flows-next] <step> — <topic>`. S under 400 product lines, M about 1,000. `free` steps run side by side when they touch different files; a step marked `after` waits for that step's merge or that decision. Tests in `tests/workspace_flow_*_test.php`, red first; texts through `tr_add.php`; `php tools/build_js.php` after workspace_flow.js.

## A: decided by the architect first
- A1 (after #7015, M): the answer of an approval box (a note or a number read by the workspace calculator) as outputs for later boxes. flow approval box, workspace_approvals.js.

- A2 (after #7016, S): a Calculate rule in the derived value box with `ws_calc_evaluate()`. flow_values.php, the value form.

## B: new boxes (what other tools have)
- B1 (free, M): Read a record (`record_get`): an order, product, contact, booking, task or form submission by id (a value), its fields as outputs, read with the run owner's rights as the change box checks them; `found` 0/1 instead of failing. As Zapier's Find and Make's Search steps. new flow_record_get.php, steps.php, the form.

- B2 (after question 1, M then M): Pinegrap AI box: a prompt with values, through the site's AI backend (`pg_ai_chat()`); modes write text, summarize, extract a number or field, classify into named outcomes that become its arrows.

- B3 (free, S): text rules in the derived value box: replace, extract a number, extract an e-mail address, upper or lower case, trim and cut to N characters, split and take part N. As Zapier Formatter. flow_values.php.

- B4 (after question 2, S): Wait until: the delay box waits until a date and time read from a value (a booking's start, minus N hours). flow_runs.php delay, the form.

- B5 (free, S): add to a cell: a `row_set` cell may add to the number it holds in the same write (`+3`), so two runs never lose an addition (seen on dev: two shifts read one row, one addition was lost). wtable store, steps.php.

- B6 (free, S): a failed run as a start: `workspace.flow.run.failed` in `ws_events_catalog()` (flow, box, its sentence), so a team, not only the owner, hears of it; a flow never starts on its own failure.

- B7 (after question 3, M): a web address that starts a flow: a secret URL per flow, its body's fields as outputs, a rate limit.

- B8 (after question 4, M): e-mail to the record's person: the e-mail box's recipient may be the order's customer, the form's sender or the booking's guest, never any typed value; commercial ones only with consent.

## C: flows reaching the rest of the site
- C1 (free, S, done #7261): events the site already sends as webhooks (`api_webhook_events()`) but flows cannot start on: product.review.created, order.return.approved/received/refunded, cart_quote.created/ordered, marketplace.order.imported, subscription.failed/renewed/cancelled, form.paid, inventory.changed. Their lines in `ws_events_catalog()` with record, right, filter and outputs. watch.php.

- C2 (free, S): people and content: user.created, customer.consent_changed, mail.bounced, campaign.sent, page.created/updated, calendar_event.reserved, credit.issued/expired. watch.php.

- C3 (free, M): ERP events not sent today: an invoice issued, paid and overdue, a payment received, an e-document received or answered, a sales quote accepted or rejected; `ws_event()` where the ERP writes them (`docs/memory/erp/`), then their catalogue lines.

- C4 (after question 5, M): a channel message as a start: words or a mention, in one channel (a guest's message and a received e-mail are starts already).
