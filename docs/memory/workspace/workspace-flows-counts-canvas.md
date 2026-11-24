---
name: workspace-flows-counts-canvas
description: A live flow's counts on the Flows canvas (FE11b): Counts and the days in the bar, pills over boxes, a pill's runs in the panel with the box's pass and the record's name, Open all in Flow runs
---

## Who sees Counts, and what does it do?
- "Counts" (`fl_counts`, quiet, `bi-bar-chart`) sits in the bar after Flow runs for whoever reaches a flow with a live version (`canCount()`: `flow.version > 0`, no run shown, no trial, not embedded); the server's rights are the runs list's ([[workspace-flows-counts]]).
- Pressed (`pressedLook()`), it reads `ws_flow_counts` and shows beside it the days as a `pg-seg`: "Last 7 days", "Last 30 days", 30 at first. No 90: ended runs are kept 30 days (`WS_FLOW_RUNS_KEEP`), so the server would answer 30.
- Read again every 60 s while shown (`COUNTS_EVERY`); a look is skipped while the page is hidden, a run is shown or a trial is on. Pressed again, the counts go (`endCounts()`); another flow, the list or leaving the screen ends them too.

## What is over a box?
- `countPills(line, start)` (pure, tested): waiting (`pg-tone-amber`), passed (gray), failed (`pg-tone-rose`), each only above 0; a start's passes read "N started". A box no run reached, and a value box, has none.
- The pills are look A's `.pg-pill` as buttons in `.wsf-counts`, above the box (`paintCounts()` from `fillBox()`); `.wsf-counts button` is in the canvas's `ownClicks`, so a press is not a drag. The pill listed is `aria-pressed`, ringed in its tone.
- Counted by box id over the draft drawn: a box only in the draft shows none, a box only in older versions is not drawn.

## What does a pill list?
- The panel (`countsSide()`, `state.panel = 'counts'`): the box's name, the count as its title, Close; then the runs, 20 a page (`ws_flow_runs_list` with `node`, `state`, `days`), More, and "Open all in Flow runs".
- Each row: the contact's name (`about`) or "Run #N", and the box's pass (`at`): "Waits until …" (a Wait's `wake_at`), "Waiting since …", "Passed …", "Failed …". A row opens the run; closing that run comes back to the list (`counts.back`).
- A box or arrow chosen takes the panel over, as with Flow runs; with nothing chosen the list is back. The same pill again closes it.
- "Open all in Flow runs" narrows the runs list (`state.runs.filter`) with "Only the runs of …" and "Show every run"; the bar's Flow runs always lists every run.

## What does the server add for it?
- `ws_flow_runs_list` (panel action) with a valid `node` and `state` gives each run `at` (`ws_flow_runs_at_node()`: one read by `uq_node`, `ws_flow_counts_at_fold()` keeps the last pass in that state) and `about` (`ws_flow_runs_about()`: only contacts the person opens with the contacts right, `ws_flow_run_contact_readable()`). The API's runs route is unchanged.
- `ws_flow_contact_name()`: name, else company, else e-mail; `ws_flow_run_contact_check()` uses it too.

## Where is it tested and shown?
- `tests/workspace_flows_canvas_test.php`: `test_workspace_flows_canvas_count_pills`; `tests/workspace_flow_counts_test.php`: the fold, the name, the list's lines. Not run in a browser.
- Design examples: the pattern "Counts" (`pg_look_examples_flow_counts_pattern()`).
