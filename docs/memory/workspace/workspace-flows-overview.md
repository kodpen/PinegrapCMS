---
name: workspace-flows-overview
description: Workspace Flows ("Akışlar") as a whole: what it is, the decisions that bind it, every box kind by its label, the editor's parts by their label, what drives a run, where each part is
---

## What is it?
- Workflows drawn on the Workspace canvas: a start box, step boxes on arrows, value boxes on data threads; a draft and immutable versions, a run keeps its version. Scheduled actions moved in (`-scheduled-merge.md`).

## Which decisions bind it?
- `workspace-flows-decisions.md` (owner answers 1-8, rights, data passing); the editor of 2026-11-22 (`-editor-decisions.md`); new boxes general (`workflow/decisions-made-product-33.md`, `-adding.md`); no back arrows or flow variables (`-product-30.md`); scheduled work only on the canvas (`workspace-scheduling-one-place.md`); role-3 members' own flows (`-workspace-3.md`).

## Which boxes are there? (`ws_flow_kinds()`; label, then kind)
- Start, one per flow: Started by hand `manual`, At a time `schedule`, When something happens `event`, … joins a channel `join`, … a table row changes `row`, … a customer stops ordering `no_order`, … the flow is mentioned `mention`, … a web address is called `web`.
- Messages: Post a message `post`, Send an e-mail `email`, Send an SMS `sms`, Send a WhatsApp message `whatsapp`, Let people know `notify`, Send a summary of counts `report`.
- People: Task `task` (waits for it), Open a task without waiting `task_open`, E-mail people their tasks `task_digest`, Ask for a check-in `checkin`, Bring a customer into a channel `lead`, Ask for approval `approval`.
- Logic: Condition `condition`, Split `split` (arms by share), Wait `delay` (90 days at most), For each `for_each` with End for each `for_each_end` (also Repeat N times), End `end`.
- System: Call a webhook `webhook`, Check a web address `web_check`, Start a flow `start_flow`, Change a record `change`, Run a tool of an MCP server `mcp`, Add / Change / Read a row of a table `row_add` / `row_set` / `row_get`, Read rows of a table `rows_get`, Read a record `record_get`, Read the records of a list `list_records`, Add to or take out of a contact group `contact_group`, Ask Pinegrap AI `ai`, Ask an agent `agent`.
- Values, no arrows: Constants `constants`, Derived value `value`.
- Never offered: Start a scheduled action `start_action` (`ws_flow_kinds_not_offered()`).

## What is on the editor? (`workspace-flows-canvas.md`)
- Bar: the name's window with Leaving the flow, Problems, Counts, Flow runs, Try, Run, Publish (`-window-bar.md`, `-name-window.md`, `-leave.md`). Over the canvas: add rail and list, bottom strip (`-canvas-tools.md`, `-palette.md`, `-tidy-map.md`), "+" on an arrow (`-add-here.md`), the chosen box's card (`-side-panel.md`, `-panel-fields.md`).
- `-several-boxes.md`, `-notes-frames.md`; Try: `-trial*.md`; Counts: `-counts*.md`; Sending hours: `-send-hours.md`.

## What drives a run, and how late can it be?
- Nothing runs alone: `ws_scheduled_run()` calls `ws_flow_advance()` from `ws_tick`, `job.php` and `workspace_recurring_job.php` (`automation-flows-gate.md`, `-runner-rounds.md`); a start by hand or by the API advances at once. A due Wait is late at most until the next job tick or open screen; a task box is looked at hourly too (`WS_FLOW_RECHECK`); events wake waiting boxes and end leaving runs (`ws_events_process()`).

## Where is each part?
- Store, check, problems: `workspace-flows-store.md`; look: `-box-look.md`, `-visual-spec.md`; runner: `-runner.md`, `-runner-rounds.md`, retry and skip `-retry-skip.md`.
- Boxes: `workspace-flows-<name>-box.md`, `-table-boxes.md`, `-for-each.md`, `-steps.md`; starts: `-anchors.md`, `-web-start.md`, `-mention-start.md`, `-*-start-events.md`.
- Values: `-outputs.md`, `-trigger-values.md`, `-references.md`, `-value-picker.md`, `-thread-*.md`. Runs: `-run-view.md`, `-run-notice.md`, `-run-events.md`.
- AI drafts: `workspace-flow-ai-drafts.md`; API: `-api-*.md`, `-catalogue.md`; shares: `-shares.md`, `-member.md`.
- Adding a box or start event: `-adding.md`; a template: `-template-adding.md`.
