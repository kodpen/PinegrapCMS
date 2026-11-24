---
name: workspace-flows-trial-canvas
description: The whole-flow trial on the Flows canvas (FE10b, #11356): Try in the bar, the trial bar, the record picker, Next step and To the end, marks on boxes and arrows, the outcome choice, Preview
---

## Who sees Try, and what does it do?
- "Try" (`fl_trial`, quiet, `bi-play-circle`) sits in the bar before Run for the flow's owner editing the draft (`canTry()`: `state.edit`, `flow.manage`, no run shown, not embedded), as a box's Try tab.
- It saves the draft first (`flush()`), since `ws_flow_trial` walks the draft the server keeps ([[workspace-flows-trial]]); then `state.trial` is set, `state.edit` false, `.wsf-trial` and `.wsf-readonly` on the app: the canvas is read-only, the strip loses Undo, Redo, Tidy and Note.
- While it is on, the bar keeps the name, status and view switch and shows "Trial · nothing is sent" with "End the trial" (filled) in place of Problems, Flow runs, Run, Publish and ⋯ (`trialBar()`).
- End the trial (`closeTrial()`) gives `state.edit` back and focuses Try. Opening another flow, the list or a run ends it without drawing (`endTrial()` in `show()`, `clearScreen()`, `showRun()`).

## How is the record chosen?
- The panel (`trialSide()`): the record chosen or "No record", the flow's last five runs as buttons (`ws_flow_runs_list`, "Run #N · state", pressed when chosen; only for a flow with a version), then the contact search `customerPicker({ help, onPick })`, the same one Run asks with.
- A run sends `run_id`, a contact `contact_id`; another record clears the outcomes chosen and starts the walk again from nothing walked (`chooseRecord()`).

## How are the steps walked?
- Each change asks `ws_flow_trial` again (`loadTrial(shown)`: flow, run_id, contact_id, outcomes as JSON); only the last question asked is drawn (`seq`). Nothing is kept on the server.
- The panel lists the steps walked (badge, name, `said`, `refusal` as "It would fail", `empty`), then the step in turn named with "Next". "Next step" walks one, "To the end" the rest; at the end "The run would end: <state>.", the boxes not reached, and "Start over" (`walkTrial(0)`). Steps walked are told to screen readers.
- A person's box (task, approval, Pinegrap AI, agent) with two or more `choices` gets an "Outcome" select; a change keeps it in `trial.outcomes` and walks on from the box after it.

## What marks the canvas?
- `trialMarks(steps, shown, edges)` (pure, tested): walked boxes `done` with their outcome, a Wait (`delay`) `passed`, a failed one `failed`, the step in turn `next`; the arrows taken are a walked box's outcome and `always` unless it failed.
- `paintTrial()` after `paintRun()` in `fillBox()`: the run-state tone (`TRIAL_MARKS`: done, skipped, failed, running) and a badge with a mark and word: "Tried", "Passed", "Would fail", "Next"; a box with several outcomes says the one it left on (a condition's Yes or No).
- `drawEdges()` gives the taken arrows `wsf-edge-taken`; `.wsf-trial` shares the run view's rules in `backend.src.css`: taken green, others faint.

## What does Preview show?
- A step of a message box (group `message`) with texts: "Preview" opens the question dialog as a notice (`K.ask(..., { info: true })`) with the texts filled: subject, fallback subject or title as the heading, the rest as written, what stays empty named. Titled "“Box” as <record> would get it" with a record.

## Where is it tested and shown?
- `tests/workspace_flows_canvas_test.php`: `test_workspace_flows_canvas_trial_marks`, `test_workspace_flows_canvas_trial_panel` (Node). Not run in a browser.
- Design examples: the pattern "Try the flow" (`pg_look_examples_flow_trial_pattern()`).
