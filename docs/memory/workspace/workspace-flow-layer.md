---
name: workspace-flow-layer
description: flowLayer() in workspace.js: the whole Flows editor in a full-screen layer over a channel; ways in, Add › New flow, bar, inert page, Escape, Close with flush
---

## Why a layer?
- Owner, 2026-11-08: a flow is designed from a channel without leaving the chat. The whole canvas opens over the channel, mounted in the page (no frame); Add › Scheduled flow's quick window stays for the simple case. It keeps #4967: the layer is the canvas, not a second editor (PRs #8100, #8119, #8141, #8165, L5).

## What is it?
- `flowLayer(flowId, { box, run, opener, home })` (lent as `PGWsKit.flowLayer`): a `.ws-flow-layer` (`position: fixed; inset: 0`, z-index 1035) on `body`, the editor mounted by `PGWsFlow.mount(body, BOOT, { flow, box, run, home, single: true, onShow, onClose })` (`workspace-flow-mount.md`). Bar: the flow's name (`onShow`, also as renamed), "Open on the Flows screen" (`fl_layer_screen`, `flowScreenAddress()` with `?box=` / `?run=`), Close. One at a time.

## Which ways in open it?
- `flowGo(flowId, options)`: the layer, or on the Flows screen itself (`CFG.mode === 'flows'`) the address. `flowLinkOpens(link, flowId, options)` keeps the `href` for a new tab or modifier click.
- Through them: Add › Scheduled flow's Open on the canvas (`flowOpened()`, the drawer hidden first by `hideOffcanvas(node, next)`), a draft card's box link (`box`), an applied draft's flow link, a run card's Open the run (`run`), a scheduled action's Edit (`scheduledEdit()`), Duplicate, Open a drafted action.

## Add › New flow?
- The composer's Add list (`fl_new`): `CFG.flows.nav && CFG.flows.scheduled` (staff who build, `ws_flows_scheduled()`), not in a thread or an old era, a public or private channel. `flowLayer(0, { home: channel.id })`: the editor starts at the ways to start (`showGallery(null)`, as New flow on the Flows screen); the bar's link is the Flows screen until a flow is made, then that flow's address.
- `newHome()` in workspace_flow.js sends `channel_id` with the empty canvas, a template (`ws_flow_create`) and Pinegrap AI (`ws_flow_ai_sketch`, which checks the home with `ws_flow_save()`'s rule before asking the model); never for a role-3 member's own flow (`FL.member`).
- An editor opened with `home` drops its blank untouched flow on destroy (`dropUntouched()` in `unmount()`); one opened on a flow keeps it.

## What loads, what happens beneath?
- `flowScripts()`: `CFG.flow_scripts` once a page. `flowEditorConfig()`: `ws_flow_editor_config` once a page; its `flows` replaces `CFG.flows`, its `strings` fill missing keys.
- Every `body` child but dialogs, scripts and the toast root gets `inert` (restored on close); `html.ws-flow-layer-on` stops the scroll; Ctrl+K does nothing. Dialogs opened later are in the top layer.

## Escape and Close?
- Escape closes only when the editor did not take it (`defaultPrevented`), no `dialog[open]`/`askUp`, and the target is no field, menu or listbox.
- `flowLayerClose()`: `instance.flush()`; `false` asks `fl_leave_ask` as `beforeunload` does. `flowLayerEnd()`: `destroy()`, page live, focus back on the opener. `onClose` ends it at once.

## How was it checked?
- Unit: `test_workspace_flows_canvas_layer_wiring`, `_layer_ways_in`, `_layer_new_flow`. Browser: scenarios at the end of `tests/js/flows_canvas_scenarios.js` call `PGWsKit.flowLayer()` on the Flows screen harness (desktop 1440x900, phone 390x844); not a channel page (the channel app needs many more stand-in actions).
