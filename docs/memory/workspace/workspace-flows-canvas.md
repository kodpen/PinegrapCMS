---
name: workspace-flows-canvas
description: The flow canvas (workspace_flows.php, workspace_flow.js): who sees it, panel actions, editor, autosave, 409, focus, keys, outline, read-only, run view, what is left
---

## What is there?
- Screen `workspace_flows.php` (mode `flows`, rail key `flows`). `ws_flows_nav_visible()` shows it to staff, live flows' home readers and sharees; the sidebar reads `CFG.flows.nav`.
- `workspace.js` calls `window.PGWsFlow.start(root, BOOT)` and lends `PGWsKit` (`api` with `{keepalive}`, `ask`, `toast`, `select`, `peoplePicker`, `openTask`). Bundle `workspace_flow.min.js`, mode `flows` only.
- Texts `ws_flow_js_strings()` (`fl_*`). Config `ws_flow_js_config($viewer, $mode)`: `nav`; mode `record`: `nav: false`, no query; `flows` adds build, subjects, limits.

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
- `state.refocus`: `data-refocus` keys, space-separated fallbacks. `keepFocus()` redraws, then focuses the control redrawn in place, else its box (`box-<id>`), outline heading or the run's title.
- Tab in arrow order; arrows move 16 (Shift 64); C "Connect to…"; Delete; Enter: first field; + − 0; Esc.

## What is the outline view?
- A toggle, default under 768 px: boxes in arrow order with settings and "Connect to…"; the hidden canvas redraws when shown.

## What does the run view do?
- Run (live, version > 0, edit or run access) asks, then starts once (`state.starting`). Its token goes as `start_token`: `token` is the CSRF token, which a data key would replace in `api()` (every start was refused). Kept until answered: a start sent again gets the first one's run. Primary only without "New flow".
- "Flow runs" lists runs while nothing is selected; pressed while the list or a run shows (`runsShown()`). A run picked: its button disabled meanwhile, only the last pick shown (`openSeq`). Its version's graph read-only; draft, selection, view wait in `state.stash`; saves send the draft (`draftOf()`).
- Each box: `wsf-run-<state>` tone, mark, word (none = not reached), result, a waiting task's link (`?task=`, `K.openTask()`, then `ws_tick`). Taken arrows marked; outline in words.
- Poll: 2 s, ×1.5 to 30 s unchanged; stops once ended or hidden. `poll.busy` is a run id: a slow look at one run froze the next. `due` sends `ws_tick`; polite live region; Cancel (`can_cancel`) asks.

## What is left?
- Notes on the canvas, undo/redo, auto-layout, templates, multi-select, Help link; retry or skip a box (F5). Tests `tests/workspace_flows_canvas_test.php`; no headless harness in the repo.
