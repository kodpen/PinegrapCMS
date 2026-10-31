---
name: workspace-flows-run-flag
description: A flow run's flag (C1, #2903): state, duration, one-line result folded behind ▾; ws_flow_run_result_pick(), result on run reads, last_run in the flow list, runFlag() for C2
---

## What is the flag?
- `runFlag(run, options)` in `assets/js/workspace_flow_flag.js`: the state word with a mark (Completed for `done`, else Waiting, Running, Failed, Cancelled), the run's duration (so far for an open run), one line of its result. A result over 60 characters or of more than one line folds behind ▾; the open part holds the whole text and, with `options.open`, "Open the run". A running run with `can_cancel` gets Stop when `options.stop` is given.
- Colours: `.wsf-flag-*` in `backend.src.css`; the dark values are those of `plans/workspace-flows-visual-spec.md`, the light theme the same hues on a light ground.

## Where is it shown?
- Above the flow's last box on the canvas (the `end` box farthest right, else the rightmost non-value box), only while no run is open on the canvas; its link opens the run with the runs list behind it, Stop asks as "Cancel the run" does.
- In the flow list's Last run column (`workspace-flows-list.md`); its link opens the flow, then the run.
- The run's card in a channel and the Add › Start a flow drawer (C2, #3048, workspace-flows-channel-run-card.md).
- `runFlag()` lives in `assets/js/workspace_flow_flag.js` (`window.PGWsFlowFlag`, in the workspace.min.js bundle before workspace.js, so the channel screen has it without the canvas); `workspace_flow.js` calls it and still offers `window.PGWsFlow.runFlag()`. It needs only `window.PGWsKit` and the `fl_flag_*` and `fl_run_*` texts of `ws_flow_js_strings()`, which every workspace screen carries.

## Which result does a run show?
- `ws_flow_run_result_pick()` (pure, `flow_runs.php`): a failed run shows the sentence of its first failed box; any other run the sentence of the box that moved last, a box still open counting as the latest; a skipped box ("Not reached in this run.") comes last whenever it settled, as the walk writes it in the ending box's second with a larger id (#3473, bug #3370). Boxes without a sentence are passed over; cut to `WS_FLOW_RUN_RESULT_CHARS` (1000).
- Each sentence is the one the reader is shown (`ws_step_result_shown()`): a reader without edit gets a condition's or record change's plain word. No column holds it; it is read from `ws_flow_run_steps` (no schema change).

## Which reads carry it?
- `result` on every run answer (`ws_api_flow_run_present()`, REST and the panel): a single read works it out from its steps, a list passes `ws_flow_runs_results()` (one query for the page). Webhook run events leave it out (`ws_flow_run_event_run()` reads no boxes).
- `ws_flow_list` (panel) gives each flow `last_run` from `ws_flow_last_runs()`: the newest run of the flow; when the person may not read that one, the flow shows none rather than an older run.
- The canvas keeps a flow's `last_run` current from every run it reads, starts or cancels (`noteLastRun()`); a later run started elsewhere shows after the list is read again.
