---
name: workspace-flows-quick-setup-summary
description: The quick setup dialog's Summary stage (QS4 #9315): steps in order with moves, a held step indented, name, Start working when saved, Open on the canvas, what it sends and hands the opener
---

## What does it show?
- `drawSummary()` in `assets/js/workspace_flow_quick.js`: the start (its area's icon, its sentence, the "Only when" lines, a pencil back to Start), then every step in order, a condition's or loop's steps indented 30 px per level (`--wsf-quick-depth`).
- A step's line: its kind tile, its title or kind name, the canvas's own short line for the box (`PGWsFlow.boxMeta()`, `metaOf()`), and its buttons.
- Below: "Add a step" (back to the kinds, the step added at the end), "Name of the flow" (placeholder the start's sentence, 160) and "Start working when saved" (on by default).

## How is a step moved? (`pure.moves()`, `pure.move()`)
- Up and down among its siblings; into the condition or loop just above it (the end of its steps); out of the one holding it, right after it.
- Into is offered only within `limits.depth` (3) and never puts a loop inside a loop, as `ws_flow_quick_steps_read()` refuses both. The focus stays on the same button of the moved step, or its pencil.
- "Remove this step" (in the step's setting) leaves a removed condition's or loop's own steps in its place (`pure.remove()`).

## What does it send?
- `ws_flow_quick_setup` with `pure.sent()`: start, config, only_when, steps (id, kind, label, config, steps), name, channel_id (`options.home`), publish.
- "Start the flow" (or "Save the flow" with the switch off) sends publish as the switch says; "Open on the canvas" always sends a draft.
- A loop with nothing inside is stopped before the request (`pure.emptyLoop()`). A refusal shows as an alert in the Summary, focused; both buttons stay disabled only while the request runs.

## What does the opener get?
- The dialog closes first (`pgUI.dialog.hide()`, `ui/dialog-result-after-close.md`), then `options.onDone({ flow, published, publish_error, open })`; without onDone the page goes to `workspace_flows.php?flow=<id>`. The ways in (QS5) give onDone.

## What was checked?
- Node tests of moves, removal and the sent shape; a harness page with the real CSS and canvas script: a recipe plus a condition and a step put into it, a refused request, the switch off, the sent steps nested; light, dark, 390 px. No run on a site.
