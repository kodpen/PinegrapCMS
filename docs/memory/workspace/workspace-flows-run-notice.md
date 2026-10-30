---
name: workspace-flows-run-notice
description: A flow run's inbox line opens that run (?flow=&run=), a failed run's own title and icon, box titles with values named, the live-flow hint, the cancel sentence by box kind and the cancel dialog (#3514)
---

## What does the flow_finished line open? (#3514, #3371)
- `workspace_flows.php?flow=<id>&run=<id>`: the canvas opens the flow, then the run with the runs list behind it (`start()` → `openLastRun()`, the address a run's channel card already used). It used to open only the flow list.
- One line per run (`ws_notify()` `separate`, inbox ids 0), its sentence in `ws_scheduled_notices`. The run comes from the notice: `ws_flow_run_tell_owner()` writes the run id into `ws_scheduled_notices.action_id` (0 before; nothing reads a flow notice's action) and `ws_inbox_flow_run()` joins `ws_flow_runs` on it. A line written before, or a run swept or binned with its flow, opens the flow list (`ws_inbox_flow_run_line()`). No schema change.
- A failed run: "A run of your flow failed", icon `bi-exclamation-octagon`; the title follows the run's status when the line is read.
- Decided #3526 (`workflow/decisions-made-workspace-4.md`): step 1, one line per chain of runs (the root run's unread line updated, failure turns it), in progress; step 2, a per-flow "Tell me" choice on the start box, default every run, not built.

## How are box titles with values shown? (#3373 item 4)
- `rawTitle()` is the title as written (the settings field); `titleOf()` (canvas, outline, Connect to lists, run side list, via `nameOf()`) names each `{{node.…}}` / `{{trigger.…}}` as the value picker does, in brackets: `[box › value]` (`namedTitle()`). A value no box gives: the box's kind name for the whole title. A source box whose own title holds values is named by its kind (no recursion).

## What does the side panel say on a live flow? (#3373 item 3)
- `flowHelp()`: live with a published version → `fl_flow_help_live` (changes stay in the draft until published); else `fl_flow_help` ("Nothing runs until the flow is published").

## What does a cancelled run write on its boxes? (#3373 item 5)
- `ws_flow_cancel_result($effect, $name)`: `task:<id>` → its task stays as it is; `approval:<id>` → its approval request stays open (`workspace-flows-approval-box.md`); anything else (a delay, a box not carried out) → only "Cancelled with the run by …". It used to give the task sentence to every box with an effect (`effect <> ''`), waits and approvals too.
- The dialog (`askCancel()`) says approval requests stay open; its way out reads "Keep running" (Vazgeç), relabelled after `K.ask()` fills the shared dialog, so workspace.js is untouched.
- Tests `tests/workspace_flow_run_notice_test.php`, `workspace_flows_canvas_test.php` (`…_address_opens_a_run`, `…_live_hint_and_cancel_dialog`, `…_titles_name_their_values`).
