---
name: workspace-flows
description: Plan (2026-10-24, F1 #1503, F2 #1529, F3a #1551, F3b #1555, F7 built): one Flows system: process templates, "when X do Y" rules, an n8n-like canvas; canvas first, one engine
---

## What is asked? (owner, 2026-10-24)
- One system, three uses, the third the main one: 1) process template: rows (step, person, days, "after 1") become ordered tasks on Run, a row Done with its task, rerun per customer; 2) rules on the scheduled-actions engine: Status becomes Approved → post, notify, mail, task; a form adds a row; 3) a flow drawn on the Workspace canvas: trigger → condition → step; tables are boxes.
- Standing: personal allowed, no forced link, CRM ready, AI proposes.

## What is there today?
- Scheduled actions (includes/workspace/scheduled.php): rules, one `ws_scheduled_do()` step, follow-ups, fixed-delay chains; nothing waits for a person.
- Channel templates (`ws_template_apply()`): tasks with day offsets, assign rules, `depends_on` → `ws_task_link_add()` (not enforced), all made at once; builtin:new_customer is nearly use 1.

## What is proposed? (not approved yet)
- One "Flow" ("Akış"): graph JSON (boxes, arrows, notes), draft, versions; engine: plans/workspace-flows-engine.md.
- Canvas (main): workspace_flows.php, bundle `workspace_flow.min.js`; HTML boxes, SVG arrows, keyboard "Connect to…", phone and screen-reader outline; run view polled (`ws_flow_run_get`, backoff).
- Process template: task boxes on "after" arrows; also from a channel template or table rows (workflow mode).
- Boxes: triggers (Run on a record; time, event, form, join via the anchor; row later), condition, task (waits for done), approval, delay, the `ws_scheduled_do()` steps; later add/set row, start a flow, MCP tool, assistant.
- Rights: staff build (`ws_can_schedule()`), owner-only like notes (`ws_note_access()`); an optional home channel (public or private) lets its readers Run it and holds the runs started there (a run keeps its channel); table boxes show only to their readers; runs use the owner's rights at run time; nothing reaches site members.
- CRM: pipeline = shared workflow table + flows ("Won → start New customer setup"); plans/workspace-crm-seams.md step 6 is a shared step.
- From plans/workspace-canvas-workflow-tables.md T3 (canvas = flow), T4, T5 came here; T1, T2, T6, S1, S2 stay.

## Slices (one PR each; phase 1 = F1-F3, a process drawn and run)
1. Done (PR #1503): F1 store: four tables (11.0.80), `flow_id`, graph check, API (workspace/workspace-flows-store.md).
2. PR #1529: F2 canvas: draw, save, publish; manual, task, end boxes, "after" arrows. What is left: workspace/workspace-flows-canvas.md.
3. F3 runner: Run, tasks wait for done, run view, cancel. F3a engine, API: PR #1551 (workspace/workspace-flows-runner.md); F3b run view: PR #1555.
4. F4 rules: anchor triggers, condition, steps.php boxes, `{{node.*}}`, dry-run a box.
5. F5 approval, delay, wait with timeout, retry or skip.
6. F6 after T1: row events, add/set row, workflow mode, Run from a table.
7. F7 shares built (11.0.83; workspace/workspace-flows-shares.md). F8 MCP tool, assistant, AI draft cards, starters.

## Which questions are answered?
Owner 2026-10-24: 1 a, 2 b (role-3 members only personal flows with steps in their own reach), 3 a (a run an AI app starts asks first, per the MCP plan), 4 a; name "Akışlar". 5-8: recommendations taken (owner: decide without asking).
1. Editor: own vanilla (not JointJS, not Drawflow).
2. Builders: staff; home channel readers Run; role-3 personal flows from F6.
3. Record writes: publish decides, like `change`.
4. Whiteboard: one canvas; boxes without a trigger are a board.
5. MCP connector tools: reads inline, writes propose and wait, F8.
6. AI: drafts only (a draft card as scheduled_draft.php), a person publishes.
7. Process runs: a task per box once ready, due from then, Done per run.
8. Rules: the scheduled-actions form stays, shared steps, "Open on canvas".

## Where to start?
- F4 next (plans/workspace-flows-engine.md). Read workspace/README.md, plans/workspace-growth-terms.md.
