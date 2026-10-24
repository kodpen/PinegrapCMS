---
name: workspace-flows-canvas
description: The flow canvas (workspace_flows.php, workspace_flow.js): who sees it, panel actions, the editor, autosave, leaving and 409, focus, keys, outline view, read-only, what is left
---

## What is there?
- Screen `workspace_flows.php` (mode `flows`, rail key `flows`). `ws_flows_nav_visible()` (one read) shows it to staff and to whoever may run a live flow of a channel they read; the channel sidebar reads `CFG.flows.nav`.
- `workspace.js` calls `window.PGWsFlow.start(root, BOOT)` and lends `PGWsKit` (`api` with `{keepalive}`, `ask`, `toast`, `select`, `clear`). Bundle `workspace_flow.min.js`, mode `flows` only.
- Texts: `ws_flow_js_strings()` (`fl_*`). Config: `ws_flow_js_config($viewer, $mode)`: `nav` only; mode `record` gets `nav: false` with no query; mode `flows` adds build, subjects, limits.

## Which panel actions?
- `ws_flow_list`, `_get`, `_create`, `_save`, `_publish`, `_pause`, `_bin`: rows of the `ws_` family (exempt, token, write). The store's `*_check()` decide.
- `ws_action_flow_fields()` always passes `draft_rev`; `ws_action_flow_refused()` turns a 409 into `field: changed_meanwhile` (tested).

## How is the editor built?
- Toolbar: "New flow" (slot 1, the one primary), name, status badge, save state, Problems, Publish, Pause, ⋯ (Delete). Below: list | stage | side.
- Stage: a viewport and a layer moved by translate+scale; an SVG of arrows under 208×80 HTML boxes, places snapped to 16; out port right, in port left.
- Pan: empty canvas, Space+drag (Space only on the canvas), middle button, wheel. Zoom: Ctrl+wheel, pinch, − % + fit (0.25–2). Esc or a second finger puts a box being moved back.

## How is it saved?
- 1.5 s after the last change, on window blur, Ctrl+S; Publish, Pause, opening another flow and "New flow" save first.
- Hidden tab or page left: a keepalive `ws_flow_save` whose answer is read, so `draft_rev` follows. Over 60 000 bytes (browsers refuse 64 KiB) no keepalive: a hidden tab saves the usual way, leaving asks.
- `beforeunload` asks while changes are not on their way (failed save, conflict, too large) and then sends nothing; `pagehide` sends if the person leaves.
- Changes that cannot be saved are dropped on a switch only after `ask()`. A change made while Publish/Pause runs is kept; only status and version are taken.
- A 409 shows the banner with "Load it again". `?flow=` that cannot open falls back to the first flow; with none left the address drops it.

## Where does the focus go?
- `state.refocus` names `data-refocus` keys, space-separated fallbacks: list entry, `name`, `status` after Publish/Pause, `new`, the next box's title after an outline removal.

## Which keys?
- Tab goes through boxes in arrow order. Arrows move 16 (Shift 64). C "Connect to…". Delete removes the box or arrow. Enter: first field. + − 0 zoom; Esc clears.
- The side panel does the same without a pointer. An arrow is selected by index; removing another arrow keeps the selection on its arrow.

## What is the outline view?
- A toggle, default under 768 px: boxes in arrow order with their settings, arrows and "Connect to…"; other boxes' names follow a rename. The hidden canvas is drawn when shown; people ticks only once "The people I pick" is chosen.

## What does run-only access show?
- The live graph read-only: no palette, no drag, no Publish.

## What is left?
- Notes on the canvas (passed through untouched), undo/redo, auto-layout, "from a template", multi-select; F3 adds Run.
- Tests: `tests/workspace_flows_canvas_test.php`. The headless harness is not in the repo.
