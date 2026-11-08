---
name: workspace-flow-layer
description: flowLayer() in workspace.js: the whole Flows editor in a full-screen layer over a channel (L3); bar, inert page, Escape, Close with flush, config once a page
---

## What is it?
- `flowLayer(flowId, { box, run, opener })` in workspace.js (lent as `PGWsKit.flowLayer`): a `.ws-flow-layer` (`position: fixed; inset: 0`, z-index 1035) appended to `body`, with the whole editor mounted in it by `PGWsFlow.mount(body, BOOT, { flow, box, run, single: true, onShow, onClose })` (`workspace-flow-mount.md`). Owner decision 2026-11-08 (`plans/flows-canvas-in-channel.md`): the canvas over the channel, not a second editor.
- Bar: the flow's name (`onShow`, also as it is renamed), "Open on the Flows screen" (`fl_layer_screen`, `flowScreenAddress()` with `?box=` / `?run=`), Close. One layer at a time: a second call while one is open does nothing.

## What does it load?
- `flowScripts()`: `CFG.flow_scripts` once a page (shared with the draft card's `flowEmbedKit()`). `flowEditorConfig()`: panel action `ws_flow_editor_config` once a page; its `flows` replaces `CFG.flows` (a superset of the channel page's `nav`/`scheduled`), its `strings` fill keys the page lacks.

## What happens to the page beneath?
- Every `body` child but dialogs, scripts and the toast root gets `inert` (restored on close), `html.ws-flow-layer-on` stops its scroll; the chat is left as it was. Ctrl+K (the Workspace search) does nothing while the layer is open. Dialogs opened later (`#ws-ask`, Share) are in the top layer; menus and toasts appended later are not inert.

## Escape and Close?
- Escape on the layer closes it only when the editor did not take it (`defaultPrevented`: a selected box lets go first), no `dialog[open]`/`askUp`, and the target is no field, menu or listbox.
- `flowLayerClose()`: `instance.flush()`; `false` (failed save, newer draft, unsent) asks `fl_leave_ask` / `fl_leave` as `beforeunload` does. `flowLayerEnd()`: `destroy()`, page live again, focus back on the opener (else the control focused at opening). `onClose` (flow deleted or not opened) ends it at once.

## How was it checked?
- Unit: `test_workspace_flows_canvas_layer_wiring`. Browser: two scenarios at the end of `tests/js/flows_canvas_scenarios.js` call `PGWsKit.flowLayer()` on the harness page (desktop 1440x900: full screen, page inert, address kept, box selected, Escape twice, the move saved before close, focus back; phone 390x844: outline, no sideways scroll, Close). The harness page is the Flows screen, not a channel page (the channel app needs many more stand-in actions); the screen's editor is destroyed by the layer's mount there. 32 of 32 from sources and `--min`.
