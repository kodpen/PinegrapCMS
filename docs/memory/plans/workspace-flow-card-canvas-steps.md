---
name: workspace-flow-card-canvas-steps
description: Steps K1-K5 of plans/workspace-flow-card-canvas.md: the embed, the server side, the card, Edit here removed, the other drawings; claim as [plan:workspace-flow-card-canvas] K<n>
---

## Which steps? (one PR each)
- K1 the embed (done, #6592): `PGWsFlow.embed(host, boot)` in workspace_flow.js. One live instance at a time (one `state`; a second embed destroys the first, whose host shows what it showed before). Stage and zoom buttons only, `state.edit` false, fit on load; boxes, arrows, meta lines, data rows and threads as on the canvas, threads always on; a `mark` overlay from the boot's dry run; `onSelect(node)`; `destroy()`. The wheel only while the host has focus (click, Tab), so the channel scrolls over a card nobody opened; pinch and the zoom buttons as on the canvas; the core's keys while focused, nothing of the editor's. Tests: tests/workspace_flows_canvas_test.php (its rows stay) and the harness, the same result before and after. Files: assets/js/workspace_flow.js and its bundle. About 500 lines.

- K2 the server side (done, #6593): `ws_flow_draft_card_view_read()` (flow_draft.php) gives the graph at its real places (never `ws_flow_card_graph()`'s scaled ones) with `ws_flow_card_tries()` per box, `mark` from `ws_flow_patch_marks()`, `flow_id`, `can_try`, `ws_flow_box_names()`, under the rights of `ws_flow_draft_card_graph_read()`; `ws_flow_embed_config($viewer)` (flows.php), the part of `ws_flow_js_config()` the drawing needs (kinds' labels, subjects, metrics, targets, repeats, events, tables, mcp names: labels only), for a home reader too, as panel action `ws_flow_embed_config`. Pure parts in tests/workspace_flow_draft_test.php. Files: includes/workspace/flow_draft.php, flows.php, actions.php, includes/panel/actions.php. About 300 lines.

- K3 the card (done, #6630): the picture at rest, a click or Enter opens `PGWsFlow.embed()` in a 20 rem pane (Larger doubles it), the strip below with "Open on the canvas" and "Try this box"; Close or another card brings the picture back. What it does now: `workspace/workspace-flow-draft-card-window.md`.

- K4 Edit here goes (done, #6495): `ws_flow_draft_box_edit()`, `_texts()`, `_fields()`, `_set()`, `_flow()`, `_read()`, `_save()`, the actions `ws_flow_draft_box` and `ws_flow_draft_box_save` (actions.php, panel/actions.php rows), `flowBoxForm()` and the graph's `edit` in workspace.js, their texts in `ws_flow_draft_js_strings()`, tests/workspace_flow_draft_edit_test.php; `workspace-flow-draft-card-window.md` loses its V7b section; `workflow/decisions-made.md` gets the owner's decisions (2026-11-02: the card is read-only, the canvas is the one place a flow is changed; #3444's "Edit here" withdrawn: only a box's name could be changed there; and #4967's answers 2-5 as the plan lists them). First. Files: includes/workspace/flow_draft.php, actions.php, panel/actions.php, assets/js/workspace.js, tests, memory. Removes about 500 lines.

- K5 the other drawing: Add › Scheduled flow's mini window (`scheduledFlowWindow()`, `workspace-flows-scheduled-ways-in.md`) draws with `flowWindowDraw()` too and publishes its two-box flow there. Its picture becomes the read-only embed of the sketch with Publish under it (decided in #4967); `flowWindowDraw()` then keeps the thumbnail's duty only. Files: assets/js/workspace.js. About 200 lines.

## In what order?
- K4 first. K1 and K2 side by side (different files). K3 after both. K5 after K3. Before K1: `graph-canvas-core.md` and `workspace-flows-canvas-harness.md`.
