---
name: workspace-flow-ai-start
description: New flow from a sentence (G1b #3119, #3266): ws_flow_ai_sketch() asks Pinegrap AI for a flow in propose_flow's shape, one retry, saved as a draft; the panel route and the gallery's AI card
---

## What is it?
- The "describe it to AI" start of New flow (owner request: empty canvas, describe it, from a template). Server: `ws_flow_ai_sketch($viewer, $sentence, $channel_id = 0, $ask = null)` in includes/workspace/flow_draft.php (#3119); panel route and card (#3266).

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

## How does the panel reach it? (#3266)
- Action `ws_flow_ai_sketch` (`sentence`; a `ws_` row of `pg_panel_actions()`: exempt, token, write) → `ws_action_flow_ai_sketch()` in includes/workspace/actions.php: no home channel from New flow; answer `ws_action_flow_ai_sketch_answer()` (pure): `flow` (`ws_flow_detail()`), `problems`, `notes`; a refusal is an error on field `sentence` in words.
- `ws_flow_js_config()` (mode flows) gives `ai_sketch` (builders and `ws_flow_ai_sketch_ready()`) and `ai_sketch_max`. "New flow" opens the gallery when there are templates or `ai_sketch`.
- `sketchCard()` in workspace_flow.js, beside Empty canvas: a one-line field (`maxLength` the limit), "Draft the flow"; while asked the button is disabled, the field read-only and a status line says it works; a refusal or AI not ready is said in that line (`aria-invalid`), the field keeps the focus.
- Drafted: `show(data.flow)`, the problem list opened when there are problems or warnings, and a strip (`drawSketch()`, `state.sketch`) under the AI marks: "check every box before you publish" and the notes; Hide or another flow clears it. Publishing stays the canvas's Publish.

## Tests
- tests/workspace_flow_draft_test.php: prompt, parse, read, one call, the second try, refusals; canned answers through `$ask`, problems from `ws_flow_graph_check()` (`ws_flow_problems()` reaches the database); the action's refusals, answer shape and wiring (#3266).
