---
name: workspace-flow-ai-drafts
description: Pinegrap AI drafts a flow (propose_flow, read_flow; D8a #2605): catalogue schema, graph or patch, automatic layout, the A8 card, a patch opened onto its flow and marked on the canvas (D8c)
---

## When is the tool offered?
- `ws_flow_ai_offered()` (includes/workspace/flow_draft.php): a request in a channel (no note, task or MCP alias), proposals table and flows ready, the asker `ws_can_schedule()`. Then `ws_ai_tools()` adds `propose_flow` (`ws_flow_ai_tool(ws_flow_kind_catalogue())`) and `read_flow`; the prompt gets one line on when to use it.

## What is its schema?
- Built from the catalogue (pure): `kind` an enum of the kinds, `on` an enum of every outcome plus `always`, the description one line per kind (`ws_flow_ai_catalogue_text()`: starts, outcomes, values, filled texts, settings with type or words). No invented kind or setting: `ws_flow_ai_node()` refuses a kind or a settings key the catalogue does not list.
- New flow: name, boxes (id, kind, label, settings), arrows (from, to, on). Existing flow: flow_id and changes, never the whole graph (refused).

## How is a patch made?
- `ws_flow_patch_apply($graph, $ops, $keys)` (pure): add, connect, remove (a box with its arrows, or an arrow), set (label, settings merged); at most 40; errors say `changes[i]`. Returns the ops as read (a removed box keeps its name for the card) and `touched`; the same ops made again on the same graph give the same graph.

## Where do boxes go?
- `ws_flow_auto_layout($graph, $placed)` (pure): never the model's places. Columns by the longest way in, 288 apart, rows 128 apart from 96; an added box a column after the box it follows, one row lower while the spot is taken; placed boxes stay.

## What is checked and kept?
- `ws_flow_ai_propose()`: `ws_flow_problems()` texts go back to the model once (`flow_tries`); a second time the draft is kept and the card names them. New flow: `ws_flow_draft_proposal_row()` (the A8 check). Patch: `ws_flow_for(…, 'edit')`, not binned, `ws_flow_save_check()` with the flow; snapshot adds `base` (flow_id, draft_rev), `patch` (ops), `touched`. At most 2 per answer, in `$state['proposals']['flows']`, stored by `ws_ai_deliver()` with `ws_changes_store()`. Drafting saves and publishes nothing.
- Answer words: an answer with only drafts gets "I drafted the flow below. Nothing runs until you open it and publish it."

## How does the card open?
- Same card (`ws_flow_draft_change_entry()`); a patch lists its changes (`ws_flow_patch_lines()`: Adds, Connects, Removes, Changes) instead of boxes.
- `ws_flow_draft_open()`: a snapshot with `base` goes to `ws_flow_draft_patch_open()`: edit access now, the ops made again on the flow's draft as it is now (refused if they no longer fit), `ws_flow_save()` with that `draft_rev`, row applied with `flow_id` (`ws_flow_draft_mark_opened()`). Publishing stays the person's.

## How does the canvas show a patch? (D8c)
- `ws_flow_detail()` carries `ai_marks` to an editor: `ws_flow_ai_marks()` reads the person's own applied flow cards (`decided_by`, newest first, at most 20) opened after the live version's `published_at` (and within `WS_FLOW_PATCH_MARK_DAYS`), takes the first whose `base.flow_id` is this flow, and `ws_flow_patch_marks()` (pure) turns its ops into `{id, mark}`: `added`, or `changed` for a `set`; a removed box or one gone from the draft is left out. Publishing ends the marks.
- workspace_flow.js: a dashed ring and a New / Changed badge on the box and its outline item (`paintMark()`), a strip over the canvas listing the boxes (each selects and reveals its box) with "Hide marks" (kept per card in localStorage `pg_wsf_marks_hidden`). None while a run is shown.
- `workspace_flows.php?flow=<id>&box=<id>` opens the flow with that box selected and in sight: the link a card's per-box edit uses (D8b).

## What is left?
- D8b the card as a mini flow window (plans/workspace-flows-data-passing.md).

## Tests
- tests/workspace_flow_draft_test.php (schema, refusals, layout, patch, wiring).
