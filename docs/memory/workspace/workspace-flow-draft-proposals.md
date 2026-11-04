---
name: workspace-flow-draft-proposals
description: An application, or Claude in a channel answer, proposes a flow as a draft card ({type: flow}, A8): what is sent, checked, kept, how the card opens it, {type: scheduled}
---

## What does the application send?
- One more change in `workspace.changes.propose` (channel route only; the task twin answers 422): `{type: flow, action: draft, data, reason}`. `data` is the flow as `POST /workspace/flows` takes it: name, nodes, edges, notes, or template (+ answers), and channel_id (its home).
- Claude puts the same change in its answer in a channel: `ws_api_claude_answer()` calls `ws_changes_input(…, 0, false, true)` (`$drafts`); a task run still refuses it. The site's instructions (`ws_claude_routine_prompt()`) say to read `GET /workspace/flow-kinds` first, one start box, outcomes per kind, approval for a yes-or-no question, no x and y. Before (2026-11-03) Claude answered that no tool of its could build a flow and wrote the design out.
- Value boxes (#6447): Claude put a whole title into a join box's `a` and left `b` empty (unpublishable). The catalogue now names `value.rule` (join first), `value.op`, `value.format` (`ws_flow_setting_values()`); the instructions and Pinegrap AI's `value.b` hint (`ws_flow_ai_catalogue_hints()`) say the rules of `ws_flow_derived_two_inputs()` need both, join writing a, a space, then b.
- Refused while unpublishable (#6668): Claude's channel answer (`$drafts` of `ws_changes_input()`) with a flow `ws_flow_problems()` stops is a 422 from `ws_flow_draft_answer_refusal()`: the problems, `ws_flow_ai_problem_hints()`, "fix it and send again, or leave the flow out". Claude had put the flow's name as the start box's subject. Applications' proposals and Pinegrap AI's two tries unchanged.
- Shape first (`ws_flow_draft_proposal_input()`, pure, includes/workspace/flow_draft.php): action draft, data an object, nodes/edges/notes lists, template a string and never beside boxes, answers an object. Boxes all in one place (`ws_flow_draft_unplaced()`: none sent, or the same x and y, more than one box) are laid out with `ws_flow_auto_layout()`; boxes with places keep them.

## What is checked?
- `ws_flow_draft_proposal_row()` for the person (`ws_ai_viewer()`): `ws_can_schedule()`, a template filled for them (`ws_api_flow_from_template()`), then `ws_flow_save_check()`, the check of `POST /workspace/flows` (flow limit, name, home readable). Any refusal is 422 on `changes[i]`, dry run included. Problems that stop publishing are not refused: the card names up to three.
- Home: channel_id sent, else the channel it is proposed in when public or private and read, else none.

## How is it kept and opened?
- A `ws_ai_changes` row, record_type `flow` (VARCHAR(20), no schema change), action draft, record_id 0; snapshot `{sentence '', data {name, channel_id, graph}, notes, channel_id}`.
- Card: `ws_flow_draft_change_entry()`; only the person it waits for sees the start (`ws_flow_start_text()`), up to 12 boxes and the problems. `can_apply` false; `ws_change_apply_check()` refuses type flow.
- Panel action `ws_flow_from_draft` calls `ws_flow_draft_open()`: a flow row here, a scheduled row through `ws_scheduled_draft_flow_open()`. Both write with `ws_flow_draft_opened()`: `ws_flow_save()` as a new draft flow of the person (home dropped when no longer readable), row applied with `flow_id`. The workspace.js card needed no change (it reads `draft` and `flow_id`).
- Publishing stays the person's own session (#1708 rule).

## What happens to {type: scheduled}?
- Still taken for clients that send it, and kept as this same flow card since C5b (#2852): `workspace-scheduled-draft-proposals.md`. Older scheduled cards keep opening as flows.

## API
- `GET /workspace/changes?type=flow` lists them; record.type flow, fields the card's lines.

## Tests
- tests/workspace_flow_draft_test.php, tests/workspace_claude_flow_drafts_test.php.
