---
name: workspace-scheduled-actions-api
description: The /workspace/scheduled-actions routes were removed before 2026.11.0 shipped (PR #2107): why, what took their place, what stays (scheduled messages, the draft card)
---

## Are there scheduled-action routes in the external API?
- No. The `/workspace/scheduled-actions` routes (list, get, create, update, pause, resume, cancel; `includes/workspace/api_scheduled.php`, added in #1708, #1858, #1987) and their MCP tools were removed in #2107, before 2026.11.0 shipped, so no released site ever had them. There is no compatibility layer.
- `tests/workspace_proposals_test.php` fails if a route id starting `workspace.scheduled_actions.` comes back.

## Why were they removed?
- Owner decision (2026-10-27, `workflow/decisions-made-product.md`): scheduled work is a flow with a time, event or join start, so it goes through the flow routes (`/workspace/flows`, `workspace-flows-store.md`). The rule of #1708 moved with it: only the person, from their own team devices session, publishes, changes live or resumes a flow.
- More flow routes come in their place, not the same ones: `plans/workspace-flows-api.md`.

## What stays?
- `/workspace/scheduled-messages` (api_threads.php): a person's "Send later" messages are not flows.
- The panel's `ws_scheduled_*` actions and the engine in scheduled.php: the scheduled-action form and the flows' anchors still run on them until every action is converted (`plans/workspace-flows-channels.md`, C6).
- A connected app's `{type: scheduled, action: draft}` proposal (`workspace-scheduled-draft-proposals.md`): `data` is still the action as an object of name, rules, action, follow and channel_id; it becomes a flow draft card in C5b.
- The parity table lost its `api_routes` group and the `ws_api_scheduled_body_params` limit line with the file (`workspace-flows-parity-test.md`).
