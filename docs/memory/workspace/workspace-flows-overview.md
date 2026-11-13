---
name: workspace-flows-overview
description: Workspace Flows ("Akışlar") as a whole: what it is, the decisions that bind it, every box kind by its label, what drives a run and how late it can be, which of the workspace-flow* files holds what
---

## What is it?
- Workflows drawn on the Workspace canvas: a start box, step boxes on arrows, value boxes on data threads. A draft and immutable versions; a run keeps its version. Scheduled actions moved into it (`workspace-flows-scheduled-merge.md`).

## Which decisions bind it?
- `workspace-flows-decisions.md` (owner answers 1-8, rights, data passing); new boxes general, never for one example (`workflow/decisions-made-product-33.md`, `workspace-flows-adding.md`); loops without back arrows or flow variables (`-product-30.md`); scheduled work only on the canvas (`workspace-scheduling-one-place.md`); role-3 members' own flows (`-workspace-3.md`). A box acts with the owner's rights as they are when it runs.

## Which boxes are there? (`ws_flow_kinds()`; label, then kind)
- Start, one per flow: Started by hand `manual`, At a time `schedule`, When something happens `event`, When somebody joins a channel `join`, When a table row changes `row`, When a customer stops ordering `no_order`, When the flow is mentioned in a channel `mention`, When a web address is called `web`.
- Messages: Post a message `post`, Send an e-mail `email`, Let people know `notify`, Send a summary of counts `report`.
- People: Task `task` (waits for it), Open a task without waiting `task_open`, E-mail people their tasks `task_digest`, Ask for a check-in `checkin`, Bring a customer into a channel `lead`, Ask for approval `approval`.
- Logic: Condition `condition`, Wait `delay` (Turkish "Bekle"; for a time or until a date, 90 days at most), For each `for_each` with End for each `for_each_end` (also Repeat N times), End `end`.
- System: Call a webhook `webhook`, Check a web address `web_check`, Start a flow `start_flow`, Change a record `change`, Run a tool of an MCP server `mcp`, Add / Change / Read a row of a table `row_add` / `row_set` / `row_get`, Read rows of a table `rows_get`, Read a record `record_get`, Ask Pinegrap AI `ai`, Ask an agent `agent`.
- Values, no arrows: Constants `constants`, Derived value `value`.
- Kept but never offered: Start a scheduled action `start_action` (`ws_flow_kinds_not_offered()`).

## What drives a run, and how late can it be?
- Nothing runs by itself. `ws_scheduled_run()` calls `ws_flow_advance()` (10 s budget on a screen, 25 s in a job, 3 boxes a run a round): from the open workspace's `ws_tick` (when `ws_sync` reports `scheduled_due`), from `job.php` on every general job tick and from `workspace_recurring_job.php`, Workspace on or off (`automation-flows-gate.md`). A start by hand or by the API advances its run at once.
- So a due box (a Wait's `wake_at`, a ready box) is late by at most the time until the next job tick or an open workspace screen asks: a minute with the job every minute. A task box is looked at hourly besides its event (`WS_FLOW_RECHECK`); events wake waiting boxes in the same call (`ws_events_process()`).

## Where is each part?
- Store, graph check, problems: `workspace-flows-store.md`; canvas, palette, look: `-canvas.md`, `-palette.md`, `-box-look.md`, `-visual-spec.md`; runner: `-runner.md`, `-runner-rounds.md`, retry and skip `-retry-skip.md`.
- Boxes: `workspace-flows-<name>-box.md` (delay, approval, condition, change, chain, derived, row-get, rows-get, record-get, ai, mcp), `-table-boxes.md`, `-for-each.md`, the rest `-steps.md`; starts: `-anchors.md`, `-web-start.md`, `-mention-start.md`, `-*-start-events.md`.
- Values between boxes: `-outputs.md`, `-trigger-values.md`, `-references.md`, `-value-picker.md`, `-thread-*.md`. Runs: `-run-view.md`, `-run-notice.md`, `-run-events.md`.
- Pinegrap AI drafts: `workspace-flow-ai-drafts.md`; API: `-api-*.md`, `-catalogue.md`; shares and members: `-shares.md`, `-member.md`.

## How is a part added?
- A box or a start event: `workspace-flows-adding.md`; a template: `workspace-flows-template-adding.md`.
