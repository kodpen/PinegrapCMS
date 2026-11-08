---
name: flows-canvas-in-channel
description: Plan (owner 2026-11-08): the whole Flows canvas in a full-screen layer over a channel, mounted in the page, so a flow is designed without leaving the chat; steps L1-L5
---

## Why this plan?
- Owner, 2026-11-08: from a channel a flow cannot be designed. Add › Scheduled flow's window has two fixed boxes (time, message) and a read-only drawing; adding boxes, their settings or a try means Open on the canvas, which leaves the chat for the Flows screen.
- His decision: the whole canvas opens over the channel in a full-screen layer, mounted in the channel page (not a frame), as many PRs as it needs; the quick window stays for the simple case. It keeps #4967 (the canvas is the one place a flow is changed): the layer is that canvas, not a second editor.

## What does it build on?
- `PGWsFlow.start(root, boot)` (workspace_flow.js) runs the Flows screen: module state (`state.screen`, `CFG`, `FL`, `BOOT`), `layout()`, `bindCanvas()`, `bindWindow()` (keydown, blur, visibilitychange, pagehide, beforeunload), `history.replaceState` to `?flow=` in `open()` and `showNone()`, `K.viewBack()` on the page header, `?box=` / `?run=` read from the address.
- `PGWsFlow.embed()` draws one flow read-only on a channel page with `flowEmbedKit()` (`CFG.flow_scripts`, panel action `ws_flow_embed_config`); one canvas drawing at a time (`workspace-flow-draft-card-window.md`).

## Which steps?
- L1 (M, done #8100): a mountable editor: `PGWsFlow.mount(root, boot, options)` → `{ destroy(), flush() }`; options `flow`, `box`, `run`, `single` (no flow list, no address change, no `K.viewBack()`, the list's way back calls `onClose`), `onClose`. Window listeners added once and doing nothing while no editor is mounted; destroy saves what is on its way, stops the run poll and the timers, ends any embed first and frees `state.screen`. `start()` becomes `mount()` without `single`. workspace_flow.js only; tests on the wiring and the pure parts.

- L2 (S, done #8119): the editor's config on any Workspace page: panel action `ws_flow_editor_config` → the `flows` part of `ws_flow_js_config($viewer, 'flows')` and `ws_flow_js_strings()`, with the Flows screen's rights (`ws_screen_gate()` for flows, the builder kinds); read once a page. actions.php, flows.php.

- L3 (M, done #8141): the layer (`flowLayer(flowId, options)` in workspace.js): `position: fixed`, full screen on every width; its bar: the flow's name, "Open on the Flows screen", Close. Focus moves in and back to the opener; Escape closes only when no editor dialog or field holds it; closing calls `flush()` and, after a failed save or a newer draft, asks first as `beforeunload` does; the channel's own keys and composer stay idle while it is open; the chat stays as it was beneath. backend.src.css, workspace.js.

- L4 (S): the ways in open the layer instead of the Flows screen: Add › Scheduled flow's Open on the canvas, a draft card's Open on the canvas (`?box=`), a run card's Open the run (`?run=`), `flowOpened()` after a card's Edit or Duplicate; each keeps the Flows screen link in the layer's bar.

- L5 (S): Add › Flow in the composer (staff, not in a thread, where `CFG.flows.nav`): the layer opens on a new flow whose home is this channel, the template gallery first, as New flow on the Flows screen.

## How is it tested?
- Unit tests for the wiring and the pure helpers; each of L3-L5 is also tried in headless Chromium on a channel page (desktop and a phone size): open, add a box, change a setting, try, publish, close, the chat as it was. The PR says why a browser run was needed.

## Where to start (L4)?
- `flowLayer(flowId, { box, run, opener })` in workspace.js (`workspace-flow-layer.md`) is ready and lent as `PGWsKit.flowLayer`; L4 calls it from the ways in listed above (they set `window.location.href` to `CFG.urls.flows` today: `flowCardBox()` link, the scheduled window's Open, the run card's Open the run, `flowOpened()`).
- Browser checks: the two layer scenarios at the end of `tests/js/flows_canvas_scenarios.js` run on the Flows screen page; a channel page needs the channel app's stand-ins, which the harness lacks.

## Open questions
- None: the layout inside the layer is the Flows screen's (palette, canvas, side panel, outline on a phone).
