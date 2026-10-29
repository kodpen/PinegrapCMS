---
name: workspace-flow-ai-start
description: New flow from a sentence (G1b): ws_flow_ai_sketch() asks Pinegrap AI once for a flow in propose_flow's shape, one retry with the problems, saved as the person's draft flow, never published
---

## What is it?
- The "describe it to AI" start of New flow (owner request: empty canvas, describe it, from a template). Server half in G1b: `ws_flow_ai_sketch($viewer, $sentence, $channel_id = 0, $ask = null)` in includes/workspace/flow_draft.php. No caller yet: the panel route (a `ws_handle_action` case and its `pg_panel_actions()` row), the `ws_flow_js_config()` flag and the AI card in `showGallery()` come in the client half.

## Who may use it?
- `ws_flow_ai_sketch_ready()`: `ws_can_schedule()`, `ws_flows_ready()` and `pg_ai_state()` ready (the module on, settings complete, no hold; the Premium licence through `ws_ai_missing()`). Refusals: not staff, an empty sentence, AI not ready (asked nothing then).

## How is the model asked?
- `pg_ai_chat()` sends no tools, so the prompt (`ws_flow_ai_sketch_messages()`, pure) asks for one JSON object whose schema is propose_flow's (`ws_flow_ai_tool()`) without `flow_id` and `changes`, with `ws_flow_ai_catalogue_text()`; the sentence (one line, at most `WS_FLOW_AI_SKETCH_MAX` = 600 characters) is fenced as data; texts in `ws_ai_request_language()`.
- `ws_flow_ai_sketch_parse()` takes the first object with `boxes` (code block or words around it allowed), as the retired scheduled-draft parser did.

## What is checked?
- `ws_flow_ai_sketch_read()`: no `flow_id`/`changes`, `ws_flow_ai_graph()` (no invented kind or setting, automatic layout), name or the sentence's first 60 characters, reason, `ws_flow_ai_problem_texts()`.
- `ws_flow_ai_sketch_draft()`: an unreadable answer, a refused graph or problems go back once (the answer as assistant, what is wrong as user); at most two calls. A second flow still with problems is kept and its problems answered; unreadable twice is refused.

## What is saved?
- `ws_flow_save()` as a new flow of the person (status draft, home `$channel_id` when given). Never published: publishing stays the person's (answer 6 of plans/workspace-flows.md). Answer: ok, error, flow_id, problems, notes (the reason).

## Tests
- tests/workspace_flow_draft_test.php: prompt, parse, read, one call, the second try, refusals; canned answers through `$ask`, problems from `ws_flow_graph_check()` (`ws_flow_problems()` reaches the database).
