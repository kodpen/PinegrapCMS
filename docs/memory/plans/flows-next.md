---
name: flows-next
description: Plan (owner 2026-11-05): flow boxes other automation tools have and Pinegrap lacks, flows reaching the site's other areas, and ready templates; steps in flows-next-steps and -templates
---

## Why this plan?
- The owner asked (2026-11-05) for the most valuable features of other automation tools (Zapier, Make, n8n, Power Automate) in flows, for flows to reach the parts of the site they do not reach yet, and for more ready templates, tested and added, so that people need not build flows box by box: a counter flow built from boxes alone was too hard to follow.
- The comparison: those tools all have an answer step for people (Zapier Human in the Loop "Collect Data", n8n "Send and wait for response", Power Automate approvals with comments), formulas, record lookups, an AI step, text formatting, waiting until a date, a web address that starts a flow and a failure path. Pinegrap's edge is that flows run inside the site: channels, tasks, tables, orders, bookings and ERP records with their rights, no price per task, the site's number format, and drafts by Claude or Pinegrap AI in a channel.

## What holds for every step?
- General mechanisms only: a box or an event serves every scenario, never one example (the bakery flow on dev was an example).
- A new box: its kind in `ws_flow_kinds()`, its canvas form, its try, its outputs, its line in `ws_flow_kind_catalogue()` (read by Claude) and `ws_flow_ai_catalogue_hints()`, and its memory in `docs/memory/workspace/`.
- A new start event: its line in `ws_events_catalog()` (watch.php) with record, right and filter, its outputs in `ws_flow_event_outputs()` (flow_anchors.php), its parity line in tests/workspace_flows_parity_test.php.

## What is there already?
- 22 start events: orders (created, status, shipped, delivered, cancelled, return requested), stock.low, contacts, form.submitted, contact group, bookings, tasks, approval decided, a guest writes, an e-mail is received.
- Read a row, Repeat N times, date words in derived values (#6874, #6897, #6873, size warning #7000).
- The flow's owner is told when a run fails (`ws_flow_run_tell_owner()`), and `workspace.flow.run.failed` is a webhook event (`workspace-flows-run-events.md`), not yet a start.
- Pending with the architect: #7015 (an answer, number or note, from the people an approval asks) and #7016 (a Calculate rule with the workspace calculator): steps A1 and A2.

## Which questions are open? (for the architect)
1. AI box: which data may go to the AI backend (customer data, order lines), whose quota pays, may it wait like an approval, and may its answer pick a branch?
2. Wait until a date from a value: how far ahead (the delay box stops at a week, `WS_FLOW_DELAY_MAX`; a booking reminder needs weeks)?
3. A web address that starts a flow: allowed, with which rate limit and size cap?
4. E-mail to the person of the record (an order's customer, a form's sender, a booking's guest): today `to` is typed and never filled. Which recipients may a flow address, and how are commercial messages kept to people who consented?
5. A channel message as a start (words or a mention): allowed, and how is it kept from starting on every message?
6. Order of work: A after their decisions, then B1, B3, B5, C1, C2, with templates as their boxes land.

## Where to start?
- Steps B1, B3, B5, B6, C1, C2 and C3 need no decision; the templates marked `now` use boxes on development and can be written at once.
