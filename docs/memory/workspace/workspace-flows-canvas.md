---
name: workspace-flows-canvas
description: The flow canvas (workspace_flows.php, workspace_flow.js): who sees it, panel actions, the editor from edge to edge with its floating parts, autosave, 409, focus, keys, outline, read-only
---

## What is there?
- Screen `workspace_flows.php` (mode `flows`, list key `flows`); no `?flow=`: `workspace-flows-list.md`. `ws_flows_nav_visible()` shows it to staff, live flows' home readers and sharees (`ws_nav_groups()`).
- `workspace.js` calls `window.PGWsFlow.start(root, BOOT)` and lends `PGWsKit`. Bundle `workspace_flow.min.js`, mode `flows` only, after the core `pg_graph_canvas.min.js` (`ui/graph-canvas-core-module.md`).
- Texts `ws_flow_js_strings()` (`fl_*`). Config `ws_flow_js_config($viewer, $mode)`: `nav`; mode `record`: `nav: false`, no query; `flows` adds build, subjects, limits.
- Staff's scheduled actions are rows of the flow list (`workspace-flows-not-converted.md`).

## Which panel actions?
- `ws_flow_list`, `_get`, `_create`, `_save`, `_publish`, `_pause`, `_bin`, `ws_flow_run_start`, `ws_flow_runs_list` (20 a page), `ws_flow_run_get`, `_cancel`, `ws_flow_shares`, `ws_flow_share_set`, `_remove`: `ws_` rows (exempt, token, write); the store's, runner's, shares' `*_check()` decide.
- `ws_action_flow_fields()` always passes `draft_rev`; `ws_action_flow_refused()` makes a 409 `field: changed_meanwhile`.
- Runs and shares answer with the API's presenters (`api_flows.php`), times via `ws_flow_time()`; `ws_flow_run_get` adds `due`. Trial, counts, Tidy: `ws_flow_trial`, `ws_flow_counts`, `ws_flow_tidy`.

## How is the editor built?
- Bar: name with its window, status, save state; Canvas / List; Problems, Counts, Flow runs, Try, Run, Publish, ⋯ (`workspace-flows-window-bar.md`). Below it the canvas from edge to edge (`.wsf-app` one column); every other part floats over it.
- Over the canvas: the add rail and its list card at the top left, the bottom strip (Undo, Redo, zoom, Fit, Tidy, Data threads, Note, Map), the map card (`workspace-flows-canvas-tools.md`, `-palette.md`, `-tidy-map.md`); the chosen box's card at the right, closed with nothing chosen (`-side-panel.md`, `-name-window.md`).
- Stage: a layer moved by translate+scale; SVG arrows under the cards, grid 16. Pan: drag the empty canvas, Space, middle button, wheel. Zoom: Ctrl+wheel, pinch, the strip's − % + and Fit (0.25-2).
- Building: "+" on an arrow (`-add-here.md`), several boxes (`-several-boxes.md`), notes and frames (`-notes-frames.md`). Try (`-trial-canvas.md`), Counts (`-counts-canvas.md`), runs (`-run-view.md`).
- Run/view access: the live graph read-only, no rail, drag, Publish. Share dialog (⋯): `workspace-flows-shares.md`.
- Box look: `-box-look.md`; arrow names: `-arrow-names.md`; data threads: `-thread-focus.md`; titles with values, live hint: `-run-notice.md`.

## How is it saved?
- 1.5 s after the last change, on blur, Ctrl+S; Publish, Pause, another flow, the way back to the list save first.
- Hidden tab or page left: a keepalive `ws_flow_save`, none over 60 000 bytes; `beforeunload` asks while changes are unsent (`workspace-flows-canvas-leave-focus.md`).
- Unsaveable changes are dropped on a switch after `ask()` only. A 409 shows "Load it again".

## Focus and keys?
- `state.refocus`: `data-refocus` keys, spaced fallbacks. `keepFocus()` redraws, then focuses the control redrawn in place, else its box (`box-<id>`), outline heading, the run's title or the run bar's Close.
- Tab in arrow order; arrows move 16 (Shift 64), several chosen boxes together; C "Connect to…"; A, /: `-add-here.md`; Delete; Enter: Settings' first field; + − 0; Esc; F6 between the areas. Undo: `workspace-flows-undo.md`.

## What is the outline view?
- The bar's List choice, default under 768 px: boxes in arrow order; a box's heading opens its settings and "Connect to…" (`outlineOpen()`), one at a time.

## Where is it tested?
- `tests/workspace_flows_canvas_test.php` (Node), the browser harness by hand (`workspace-flows-canvas-harness.md`).
- Templates and the gallery: `workspace-flows-template-format.md`.
