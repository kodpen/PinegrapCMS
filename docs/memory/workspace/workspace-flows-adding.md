---
name: workspace-flows-adding
description: How a new flow box or start event is added: a general mechanism, never one example; every place it is named (kinds, catalogue, AI hints, events catalogue, outputs, parity test)
---

## What may a new box or event be?
- A general mechanism that serves every scenario, never one made for one example (the bakery flow on dev was an example). Ready flows for one need are templates: `workspace-flows-template-adding.md`.
- Why flows grow this way (owner, 2026-11-05): people get the steps other automation tools have (an answer from a person, formulas, record lookups, an AI step, text rules, waiting until a date, a web address start, a failure path), while flows run inside the site with its records, rights, number format and drafts by Claude or Pinegrap AI, at no price per task. The decisions on those boxes: `workflow/decisions-made-product-33.md`.

## Where is a new box named?
- Its kind in `ws_flow_kinds()` (`includes/workspace/flows.php`), its canvas form, its try (`workspace-flows-box-try.md`), its outputs, its line in `ws_flow_kind_catalogue()` (read by Claude, `workspace-flows-catalogue.md`) and in `ws_flow_ai_catalogue_hints()` (`flow_draft.php`), and its memory topic in `docs/memory/workspace/`.

## Where is a new start event named?
- Its line in `ws_events_catalog()` (`watch.php`) with record, right and filter; its outputs in `ws_flow_event_outputs()` (`flow_anchors.php`); its line in `tests/workspace_flows_parity_test.php`.
