---
name: flows-editor-next-steps-3
description: Plan flows-editor-next, steps FE10b to FE15: the trial and the counts on the canvas, the Split box, sending hours, leaving the flow, close-out; what every step keeps and the order given out
---

## Which steps follow FE9 on the canvas line?
- FE10b (M) after FE9 and FE10a, done (#11356): the trial on the canvas (board C1): Try in the bar, the trial bar, the record's picker (`customerPicker()`), the steps, Next step and To the end, the marks on boxes and arrows, Preview.
- FE11b (M) after FE10b and FE11a, done (#11380): Counts in the bar with the days, the pills over boxes, the panel's list of those runs (board 3).
- FE12 (M) after FE11b, done (#11410): the Split box by the recipe of `workspace/workspace-flows-adding.md`: kind `split` in `ws_flow_kinds()` and the step kinds with its settings check, the pick by shares (pure, red test first) and the outcome kept on the step; the canvas form and named arrows as the Pinegrap AI box's; palette, icon, catalogue, AI hints, quick setup group, trial and counts.
- FE13 (M) after FE12, done (#11446): sending hours: the setting in `ws_step_config_normalize()` for post, email, sms and whatsapp; the next open time (pure, the site's time zone, red test first); the wait through `wake_at` or the settled "not sent"; an e-mail to a group; the folded row in the four forms; catalogue and AI hints; Try and the trial say when it would send.
- FE14 (M) after FE13, done (#11475): leaving the flow: `leave` in the graph (normalize, check, limits, API schema); the events offered (those naming a person or a record, from the catalogue); the hook where events wake waiting boxes (`ws_events_process()`), found by `idx_subject`; open steps cancelled, status `left` in the runs list, run view, run card, counts and API; the name's window part with its 30-day count. Red tests first for the match of an event to a run.
- FE15 (S) last, close-out: memory rewritten to what stands: `workspace/workspace-flows-canvas.md`, `-palette.md`, `-window-bar.md`, `-side-panel.md`, `-empty-panel.md`, `-box-look.md`, `-visual-spec.md`, `-thread-drawing.md`, `-value-picker.md`, `-run-view.md`, `-overview.md`, and one file per new function; `workflow/decisions-made-workspace-20.md` corrected where it says the panels are columns; this plan's files deleted, the drawings kept.

## What does every step keep?
- About 1,000 product lines; a step that would pass it says so to the architect before it grows and is cut in two there.
- No schema. A step that finds it needs a column or a key stops and asks the architect.
- What a board and these files leave open is asked of the architect before it is built; nothing is sent to the owner for a look.
- A new `includes/workspace/` file carries the Pinegrap header and is named in its memory file.
- Each step writes its memory in `docs/memory/workspace/` and marks itself done here with its PR number.

## In which order are the two lines given out?
- The canvas line FE1 to FE9, then FE10b to FE14, one worker at a time. FE10a, then FE11a, to a second worker beside it. FE15 when all have landed.

## In which order are the two lines given out?
- The canvas line FE1 to FE9, then FE10b to FE14, one worker at a time. FE10a, then FE11a, to a second worker beside it. FE15 when all have landed.

Related: docs/memory/plans/flows-editor-next.md, docs/memory/plans/flows-editor-next-steps-2.md, docs/memory/plans/flows-editor-next-screens-3.md
