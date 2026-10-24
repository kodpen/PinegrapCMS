---
name: workspace-flows-canvas
description: The flow canvas (workspace_flows.php, workspace_flow.js): who sees it, panel actions, editor, autosave, 409, focus, keys, outline, read-only, the run view, what is left
---

## What is there?
- Screen `workspace_flows.php` (mode `flows`, rail key `flows`). `ws_flows_nav_visible()` (one read) shows it to staff and to whoever may run a live flow of a channel they read; the channel sidebar reads `CFG.flows.nav`.
- `workspace.js` calls `window.PGWsFlow.start(root, BOOT)` and lends `PGWsKit` (`api` with `{keepalive}`, `ask`, `toast`, `select`, `openTask`). Bundle `workspace_flow.min.js`, mode `flows` only.
- Texts `ws_flow_js_strings()` (`fl_*`). Config `ws_flow_js_config($viewer, $mode)`: `nav`; mode `record` gets `nav: false`, no query; `flows` adds build, subjects, limits.

## Which panel actions?
- `ws_flow_list`, `_get`, `_create`, `_save`, `_publish`, `_pause`, `_bin`, `ws_flow_run_start`, `ws_flow_runs_list` (20 a page), `ws_flow_run_get`, `ws_flow_run_cancel`: `ws_` family rows (exempt, token, write). The store's and runner's `*_check()` decide.
- `ws_action_flow_fields()` always passes `draft_rev`; `ws_action_flow_refused()` turns a 409 into `field: changed_meanwhile`.
- Runs answer with the API's presenters (`ws_action_flow_run_present()` loads `api_flows.php`), whose times use `ws_flow_time()`: `api_time()` is API-only. `ws_flow_run_get` adds `due` (`ws_scheduled_due()`).

## How is the editor built?
- Toolbar: "New flow" (slot 1, the one primary), name, status, save state, Outline, Flow runs, Run, Problems, Publish, Pause, ⋯. Below: list | stage | side.
- Stage: a layer moved by translate+scale; SVG arrows under 208×80 HTML boxes, snapped to 16. Pan: empty canvas, Space+drag, middle button, wheel. Zoom: Ctrl+wheel, pinch, − % + fit (0.25–2).
- Run-only access: the live graph read-only (no palette, drag, Publish).

## How is it saved?
- 1.5 s after the last change, on blur, Ctrl+S; Publish, Pause, another flow and "New flow" save first.
- Hidden tab or page left: a keepalive `ws_flow_save` whose answer is read; over 60 000 bytes none (a hidden tab saves the usual way, leaving asks). `beforeunload` asks while changes are not on their way.
- Unsaveable changes are dropped on a switch only after `ask()`. A 409 shows "Load it again".

## Focus and keys?
- `state.refocus` names `data-refocus` keys, space-separated fallbacks. `keepFocus()` redraws and hands the focus to the control drawn in place, or its box (`box-<id>`).
- Tab in arrow order; arrows move 16 (Shift 64); C "Connect to…"; Delete; Enter: first field; + − 0; Esc.

## What is the outline view?
- A toggle, default under 768 px: boxes in arrow order with settings and "Connect to…"; the hidden canvas is drawn when shown.

## What does the run view do?
- Run (live, version > 0, edit or run access) asks, then starts once: `state.starting` stops a second click; its token is kept until the server answers, so a start sent again gets the run the first one started. Primary only where "New flow" is not shown.
- "Flow runs" lists runs in the side panel while nothing is selected. A run opened shows its version's graph read-only (`state.edit` false); draft, selection, view are in `state.stash`, and saves keep sending the draft (`draftOf()`), never the run's graph. Closing brings them back.
- Each box: `wsf-run-<state>` tone, mark and word (ready, running, waiting, done, failed, skipped; none = not reached), its result, a waiting task's link (`?task=`, opens `K.openTask()`, a change there sends `ws_tick`). Taken arrows marked; the outline says states in words.
- Poll: 2 s, ×1.5 up to 30 s while unchanged; stops when the run ends or the tab hides; `due` sends `ws_tick` (once in 20 s). Changes go to the polite live region. Cancel (`can_cancel`) asks.

## What is left?
- Notes on the canvas, undo/redo, auto-layout, templates, multi-select, Help link; retry or skip a box (F5).
- Tests `tests/workspace_flows_canvas_test.php`. The headless harness is not in the repo.
