---
name: workspace-flow-draft-card-window
description: The flow draft card (D8b #3032, V7a #3393, K3 #6630): a still picture at rest, a click or Enter opens the read-only Flows canvas in a 20 rem pane; the strip below with link and try
---

## What does the card show at rest? (D8b #3032, V7a #3393)
- The entry carries `window` for the asker while it waits (`$may`) and once applied with a `flow_id`. workspace.js `flowWindow()` reads panel action `ws_flow_draft_graph` (`ws_flow_draft_card_graph_read()`, once a page per card state) and `flowWindowDraw(stage, graph, false)` draws a still SVG (no per-box focus or click) in `.ws-fd-pane` (16rem); the card's `box_*` and `more` lines are left out. The channel stays light and the wheel scrolls the channel (owner, #4967 answer 2).
- The graph: waiting new flow = snapshot graph; waiting patch = ops made again on the flow's draft now (`edit` access); applied = the flow's draft now (`view`), with `flow_id` and `can_try` (`ws_flow_share_manager()`). Asker, `ws_can_schedule()`, `ws_can_post_channel()` as `ws_flow_draft_open()`. Nothing written.
- `ws_flow_card_graph($graph, $ops)` (pure): places scaled 128/208 across, 60/80 down; `outbound`, `mark`, `try`, `said`/`failed`, `threads`, `removed`. Boxes 128 × 60 with the kind's icon, name, kind and dry run; arrows `.ws-fd-arrow` with a filled head; threads `.ws-fd-thread` (`ws_flow_card_threads()`).
- The pane looks like the canvas (#6414): `var(--pg-bg-1)`, dots 10 px apart, `background-attachment: local`.

## How does a card open? (K3, #6630)
- A click or Enter on the picture (`flowCardOpen()`): `flowEmbedKit()` loads `CFG.flow_scripts` (`pg_graph_canvas.min.js`, then `workspace_flow.min.js`, from `ws_screen_config()`; none on the Flows screen) and panel action `ws_flow_embed_config` once a page; panel action `ws_flow_draft_view` (`ws_flow_draft_card_view_read()`) is read on every open. `PGWsFlow.embed(stage, { graph, mark, tries, config, people, onSelect, onClose })` replaces the picture; destroy puts it back.
- `.ws-fd-stage.is-open` is 20rem, `is-large` 40rem (on a phone `min(40rem, 75vh)`); `.ws-fd-stage .pgc-stage` is 100% high (the Flows screen's 60vh phone rule would push the zoom buttons out of the pane). The bar above: Larger/Smaller (fits again), Close (focus back on the picture). No stored preference.
- One card open at a time (`self.flowOpen`): opening another destroys the first. A card drawn anew (a message changed in a sync) whose change is open opens again with the view read anew. On the Flows screen `embed()` returns null and the card keeps its picture.
- The strip below (`flowCardBox()`): name, kind when the name is not the kind's, outbound and try from the picture's node, New / Changed, "Open on the canvas" (`?flow=&box=`), "Try this box" (`ws_flow_box_try`) for `can_try`.

## Whose dry run is the third line?
- `ws_flow_card_try_flow()` (pure) hands `ws_flow_box_try()` (unchanged, writes nothing): waiting new flow or scheduled sketch = in-memory row with the asker's rights; waiting patch none (F4e, the flow owner's); opened draft its flow where `can_try`. A refused try draws no line.

## Can a box be changed from the card?
- No (owner, 2026-11-02, #4967): the canvas is the one place a flow is changed. "Edit here" (V7b, #3493) was removed (#6495) with its actions `ws_flow_draft_box` / `ws_flow_draft_box_save`, `ws_flow_draft_box_*()` and `flowBoxForm()`.

## Tests
- tests/workspace_flow_draft_test.php (`test_ws_flow_card_*`). K3 was tried in headless Chromium (desktop 1280 × 900 and Pixel 7) on a stand-in channel page: open, pick, try, Larger, Close, Enter; pinch was not driven there (the canvas harness covers it).
