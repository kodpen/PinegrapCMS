---
name: workspace-flows-template-setup
description: Placing a flow template (G2): motion schedule from ws_flow_template_motion(), drawn threads, reduced motion, the setup panel, ws_flow_template_setup action, answers checked on the server
---

## What happens on Use?
- The graph is placed as in G1 (new flow, or the empty flow it was asked for), then `arrive()` in `workspace_flow.js` plays `template.motion`: boxes rise and grow from 92 %, arrows grow (marker hidden while they do), data threads draw, then the setup panel slides in over the canvas.
- With `prefers-reduced-motion: reduce` nothing moves: boxes, arrows and threads are there at once and the panel opens at once (CSS animation off too).

## Where are the timings?
- `ws_flow_template_motion($graph)` (pure, `flow_templates.php`), served in each template as `motion`: box n at n × 150 ms, 420 ms each; an arrow at the later end of its two boxes, 200 ms; threads after the last arrow, 200 ms apart, 650 ms each; `panel` when all is in. Numbers from `plans/workspace-flows-visual-spec.md`.
- Threads come from `ws_flow_template_threads()`: one per `{{node.<id>.<field>}}` in a box's setting (from, to, key); the card's `threads` count is their number.

## How are threads drawn?
- `drawThreads()`: 1 px, text colour `#e9b44c`, from the bottom middle of the giving box to the bottom middle of the reading box, a quadratic curve hanging 10 px + 0.06 × span below; threads of one box spread apart, the farthest leaving on the left.
- They are drawn only by the placement and go at the next canvas redraw (`drawEdges()` removes `.wsf-thread`): the canvas has no lasting thread layer yet (data-threads plan).

## What does the setup panel ask?
- One field per `settings` entry, filled with the first target's current value: channel (select), person (checkboxes of the team), time (time input), url, address (comma separated).
- "Open on canvas" writes the answers into the draft; "Publish now" writes them and publishes. Close or Escape leaves the template's own values.
- Both send `ws_flow_template_setup` (panel action, `actions.php`) after `flush()`, with the flow's `draft_rev`; a 409 shows the usual conflict bar.

## What does the server check?
- `ws_flow_template_setup()`: the template rebuilt for the person (`ws_flow_templates_for()`, the gallery's fill), so a key not offered to them is refused; `ws_flow_template_answers_check()` (pure): channel id > 0, 1–20 people ids, `HH:MM`, an http(s) URL up to 2000, addresses through `ws_scheduled_addresses()`; the refused setting is the error's `field`.
- `ws_flow_template_apply()` (pure) writes a value only into a box still the template's (same id and kind); boxes removed or changed on the canvas stay as they are.
- Then `ws_flow_save()` and, for Publish now, `ws_flow_publish()`, whose checks decide rights (channel written in, people, addresses). A refused publish keeps the saved answers and comes back as `publish_error`, shown as a toast.

## Tests?
- `tests/workspace_flow_template_setup_test.php`: schedule numbers, answers, apply, the action and reduced motion present.
