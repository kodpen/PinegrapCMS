---
name: workspace-flows
description: Plan (2026-10-24): one Flows system: process templates, "when X do Y" rules, an n8n-like canvas; canvas first, one engine
---

## What is asked? (owner, 2026-10-24)
- One system, three uses, the third the main one: 1) process template: rows (step, person, days, "after 1") become ordered tasks on Run, a row Done with its task, rerun per customer; 2) rules on the scheduled-actions engine: Status becomes Approved → post, notify, mail, task; a form adds a row; 3) a flow drawn on the Workspace canvas: trigger → condition → step; tables are boxes.
- Standing: personal allowed, no forced link, CRM, AI proposes.

## What is there today?
- Scheduled actions: rules, one `ws_scheduled_do()` step, follow-ups, fixed-delay chains; nothing waits for a person.
- Channel templates (`ws_template_apply()`): tasks with day offsets, assign rules, `depends_on` (not enforced), all at once; builtin:new_customer is nearly use 1.

## What is proposed? (not approved yet)
- One "Flow" ("Akış"): graph JSON (boxes, arrows, notes), draft, versions; engine: plans/workspace-flows-engine.md.
- Canvas (main): workspace_flows.php, bundle `workspace_flow.min.js`; HTML boxes, SVG arrows, keyboard "Connect to…", phone and screen-reader outline; run view polled (`ws_flow_run_get`, backoff).
- Process template: task boxes on "after" arrows; from a channel template or table rows.
- Boxes: triggers (Run on a record; time, event, form, join via the anchor; row later), condition, task (waits for done), approval, delay, the `ws_scheduled_do()` steps; later add/set row, start a flow, MCP tool, assistant.
- Rights: staff build (`ws_can_schedule()`), owner-only (`ws_note_access()`); an optional home channel (public or private) lets its readers Run it and holds runs started there (each keeps its channel); table boxes show only to their readers; runs use the owner's rights at run time; nothing reaches site members.
- CRM: pipeline = shared workflow table + flows ("Won → start New customer setup"); plans/workspace-crm-seams.md step 6 is shared.
- Tables T3-T5 came here (workspace/workflow-tables-later.md).

## Slices (one PR each; phase 1 = F1-F3, a process drawn and run)
1. Done #1503: F1 store: four tables (11.0.80), graph check, API (workspace/workspace-flows-store.md).
2. Done #1529: F2 canvas: draw, save, publish (workspace/workspace-flows-canvas.md).
3. Done: F3 runner #1551 (workspace/workspace-flows-runner.md), run view #1555.
4. Done: F4 rules: anchors, condition, steps.php boxes, `{{node.*}}`, box try.
5. Done: F5 approval, delay, timeout, retry or skip.
6. F6 (tables): F6a done #3064 row start box (workspace/workspace-flows-row-box.md); F6b add/set row boxes: F6b-1 server done #3189 (workspace/workspace-flows-table-boxes.md), F6b-2 canvas form; F6c workflow mode (#3122): F6c-a done #3145, F6c-b #3301 (workspace/workspace-flows-from-table.md).
7. F7 shares #1578 (11.0.83; workspace/workspace-flows-shares.md).
8. F8 MCP box (answer 5): F8a read tool #3000; F8b canvas form #3028; F8c writes propose and wait: F8c-1 server done #3144, F8c-2 canvas form.

## Which questions are answered?
Owner 2026-10-24: 1 a, 2 b, 3 a, 4 a; name "Akışlar". 5-7: recommendations taken (owner: decide without asking); 8 below.
1. Editor: own vanilla (not JointJS, not Drawflow).
2. Builders: staff build and share; home channel readers Run; role-3 members only personal flows with steps in their reach, from F6.
3. Record writes: publish decides, like `change`; an AI app's run asks first (MCP plan).
4. Whiteboard: one canvas; boxes without a trigger are a board.
5. MCP connector tools: reads inline, writes propose and wait, F8.
6. AI: drafts only (a draft card as scheduled_draft.php), a person publishes.
7. Process runs: a task per box once ready, due from then, Done per run.
8. Scheduled actions in Flows, no loss: workspace/workspace-flows-scheduled-merge.md.

## Where to start?
- F8c-2 (workspace/workspace-flows-mcp-box-writes.md), F6b-2: canvas forms, after plans/workspace-flows-look.md. Values: plans/workspace-flows-data-passing.md.
