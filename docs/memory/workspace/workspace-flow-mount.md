---
name: workspace-flow-mount
description: PGWsFlow.mount(root, boot, options) in workspace_flow.js: the Flows editor drawn into any element, single-flow mode for a layer over a channel, destroy() and flush(), window listeners bound once
---

## What is it?
- `window.PGWsFlow.mount(root, boot, options)` draws the whole Flows editor (toolbar, palette, canvas, side panel, outline, run view) into `root` and returns `{ destroy(), flush() }`, null without `window.PGWsKit`. `start(root, boot)` is `mount()` with `addressOptions(location.search)` (`?flow=`, `?box=`, `?run=`). Step L1 of `plans/flows-canvas-in-channel.md`.
- options: `flow` (id to open), `box` (selected once open), `run` (shown once open), `single`, `onClose`.

## What does `single` change?
- One flow in a layer over another screen: no `ws_flow_list` read, no New flow button, no `history.replaceState` (`setAddress()`), no `K.viewBack()` (`viewBack()` puts a way back such as the template gallery's into the editor's own bar; `backToList()` draws nothing).
- `showNone()` (the way back to the list: flow deleted, one that cannot be opened) calls `onClose` instead. With no `flow`, builders get the ways to start (`showGallery(null)`), others `onClose`.
- `clearScreen()` is the part of `showNone()` the gallery shares, so the gallery does not close a single editor.

## What do destroy() and flush() do?
- `destroy()` (`unmount()`): stops the run's looks (`stopPoll()`) and the settle, setup and save timers, sends what is unsaved (`flush()`, its promise returned), `canvas.destroy()` (the core's listeners), removes the share and template dialogs from `body`, empties `root`, clears the page's back arrow (not for `single`), frees `state.screen` and `state.mounted`. A second call does nothing. A save still on its way finishes; its answer draws into the detached nodes.
- `flush()` resolves `true` once nothing is left unsaved, `false` after a failed save or against a newer draft (the layer asks first, as `beforeunload` does).

## One at a time?
- The module keeps one `state` and one core `canvas`: a second `mount()` destroys the first, and an embedded drawing (`workspace-flow-embed.md`) ends first. `embed()` returns null while an editor is mounted (`state.screen`). `mountState()` resets the per-editor fields (flow, graph, runs, poll, marks, sketch …) and `ui` is emptied before `layout()`.

## Window listeners?
- `bindWindow()` adds keydown (Ctrl+S, undo), blur, visibilitychange, pagehide and beforeunload once (`windowBound`); each returns at once while `state.screen` is false, so a destroyed editor never saves or asks on leave.

## Tests
- `tests/workspace_flows_canvas_test.php` `test_workspace_flows_canvas_editor_mounts_and_destroys` (Node, the real `mount`/`unmount`/`bindWindow`/`showNone`/`clearScreen` with stubs); the address and parity tests read `addressOptions()` and `clearScreen()`.
