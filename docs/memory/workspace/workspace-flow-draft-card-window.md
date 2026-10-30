---
name: workspace-flow-draft-card-window
description: The flow draft card as a mini flow window (D8b #3032, V7a #3393): 128 × 60 px boxes, arrows and small data threads to the spec, the dry run as each box's third line, link and try per box
---

## What does the card show? (D8b, #3032; V7a, #3393)
- The entry carries `window` for the asker: while it waits (the same `$may` as its lines) and once applied with a `flow_id`. workspace.js `flowWindow()` then asks panel action `ws_flow_draft_graph` (`ws_flow_draft_card_graph_read()`, read once a page per card state) and `flowWindowDraw()` draws an SVG in px, scrolled in a 16rem pane; the card's `box_*` and `more` lines are left out (the start, problems and a patch's change lines stay).
- The graph: waiting new flow = snapshot graph; waiting patch = ops made again on the flow's draft now (`edit` access, refused as opening refuses); applied = the flow's draft now (`view` access), with `flow_id` and `can_try` (`ws_flow_share_manager()`, as the try checks). Asker, `ws_can_schedule()`, `ws_can_post_channel()` as `ws_flow_draft_open()`. Nothing written.
- `ws_flow_card_graph($graph, $ops)` (pure): places scaled by 128/208 across and 60/80 down (`WS_FLOW_CARD_BOX_*` over `WS_FLOW_CARD_CANVAS_*`) so gaps keep their share of a box, the top-left box at `WS_FLOW_CARD_PAD` (16 px); `width`/`height` (room for the lowest thread); `outbound` for `ws_flow_card_outbound_kinds()` (email, task_digest, webhook, mcp, change, row_add, row_set: orange outline and arrow), `mark` from `ws_flow_patch_marks()` (dashed), `try` false for value boxes (`pure`), `said`/`failed` (third line), `threads`, `removed` names (not one added and removed by the same patch; shown under an opened card, the waiting one lists Removes).
- A picked box shows below: name, kind, marks; once opened "Open on the canvas" (`?flow=&box=`) and, for `can_try`, "Try this box" (`ws_flow_box_try`, no run chosen: said or refusal, next times). CSS `.ws-fd-*` in backend.src.css.

## How is it drawn? (V7a, visual spec "Mini window and templates?")
- Boxes 128 × 60 px: the kind's icon (`flowIconPaths()`, the canvas's set) and name, the kind (or New / Changed), the dry run as the third line (red when it would not run; the whole text in `title` and the label).
- Control arrows `.ws-fd-arrow`: 2 px `#7d8696` from the right edge, a filled head drawn as its own path (no marker id shared between cards).
- Data threads `.ws-fd-thread` (before V7a the arrows wrongly took this class): `ws_flow_card_threads()` (pure) reuses `ws_flow_template_threads()` (from, to, key), adds the value the field reads first and its type (`ws_flow_card_value_type()`: number for number, integer, money; else text, as the canvas colours), tones taken in turn (`.ws-fd-tone-<type>-<n>`, the canvas's colours). A dot per value on the source's bottom edge, ends spread on the target's bottom edge, a quadratic curve with its control point `ws_flow_card_thread_sag()` (10 + 0.05 × span) below the lower end; one dot's threads 3 px apart (`WS_FLOW_CARD_THREAD_GAP`), the farthest on the left and 3 px deeper per place. `{{trigger.*}}` values draw no thread here.

## Whose dry run is the third line?
- `ws_flow_card_try_flow()` (pure) picks the flow `ws_flow_card_tries()` hands to `ws_flow_box_try()` (called, unchanged; writes nothing): a waiting new flow or the scheduled sketch = an in-memory row (id 0, owner the asker, `draft` status, the graph as `draft`, access `edit`), so the asker's own rights; a waiting patch none (the try is its flow owner's alone, F4e); an opened draft its flow only where `can_try`. `ws_flow_card_try_line()`: said, or "It would not run: …"; a refused try (403) no line.

## Tests
- tests/workspace_flow_draft_test.php (`test_ws_flow_card_graph*`, `_box_size`, `_thread_sag`, `_threads`, `_try_flow`). The drawing itself was not run in a browser.
