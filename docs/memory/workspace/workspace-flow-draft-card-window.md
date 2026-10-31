---
name: workspace-flow-draft-card-window
description: The flow draft card as a mini flow window (D8b #3032, V7a #3393, V7b #3493): 128 × 60 px boxes, arrows and threads to the spec, the dry run as each box's third line, link, try and Edit here per box
---

## What does the card show? (D8b, #3032; V7a, #3393)
- The entry carries `window` for the asker while it waits (`$may`) and once applied with a `flow_id`. workspace.js `flowWindow()` reads panel action `ws_flow_draft_graph` (`ws_flow_draft_card_graph_read()`, once a page per card state) and `flowWindowDraw()` draws an SVG in a 16rem pane; the card's `box_*` and `more` lines are left out.
- The graph: waiting new flow = snapshot graph; waiting patch = ops made again on the flow's draft now (`edit` access); applied = the flow's draft now (`view`), with `flow_id`, `can_try` (`ws_flow_share_manager()`) and `edit`. Asker, `ws_can_schedule()`, `ws_can_post_channel()` as `ws_flow_draft_open()`. Nothing written.
- `ws_flow_card_graph($graph, $ops)` (pure): places scaled 128/208 across, 60/80 down, top-left at 16 px; `outbound` kinds (orange), `mark` from `ws_flow_patch_marks()` (dashed), `try` false for value boxes, `said`/`failed`, `threads`, `removed`.
- A picked box shows below: name, kind, marks; once opened "Open on the canvas" (`?flow=&box=`) and, for `can_try`, "Try this box" (`ws_flow_box_try`). CSS `.ws-fd-*` in backend.src.css.

## How is it drawn? (V7a)
- Boxes 128 × 60: the kind's icon (`flowIconPaths()`) and name, the kind (or New / Changed), the dry run as third line (red when it would not run).
- Arrows `.ws-fd-arrow` 2 px from the right edge, a filled head as its own path. Threads `.ws-fd-thread` from `ws_flow_card_threads()` (pure, reuses `ws_flow_template_threads()`; type number or text, tones in turn), quadratic, sag `10 + 0.05 × span`, 3 px apart per dot; `{{trigger.*}}` draws none.

## Whose dry run is the third line?
- `ws_flow_card_try_flow()` (pure) hands `ws_flow_box_try()` (unchanged, writes nothing): waiting new flow or scheduled sketch = in-memory row with the asker's rights; waiting patch none (F4e, the flow owner's); opened draft its flow where `can_try`. A refused try draws no line.

## What does Edit here do? (V7b, #3493, decided #3444)
- `ws_flow_draft_box_edit()` (pure) gives the graph's `edit`: `open` on a waiting card where opening passes (asker, `ws_can_schedule()`, may post, `edit` on a patch's flow), `edit` on an opened card whose flow is at `edit`, else none. "Open and edit here" calls `ws_flow_from_draft` (`ws_flow_draft_open()`), keeps `flowEdit` and after `sync()` the opened card opens that box's form; "Edit here" opens it at once.
- Form (`flowBoxForm()`): actions `ws_flow_draft_box` / `ws_flow_draft_box_save`; the box's name and the texts of `ws_flow_draft_box_texts()` (task, task_open, post, email, notify, webhook, task_digest, report, checkin, approval: the canvas's thread texts, same labels). The canvas's own forms need its state (workspace_flow.js), so people, times, rules stay there.
- `ws_flow_draft_box_set()` (pure) changes only those; the save is `ws_flow_save()` with the `draft_rev` read (409 `changed_meanwhile`: toast and window read again). The `ws_ai_changes` row is never written.

## Tests
- tests/workspace_flow_draft_test.php (`test_ws_flow_card_*`), tests/workspace_flow_draft_edit_test.php (V7b). Not run in a browser.
