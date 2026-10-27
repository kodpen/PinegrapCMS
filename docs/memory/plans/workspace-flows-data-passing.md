---
name: workspace-flows-data-passing
description: Plan (owner request 2026-10-27, draft being refined): values carried from one Flows box to the next; output schemas, a value picker, reference checks, trigger values, escaping, then AI drafts
---

## What is there today?
- `{{node.<id>.<field>}}` (`ws_step_fill_nodes()`, steps.php) reads `text`, `outcome`, `status` and the scalar `result.data` values of the run's settled boxes (`ws_step_node_values()`); arrays read as ''.
- Filled only into a kind's `texts` (`ws_step_kinds()`): post body, e-mail subject and body, notify text, task title and description, webhook text, check-in question, report title, digest subject and intro. Addresses are never filled.
- Ids are `<kind>_<n>` (`freeId()` in workspace_flow.js) and are not shown on the canvas; `fl_values_help` is the only hint.
- A reference to a missing box or field is a problem, one to a box that may not run a warning (D4, workspace/workspace-flows-references.md); at run time it still becomes ''.
- `{{trigger.<field>}}` reads what started the run (D5, workspace/workspace-flows-trigger-values.md). A condition cannot compare a box's value. No loops (`pass` is always 0).
- A filled value is never read again as a placeholder (D2) and is escaped for where it lands (D3), workspace/workspace-flows-outputs.md.

## Which steps? (one PR each, in this order)
- D1 outputs: done (#2111), see workspace/workspace-flows-outputs.md.

- D2 one-pass fill: done (#2121), see workspace/workspace-flows-outputs.md.

- D3 escaping: done (#2293), see workspace/workspace-flows-outputs.md.

- D4 reference check: done (#2305), see workspace/workspace-flows-references.md.

- D5 trigger values: done (#2341), see workspace/workspace-flows-trigger-values.md.

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

## Which questions are answered? (#2414)
1. Webhook address: digit-only fields may go into the path or query, URL-encoded; host and scheme stay the builder's.
2. Webhook answer: the whole JSON, up to 16 KB, on the run row.
3. Picker: values of a branch that may be skipped are offered, marked "empty if skipped".
4. Role-3 personal flows (F6) read only records in the person's reach.
5. Lists (D9): webhook arrays first; a day's orders need a records-list box; table rows once T1 exists.

## Where to start?
- D6. Read workspace/workspace-flows-outputs.md and workspace-flows-trigger-values.md (`ws_flow_js_config()` `outputs`, `trigger_outputs`).
