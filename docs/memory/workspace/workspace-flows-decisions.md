---
name: workspace-flows-decisions
description: Lasting decisions behind Workspace Flows ("Akışlar"): the three uses, the owner's answers 1-8 (editor, builders, record writes, AI drafts only), rights, data-passing rules (#2414)
---

## What is Flows for? (owner, 2026-10-24)
- One system, three uses, the third the main one: 1) a process template: rows (step, person, days, "after 1") become ordered tasks on Run, rerun per customer; 2) "when X do Y" rules on the scheduled-actions engine; 3) a flow drawn on the Workspace canvas: trigger → condition → step, tables as boxes.
- One engine for all three: graph JSON (boxes, arrows, notes), draft, immutable versions; a run keeps the version it started on. Store, canvas and runner: `workspace-flows-store.md`, `workspace-flows-canvas.md`, `workspace-flows-runner.md`.
- CRM pipeline = a shared workflow table + flows (`workflow/decisions-made-product-11.md`, #3797).

## Which answers did the owner give?
1. Editor: Pinegrap's own vanilla canvas, not JointJS or Drawflow.
2. Builders: staff build and share (`ws_can_schedule()`); the readers of an optional home channel may Run it; role-3 members build only personal flows with boxes in their reach (`workflow/decisions-made-workspace-3.md`, `workspace-flows-member.md`).
3. Record writes: publishing decides, like the `change` kind; a run started by an AI application asks first.
4. Whiteboard: one canvas; boxes without a trigger are a board.
5. MCP connector boxes: reads inline, writes propose and wait (`workspace-flows-mcp-box.md`, `workspace-flows-mcp-box-writes.md`).
6. AI drafts only: a draft card, a person publishes (`workspace-flow-ai-drafts.md`).
7. Process runs: a task per box once it is ready, due from then, Done per run.
8. Scheduled actions move into Flows with no feature lost (`workspace-flows-scheduled-merge.md`).

## Which rights hold?
- Owner-only edits (`ws_note_access()`); a run uses the owner's rights at run time; table boxes show only to the table's readers; nothing reaches site members.
- Each run started in a channel keeps that channel.

## Which data-passing rules hold? (#2414)
- A filled value is never read again as a placeholder and is escaped for where it lands (`workspace-flows-outputs.md`).
- Addresses are never filled, but a webhook URL takes digit-only number values in its path or query, URL-encoded; host and scheme stay fixed (#3410, `workspace-flows-webhook-url.md`).
- The webhook answer is kept whole, up to 16 KB, on the run row (`workspace-flows-webhook-body.md`).
- The value picker offers a branch that may be skipped, marked "Empty if skipped" (`workspace-flows-value-picker.md`).
- Role-3 personal flows read only records in the person's reach; lists (For each) start with webhook arrays (`workspace-flows-for-each.md`).
- An AI draft gets the kinds and outputs as its tool schema, box places come from an automatic layout, and a live flow gets a patch, never a whole graph (`workspace-flow-ai-drafts.md`).

## Where were the steps?
- F1-F8, R1-R3 and D1-D9 are built, each named in its topic file above (F1 #1503, F2 #1529, F3 #1551, last D9 #3422). The plan files were deleted after the close-out.
