---
name: workspace-flow-draft-card-window
description: The flow draft card as a mini flow window (D8b, #3032): panel action ws_flow_draft_graph, ws_flow_card_graph(), outbound and patch marks, link and try per box
---

## What does the card show? (D8b, #3032)
- The entry carries `window` for the asker: while it waits (the same `$may` as its lines) and once applied with a `flow_id`. workspace.js `flowWindow()` then asks panel action `ws_flow_draft_graph` (`ws_flow_draft_card_graph_read()`, read once a page per card state) and `flowWindowDraw()` draws an SVG at 0.4 of canvas size, scrolled in a 16rem pane; the card's `box_*` and `more` lines are left out (the start, problems and a patch's change lines stay).
- The graph: waiting new flow = snapshot graph; waiting patch = ops made again on the flow's draft now (`edit` access, refused as opening refuses); applied = the flow's draft now (`view` access), with `flow_id` and `can_try` (`ws_flow_share_manager()`, as the try checks). Asker, `ws_can_schedule()`, `ws_can_post_channel()` as `ws_flow_draft_open()`. Nothing written.
- `ws_flow_card_graph($graph, $ops)` (pure): places moved so the top-left box sits at `WS_FLOW_CARD_PAD`, `width`/`height`, `outbound` for `ws_flow_card_outbound_kinds()` (email, task_digest, webhook, mcp, change: orange outline and arrow), `mark` from `ws_flow_patch_marks()` (dashed), `try` false for value boxes (`pure`), `removed` names (not one added and removed by the same patch; shown under an opened card, the waiting one lists Removes).
- A picked box shows below: name, kind, marks; once opened "Open on the canvas" (`?flow=&box=`) and, for `can_try`, "Try this box" (`ws_flow_box_try`, no run chosen: said or refusal, next times). CSS `.ws-fd-*` in backend.src.css.

## Tests
- tests/workspace_flow_draft_test.php (`test_ws_flow_card_graph*`). The drawing itself was not run in a browser.
