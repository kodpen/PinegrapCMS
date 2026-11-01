---
name: workspace-flow-card-canvas
description: Plan (owner 2026-11-02): the flow draft card shows the real Flows canvas read-only (pan, zoom, values, threads; no panes, no toolbar); changes only by opening the flow; Edit here goes; steps K1-K5
---

## What is asked? (owner, 2026-11-02)
- The flow draft card in a channel (`workspace-flow-draft-card-window.md`) draws its own small picture of the flow: 128 × 60 boxes in a 16 rem pane. The owner wants the flow as the Flows canvas shows it: freely panned and zoomed, every box with the words the canvas puts on it, the values it reads and gives and the data threads between them, all visible.
- The one difference from the Flows screen: read-only. Changing anything means opening the flow (`workspace_flows.php?flow=<id>&box=<id>`, the card's "Open on the canvas"). No list, palette or outline on the left, no box form on the right, no toolbar on top.
- "Edit here" / "Open and edit here" on a box (V7b, #3493, decided #3444) goes: only the box's name and a few texts could be changed there, which misleads more than it helps. "Try this box" (`ws_flow_box_try`) stays: it changes nothing.

## What is decided? (owner, 2026-11-02)
- The card draws with workspace_flow.js and the canvas core (pg_graph_canvas.js), never a second drawing of boxes, threads or meta lines: what the canvas shows, the card shows (`workspace-flows-box-look.md`, `workspace-flows-thread-drawing.md`, `workspace-flows-visual-spec.md`).
- Read-only is the canvas's own view mode (`state.edit` false, the `fl_read_only` paths) with the chrome left out: `layout()` builds the stage and the zoom buttons only; no `ui.bar`, `ui.side`, `ui.outlineBox`, `ui.gallery`, run bar, marks strip or sketch box; `bindWindow()`'s document listeners (Ctrl+S, undo, beforeunload) are not bound.
- The box the person picks is told below the pane, as the card tells it today (name, kind, New / Changed, the dry run, "Open on the canvas", "Try this box"): the strip under the pane is the card's one piece of chrome.
- The dry run ("It would not run: …", `ws_flow_card_tries()`) stays the box's third line, drawn through the canvas's `mark` overlay rather than a text of the card's own.
- The two bundles are printed for mode `flows` only (`ws_screen_scripts()`); the card fetches them once, as a record's screen fetches the workspace script (`ws_record_loader()`), and reads the drawing config from one panel action once per page: `ws_flow_js_config()` for mode `flows` is staff's and heavy.
- Steps and order: `workspace-flow-card-canvas-steps.md`.

## Which questions are open? (for the owner)
1. One live card at a time (K1 as written: the Flows script has one state, a channel may hold several cards) or every card live at once, which needs the Flows adapter made per instance first, a refactor of workspace_flow.js of its own (graph-canvas-core.md slice 4 territory), not a step here.
2. The resting state: today's thumbnail until a click (K3 as written), or live as soon as a card scrolls into view, the last one in view taking the wheel. The thumbnail keeps the channel light and the wheel predictable.
3. The pane: 20 rem with "Larger", or the card's height left to the person (a drag handle, kept per browser).
4. Add › Scheduled flow's mini window: left as it is, or the same read-only embed with Publish under it (K5).
5. "Try this box" stays on the strip (assumed yes).

## Where to start?
- K4, then K1 and K2 side by side; K3 once both are merged; K5 when question 4 is answered. The holder reviews K1 and K3 against the Flows canvas on desktop and a phone before they are marked ready.
