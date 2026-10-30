---
name: workspace-flows-data-passing
description: Plan (owner request 2026-10-27, draft being refined): values carried from one Flows box to the next; output schemas, a value picker, reference checks, trigger values, escaping, then AI drafts
---

## What is there today?
- `{{node.<id>.<field>}}` (`ws_step_fill_nodes()`, steps.php) reads `text`, `outcome`, `status` and the scalar `result.data` values of the run's settled boxes (`ws_step_node_values()`); arrays read as ''.
- Filled only into a kind's `texts` (`ws_step_kinds()`): post body, e-mail subject and body, notify text, task title and description, webhook text, check-in question, report title, digest subject and intro. Addresses are never filled.
- Ids are `<kind>_<n>` (`freeId()` in workspace_flow.js) and are not shown on the canvas; "Add value" puts them in as chips (D6, workspace/workspace-flows-value-picker.md).
- A reference to a missing box or field is a problem, one to a box that may not run a warning (D4, workspace/workspace-flows-references.md); at run time it still becomes ''.
- `{{trigger.<field>}}` reads what started the run (D5, workspace/workspace-flows-trigger-values.md). A condition compares a box's or the trigger's value (D7). A loop's body runs at `pass` = item (D9).
- A filled value is never read again as a placeholder (D2) and is escaped for where it lands (D3), workspace/workspace-flows-outputs.md.

## Which steps? (one PR each, in this order)
- Done: D1 outputs (#2111), D2 one-pass fill (#2121), D3 escaping (#2293): workspace/workspace-flows-outputs.md; D4 reference check (#2305): workspace-flows-references.md; D5 trigger values (#2341): workspace-flows-trigger-values.md; D6 value picker (#2461): workspace-flows-value-picker.md (threads, fork, value boxes: plans/workspace-flows-data-threads.md); D7 conditions on values (#2496): workspace-flows-condition-values.md.

- D8 AI drafts: F8 of workspace-flows.md, after D1 and D4 (rules below), in three slices:
  - D8a server (done, #2605, workspace/workspace-flow-ai-drafts.md): `propose_flow` and `read_flow` tools, the A8 draft card (flow_draft.php).
  - D8b card: the draft card as a mini flow window (boxes and threads drawn small, outbound boxes marked, F4e try per box); a panel action giving the card's graph. (done, #3032, workspace/workspace-flow-ai-drafts.md)
  - D8c canvas (done, #2725, workspace/workspace-flow-ai-drafts.md): patch boxes marked until publish; `?flow=<id>&box=<id>` for D8b.

- D9a webhook answer as a body output (#2414-2, done #2985, workspace/workspace-flows-webhook-body.md): `body` (16 KB, UTF-8) of the webhook kind, kept on the run row without a schema change, held back from readers who may not change the flow. Files: includes/workspace/scheduled.php, steps.php, flow_runs.php, api_flows.php, flow_run_events.php.
- D9 For each (#3312): server half done #3323 (workspace/workspace-flows-for-each.md); canvas half done #3422.

## What rules does D8 keep?
- The model gets the kinds and their outputs (`ws_flow_js_config()`) as its tool schema: no invented kind, field or reference.
- Draft only; a person publishes (answer 6 of workspace-flows.md). `ws_flow_graph_check()` runs before saving.
- Box positions come from an automatic layout, never from the model.
- The draft card is a mini flow window: boxes and threads drawn small, the F4e dry run on each box, outbound boxes (webhook, e-mail) marked; a box is edited there or on the canvas.
- On a live flow the model sends a patch (add, connect, remove, set), highlighted on the canvas, never a whole graph.

## What was decided? (#2414)
1. A webhook address takes digit-only values in its path or query, URL-encoded; host and scheme stay fixed.
2. The webhook answer is kept whole, up to 16 KB, on the run row.
3. The picker offers a branch that may be skipped, marked "Empty if skipped" (done in D6).
4. Role-3 personal flows read only records in the person's reach.
5. Lists (D9) start with webhook arrays.

## Where to start?
- D8 (AI drafts), after F8 of workspace-flows.md; its rules are above.
