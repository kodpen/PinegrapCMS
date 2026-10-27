---
name: workspace-flows-canvas
description: The flow canvas (workspace_flows.php, workspace_flow.js): who sees it, panel actions, editor, autosave, 409, focus, keys, outline, read-only, what is left; the run view has its own file
---

## What is there?
- Screen `workspace_flows.php` (mode `flows`, rail key `flows`). `ws_flows_nav_visible()` shows it to staff, live flows' home readers and sharees; the sidebar reads `CFG.flows.nav`.
- `workspace.js` calls `window.PGWsFlow.start(root, BOOT)` and lends `PGWsKit` (`api` with `{keepalive}`, `ask`, `toast`, `select`, `peoplePicker`, `openTask`). Bundle `workspace_flow.min.js`, mode `flows` only.
- Texts `ws_flow_js_strings()` (`fl_*`). Config `ws_flow_js_config($viewer, $mode)`: `nav`; mode `record`: `nav: false`, no query; `flows` adds build, subjects, limits.

## Where are staff's scheduled actions?
- `ws_flows_scheduled($viewer)` (staff, flow tables there; `CFG.flows.scheduled`): an entry under the flow list opens a section in place of the canvas (`?view=scheduled`), drawn by `scheduledList(body, bar)` of workspace.js through `PGWsKit`; the rail has no Scheduled actions entry then, workspace.php's `?view=scheduled` shows their scheduled messages only (`&part=messages` names that tab without flow tables). Parity: `workspace-flows-parity-test.md`.
- "Show on canvas" on a card there: `ws_scheduled_get` answers `graph` (pure `ws_scheduled_graph()` of the staff card: when, conditions, step, follow-ups by outcome; chips: starters, started, self-retry); `showAction()` draws it read-only on a scrolling stage; Edit opens the form.

## Which panel actions?
- `ws_flow_list`, `_get`, `_create`, `_save`, `_publish`, `_pause`, `_bin`, `ws_flow_run_start`, `ws_flow_runs_list` (20 a page), `ws_flow_run_get`, `_cancel`, `ws_flow_shares`, `ws_flow_share_set`, `_remove`: `ws_` rows (exempt, token, write); the store's, runner's, shares' `*_check()` decide.
- `ws_action_flow_fields()` always passes `draft_rev`; `ws_action_flow_refused()` makes a 409 `field: changed_meanwhile`.
- Runs and shares answer with the API's presenters (loading `api_flows.php`), times via `ws_flow_time()` (`api_time()` is API-only). `ws_flow_run_get` adds `due` (`ws_scheduled_due()`).

## How is the editor built?
- Toolbar: "New flow" (slot 1, the one primary), name, status, save state, Outline, Flow runs, Run, Problems, Publish, Pause, ⋯; below: list | stage | side.
- Stage: a layer moved by translate+scale; SVG arrows under 208×80 HTML boxes, grid 16. Pan: empty canvas, Space+drag, middle button, wheel. Zoom: Ctrl+wheel, pinch, − % + fit (0.25-2).
- Run/view access: the live graph (never published: its draft) read-only, no palette, drag, Publish. Share dialog (⋯): `workspace-flows-shares.md`.

## How is it saved?
- 1.5 s after the last change, on blur, Ctrl+S; Publish, Pause, another flow, "New flow" save first.
- Hidden tab or page left: a keepalive `ws_flow_save`, answer read; none over 60 000 bytes (a hidden tab saves as usual, leaving asks). `beforeunload` asks while changes are not on their way.
- Unsaveable changes are dropped on a switch after `ask()` only. A 409 shows "Load it again".

## Focus and keys?
- `state.refocus`: `data-refocus` keys, space-separated fallbacks. `keepFocus()` redraws, then focuses the control redrawn in place, else its box (`box-<id>`), outline heading, the run's title or the run bar's Close.
- Tab in arrow order; arrows move 16 (Shift 64); C "Connect to…"; Delete; Enter: first field; + − 0; Esc.

## What is the outline view?
- A toggle, default under 768 px: boxes in arrow order with settings and "Connect to…"; the hidden canvas redraws when shown.

## Where is the run view?
- `workspace-flows-run-view.md`: Run, the runs list, a run shown box by box, how it is read again and what a failed read shows.

## What is left?
- Notes on the canvas, undo/redo, auto-layout, multi-select, Help link; retry or skip a box (F5).
- Templates: `workspace-flows-template-format.md`. Tests `tests/workspace_flows_canvas_test.php`; no headless harness.
