---
name: workspace-flow-draft-proposals
description: An application proposes a flow as a draft card ({type: flow} in workspace.changes.propose, A8): what is sent, checked, kept, how the card opens it, what happens to {type: scheduled}
---

## What does the application send?
- One more change in `workspace.changes.propose` (channel route only; the task twin answers 422): `{type: flow, action: draft, data, reason}`. `data` is the flow as `POST /workspace/flows` takes it: name, nodes, edges, notes, or template (+ answers), and channel_id (its home).
- Shape first (`ws_flow_draft_proposal_input()`, pure, includes/workspace/flow_draft.php): action draft, data an object, nodes/edges/notes lists, template a string and never beside boxes, answers an object.

## What is checked?
- `ws_flow_draft_proposal_row()` for the person (`ws_ai_viewer()`): `ws_can_schedule()`, a template filled for them (`ws_api_flow_from_template()`), then `ws_flow_save_check()`, the check of `POST /workspace/flows` (flow limit, name, home readable). Any refusal is 422 on `changes[i]`, dry run included. Problems that stop publishing are not refused: the card names up to three.
- Home: channel_id sent, else the channel it is proposed in when public or private and read, else none.

## How is it kept and opened?
- A `ws_ai_changes` row, record_type `flow` (VARCHAR(20), no schema change), action draft, record_id 0; snapshot `{sentence '', data {name, channel_id, graph}, notes, channel_id}`.
- Card: `ws_flow_draft_change_entry()`; only the person it waits for sees the start (`ws_flow_start_text()`), up to 12 boxes and the problems. `can_apply` false; `ws_change_apply_check()` refuses type flow.
- Panel action `ws_flow_from_draft` calls `ws_flow_draft_open()`: a flow row here, a scheduled row through `ws_scheduled_draft_flow_open()`. Both write with `ws_flow_draft_opened()`: `ws_flow_save()` as a new draft flow of the person (home dropped when no longer readable), row applied with `flow_id`. The workspace.js card needed no change (it reads `draft` and `flow_id`).
- Publishing stays the person's own session (#1708 rule).

## What happens to {type: scheduled}?
- Still taken, unchanged, for clients that send it (`workspace-scheduled-draft-proposals.md`); the schema text names flow first. Existing scheduled cards keep opening as flows.

## API
- `GET /workspace/changes?type=flow` lists them; record.type flow, fields the card's lines.

## Tests
- tests/workspace_flow_draft_test.php.
