---
name: flows-next
description: Plan (owner 2026-11-05): flow boxes other automation tools have and Pinegrap lacks, flows reaching the site's other areas, ready templates; steps in flows-next-steps (templates done)
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
- The flow's owner is told when a run fails (`ws_flow_run_tell_owner()`), and `workspace.flow.run.failed` is a webhook event (`workspace-flows-run-events.md`) and a start (B6, `workspace-flows-run-failed-start.md`).
- Decided 2026-11-05: #7015 (the approval box's answer) and #7016 (a Calculate rule): steps A1 and A2, `workflow/decisions-made-product-32.md` and `-33.md`.

## Which decisions bind the steps? (#7110, owner 2026-11-05, `workflow/decisions-made-product-33.md`)
1. AI box: yes, four modes, only the prompt's values go out, the site's quota, waits like an approval, classify picks a named outcome or `other`.
2. Wait until a date: up to 90 days.
3. A web address start: a sealed secret per flow, editors only, 60 calls a minute per address, 64 KB.
4. E-mail to the record's person: yes, `info` or `marketing`; marketing only with consent, with an unsubscribe link.
5. A channel start: only a mention of the flow by name; one start per person a minute.

## Where to start?
- Every step is free. A suggested order: A2, A1, B1, C1, B3, B5, B4, B2, B7, B8, C4. The templates T1-T14 are all merged; a new template follows `workspace/workspace-flows-template-adding.md`.
