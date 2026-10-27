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

- A2 `GET /workspace/flow-kinds`: every box kind with its settings, outputs and types (D1 of workspace-flows-data-passing.md); what AI and MCP build drafts from.

- A3 `POST /{id}/validate`: the publish check's problems without publishing (D4); `POST /{id}/dry-run` (F4e, PR #2024).

- A4 `/{id}/resume` (own session); `/{id}/versions` list and `/{id}/versions/{n}/restore` into the draft.

- A5 run values: `GET /workspace/flow-runs/{id}` gives each box's threads in, outputs, the flag and its result; `/steps/{node}/retry` and `/skip` (F5).

- A6 templates: `GET /workspace/flow-templates`; `POST /workspace/flows` with `template` and its settings (workspace-flows-templates.md).

- A7 events for webhooks and wake-ups: `workspace.flow.run.finished`, `.failed`, `.waiting`, carrying the run's result.

- A8 proposals: a `{type: flow}` draft card replaces `{type: scheduled}` (api_proposals.php, scheduled_draft.php).

## Which rules hold?
- Reads are MCP tools; a key or OAuth connection may write drafts; publishing, changing a live flow and resuming stay the person's own session (#1708 rule).
- Every new route goes into the OpenAPI schema (`php tools/check_api_schema.php`) with its test.

## Open questions
1. May a run started over the API name a channel to post its card in (C2), or is that the composer's alone?
