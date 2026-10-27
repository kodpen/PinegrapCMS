---
name: workspace-flows-data-passing
description: Plan (owner request 2026-10-27, draft being refined): values carried from one Flows box to the next; output schemas, a value picker, reference checks, trigger values, escaping, then AI drafts
---

## What is there today?
- `{{node.<id>.<field>}}` (`ws_step_fill_nodes()`, steps.php) reads `text`, `outcome`, `status` and the scalar `result.data` values of the run's settled boxes (`ws_step_node_values()`); arrays read as ''.
- Filled only into a kind's `texts` (`ws_step_kinds()`): post body, e-mail subject and body, notify text, task title and description, webhook text, check-in question, report title, digest subject and intro. Addresses are never filled.
- Ids are `<kind>_<n>` (`freeId()` in workspace_flow.js) and are not shown on the canvas; `fl_values_help` is the only hint.
- A reference to a missing box or field becomes '' without a word: `ws_flow_graph_check()` does not read references.
- Not readable: trigger values (event payload, newcomer; workspace-flows-anchors.md). A condition cannot compare a box's value. No loops (`pass` is always 0).
- `ws_scheduled_fill()` runs after the node fill on the same text, so a filled value holding `{{customer}}` or `{{form_fields}}` is expanded again.

## Which steps? (one PR each, in this order)
- D1 outputs: done (#2111), see workspace/workspace-flows-outputs.md.

- D2 one-pass fill: node values and scheduled placeholders filled in one pass, or `{{` in an inserted value neutralised as `pg_ai_data_block()` does with `<<<`. Red test first.

- D3 escaping: a value is escaped for where it lands (message, e-mail HTML, webhook JSON; a line field strips control characters as today). First record what each destination does now. Addresses stay unfillable.

- D4 reference check: `ws_flow_graph_check()` gives `bad_reference` for an unknown box, a box not upstream of the reader, or a field not in its outputs; a box on a branch that may be skipped gives a warning. Shown under Problems.

- D5 trigger values: `{{trigger.<field>}}` from the run context, declared per anchor kind like outputs (event payload by event type, newcomer, record id).

- D6 value picker: "Add value" in the side panel lists the trigger and the upstream boxes with their outputs; inserts a chip "Check a web address › status code" keeping `{{node.web_check_1.status_code}}`. Ids are never typed; replaces `fl_values_help`. Threads, fork, value boxes: workspace-flows-data-threads.md.

- D7 conditions on values: a condition rule `value` compares an upstream output or trigger value, the input typed by the output's type.

- D8 AI drafts: F8 of workspace-flows.md, after D1 and D4 (rules below).

- D9 later: lists and a "For each" box (`pass` > 0).

## What rules does D8 keep?
- The model gets the kinds and their outputs (`ws_flow_js_config()`) as its tool schema: no invented kind, field or reference.
- Draft only; a person publishes (answer 6 of workspace-flows.md). `ws_flow_graph_check()` runs before saving.
- Box positions come from an automatic layout, never from the model.
- The draft card is a mini flow window: boxes and threads drawn small, the F4e dry run on each box, outbound boxes (webhook, e-mail) marked; a box is edited there or on the canvas.
- On a live flow the model sends a patch (add, connect, remove, set), highlighted on the canvas, never a whole graph.

## Open questions
1. May a value go into a webhook address's path or query (an order id)? Today never; digit-only fields could be allowed.
2. Webhook answer: keep the whole JSON (16 KB) or only fields the builder picks?
3. Picker: offer values of a branch that may be skipped (marked "empty if skipped") or hide them?
4. Role-3 personal flows (F6): the same values, or only records in their reach?
5. Lists (D9): first source: table rows, a day's orders, or webhook arrays?

## Where to start?
- D1, then D2. Read workspace/workspace-flows-steps.md, workspace-flows-anchors.md, workspace-flows-condition-box.md.
