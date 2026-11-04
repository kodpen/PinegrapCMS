---
name: workspace-flow-draft-card-window
description: The flow draft card as a read-only mini flow window (D8b #3032, V7a #3393): 128 × 60 px boxes, arrows and threads to the spec, the dry run as each box's third line, link and try per box
---

## What does the card show? (D8b, #3032; V7a, #3393)
- The entry carries `window` for the asker while it waits (`$may`) and once applied with a `flow_id`. workspace.js `flowWindow()` reads panel action `ws_flow_draft_graph` (`ws_flow_draft_card_graph_read()`, once a page per card state) and `flowWindowDraw()` draws an SVG in a 16rem pane; the card's `box_*` and `more` lines are left out.
- The graph: waiting new flow = snapshot graph; waiting patch = ops made again on the flow's draft now (`edit` access); applied = the flow's draft now (`view`), with `flow_id` and `can_try` (`ws_flow_share_manager()`). Asker, `ws_can_schedule()`, `ws_can_post_channel()` as `ws_flow_draft_open()`. Nothing written.
- `ws_flow_card_graph($graph, $ops)` (pure): places scaled 128/208 across, 60/80 down, top-left at 16 px; `outbound` kinds (orange), `mark` from `ws_flow_patch_marks()` (dashed), `try` false for value boxes, `said`/`failed`, `threads`, `removed`.
- The pane `.ws-fd-pane` looks like the canvas (#6414): surface `var(--pg-bg-1)` as `.pgc-stage` (light or dark with the panel), `.pgc-viewport`'s dots 10 px apart (16 px at 128/208), `background-attachment: local` so they scroll with the drawing.
- `.ws-drafts` is one `minmax(0, 1fr)` column (#6668): with `auto`, a drawing wider than the card widened the card and the whole channel.
- A picked box shows below: name, kind, marks; once opened "Open on the canvas" (`?flow=&box=`) and, for `can_try`, "Try this box" (`ws_flow_box_try`). CSS `.ws-fd-*` in backend.src.css.

## How is it drawn? (V7a)
- Boxes 128 × 60: the kind's icon (`flowIconPaths()`) and the name as the canvas gives it (`ws_flow_box_names()`: an untitled box by its kind), the kind (or New / Changed) only when the name is not the kind's, the dry run under them (red when it would not run).
- Arrows `.ws-fd-arrow` 2 px from the right edge, a filled head as its own path. Threads `.ws-fd-thread` from `ws_flow_card_threads()` (pure, reuses `ws_flow_template_threads()`; type number or text, tones in turn), quadratic, sag `10 + 0.05 × span`, 3 px apart per dot; `{{trigger.*}}` draws none.

## Whose dry run is the third line?
- `ws_flow_card_try_flow()` (pure) hands `ws_flow_box_try()` (unchanged, writes nothing): waiting new flow or scheduled sketch = in-memory row with the asker's rights; waiting patch none (F4e, the flow owner's); opened draft its flow where `can_try`. A refused try draws no line.

## Can a box be changed from the card?
- No (owner, 2026-11-02, #4967): the canvas is the one place a flow is changed. "Edit here" (V7b, #3493) was removed (#6495) with its actions `ws_flow_draft_box` / `ws_flow_draft_box_save`, `ws_flow_draft_box_*()` and `flowBoxForm()`.

## Tests
- tests/workspace_flow_draft_test.php (`test_ws_flow_card_*`). Not run in a browser.
