---
name: workspace-flows-api
description: Plan (owner request 2026-10-27, draft): flow API beyond the removed scheduled-actions routes; filters, box catalogue, validate, dry run, versions, run values, retry, templates, events
---

## Why?
- The `/workspace/scheduled-actions` routes were removed before 2026.11.0 ships (workspace-flows-channels.md, C5a #2107). The owner asked for more in their place, not the same.

## What is there today?
- Routes in `ws_api_routes()` (includes/workspace/api.php), handlers in api_flows.php: `/workspace/flows` list, create; `/{id}` get, update, delete; `/{id}/publish`, `/{id}/pause`; `/{id}/runs` list, start; `/{id}/shares`; `/workspace/flow-runs/{id}` get, `/cancel`.
- Publish is the person's own session only and no MCP tool (workflow/decisions-made-product.md).

## What comes? (one PR each, in this order)
- A1 done (PR #2122): list filters `start`, `status`, `channel_id`, `updated_since`; `GET /{id}` gives `start` and `start_text` (workspace-flows-api-filters.md).

- A2 done (PR #2263): `GET /workspace/flow-kinds` (workspace-flows-catalogue.md).

- A3 done (PR #2263): `GET /{id}/validate` (GET: it writes nothing); box try is `/{id}/boxes/{node}/try` (F4e, PR #2024).

- A4 done (PR #2234): `/{id}/resume` (own session); `/{id}/versions` list, `/{n}` read, `/{n}/restore` into the draft (workspace-flows-versions.md).

- A5 run values done (PR #2284): each box's `threads_in` and `outputs` (workspace-flows-run-values.md); retry and skip were already routes (F5a, #2098). The run's flag and its result come with C1 of workspace-flows-channels.md (#2385).

- A6 templates done (PR #2298): `GET /workspace/flow-templates`; `POST /workspace/flows` with `template` and `answers` (workspace-flows-api-templates.md). Never publishes.

- A7 done (PR #2388): `workspace.flow.run.finished`, `.failed`, `.waiting` (workspace-flows-run-events.md). The run-level result waits for C1, as A5's flag does. Follow-up (decided in #2406): a fourth event `workspace.flow.run.cancelled`, announced once from the cancel's conditional UPDATE with the same body as the other run events, in a small PR.

- A8 proposals: a `{type: flow}` draft card replaces `{type: scheduled}` (api_proposals.php, scheduled_draft.php).

## Where to start?
- Next: A8 (proposals), not claimed. Check open PRs on api_proposals.php and scheduled_draft.php before claiming.

## Which rules hold?
- Reads are MCP tools; a key or OAuth connection may write drafts; publishing, changing a live flow and resuming stay the person's own session (#1708 rule).
- Every new route goes into the OpenAPI schema (`php tools/check_api_schema.php`) with its test.

## Which questions are answered?
1. Answered (#2413): yes, an optional `channel_id` on the run start, accepted when the starter reads it and the flow's owner may post there (C2's rule), else 403; the home without it.
