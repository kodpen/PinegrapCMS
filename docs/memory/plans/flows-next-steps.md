---
name: flows-next-steps
description: Steps A1-C4 of plans/flows-next.md: answers and formulas, new flow boxes, and site events flows can start on; claim as [plan:flows-next] <step>
---

## How are the steps taken?
- Claim one with a draft `[plan:flows-next] <step> — <topic>`. S under 400 product lines, M about 1,000. `free` steps run side by side when they touch different files; a step marked `after` waits for that step's merge or that decision. Tests in `tests/workspace_flow_*_test.php`, red first; texts through `tr_add.php`; `php tools/build_js.php` after workspace_flow.js.

## A: answers and formulas (decided; shapes in `workflow/decisions-made-product-33.md`, as B2-C4's)
- A1 (free, M): the answer of an approval box (#7015): settings `answer` none/text/number and `answer_label`, the card's Approve asks, outputs `answer`, `answer_number`, `decided_by`; no schema. flow approval box, approvals.php, workspace_approvals.js.

- A2 done (#7260): the Calculate rule, `workspace/workspace-flows-derived-calculate.md`.

## B: new boxes (what other tools have)
- B1 (done, PR #7388): Read a record (`record_get`): an order, product, contact, booking, task or form submission by id (a value), its fields as outputs, read with the run owner's rights as the change box checks them; `found` 0/1 instead of failing. As Zapier's Find and Make's Search steps. new flow_record_get.php, steps.php, the form.

- B2 (free, M then M): Pinegrap AI box: a prompt with values only, through `pg_ai_chat()` on the site's quota; modes write, summarize, extract, classify into the named outcomes that become its arrows (else `other`); waits like the approval box.

- B3 (done, #7411): text rules in the derived value box: replace, extract a number, extract an e-mail address, upper or lower case, trim and cut to N characters, split and take part N. As Zapier Formatter. flow_values.php.

- B4 (free, S): Wait until: the delay box waits until a date and time read from a value (a booking's start, minus N hours); the cap rises to 90 days. flow_runs.php delay, the form.

- B5 (done, PR #7408): add to a cell: a `row_set` cell may add to the number it holds in the same write (`+3`), so two runs never lose an addition (seen on dev: two shifts read one row, one addition was lost). wtable store, steps.php.

- B6 (done, PR #7410): a failed run as a start: `workspace.flow.run.failed` in `ws_events_catalog()` (flow, box, its sentence), so a team, not only the owner, hears of it; a flow never starts on its own failure.

- B7 (free, M): a web address that starts a flow: a sealed secret per flow, editors only, renewable; only a published flow, in its owner's name; body fields as `trigger.*`; 60 calls a minute per address (WAF), 64 KB; its own root file.

- B8 (free, M): e-mail to the record's person: recipient `record_person`, never a typed value; purpose `info` or `marketing`, marketing only with consent, else skipped with its reason, with an unsubscribe link.

## C: flows reaching the rest of the site
- C1 (free, S, done #7261): events the site sends as webhooks (`api_webhook_events()`) but flows cannot start on: product.review.created, order.return.approved/received/refunded, cart_quote.created/ordered, marketplace.order.imported, subscription.failed/renewed/cancelled, form.paid, inventory.changed. Their lines in `ws_events_catalog()` with record, right, filter and outputs. watch.php.

- C2 (done, PR #7416): people and content: user.created, customer.consent_changed, mail.bounced, campaign.sent, page.created/updated, calendar_event.reserved, credit.issued/expired. watch.php.

- C3 (free, M): ERP events not sent today: an invoice issued, paid and overdue, a payment received, an e-document received or answered, a sales quote accepted or rejected; `ws_event()` where the ERP writes them (`docs/memory/erp/`), then their catalogue lines.

- C4 (free, M): a channel message as a start: only a mention of the flow by name in the named channel; never from flows or agents; one start per person a minute.
