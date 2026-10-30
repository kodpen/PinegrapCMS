---
name: workspace-flows-canvas
description: The flow canvas (workspace_flows.php, workspace_flow.js): who sees it, panel actions, editor, autosave, 409, focus, keys, outline, read-only, what is left; the run view has its own file
---

## What is there?
- Screen `workspace_flows.php` (mode `flows`, list key `flows`); no `?flow=`: `workspace-flows-list.md`. `ws_flows_nav_visible()` shows it to staff, live flows' home readers and sharees (`ws_nav_groups()`).
- `workspace.js` calls `window.PGWsFlow.start(root, BOOT)` and lends `PGWsKit`. Bundle `workspace_flow.min.js`, mode `flows` only.
- Texts `ws_flow_js_strings()` (`fl_*`). Config `ws_flow_js_config($viewer, $mode)`: `nav`; mode `record`: `nav: false`, no query; `flows` adds build, subjects, limits.

## Where are staff's scheduled actions?
- At the end of the flow list, converted and not converted (V5, #3222); no section of their own, `?view=scheduled` opens the list: `workspace-flows-scheduled-section.md`.

## Which panel actions?
- `ws_flow_list`, `_get`, `_create`, `_save`, `_publish`, `_pause`, `_bin`, `ws_flow_run_start`, `ws_flow_runs_list` (20 a page), `ws_flow_run_get`, `_cancel`, `ws_flow_shares`, `ws_flow_share_set`, `_remove`: `ws_` rows (exempt, token, write); the store's, runner's, shares' `*_check()` decide.
- `ws_action_flow_fields()` always passes `draft_rev`; `ws_action_flow_refused()` makes a 409 `field: changed_meanwhile`.
- Runs and shares answer with the API's presenters (`api_flows.php`), times via `ws_flow_time()` (`api_time()` is API-only). `ws_flow_run_get` adds `due` (`ws_scheduled_due()`).

## How is the editor built?
- Toolbar: "New flow" (slot 1, primary), "Flows ›", name, status, save state, Outline, Flow runs, Run, Problems, Publish, Pause, ⋯; below: palette | stage | side.
- Palette (`drawPalette()`, left column, edit access only; else `wsf-no-palette`): Start from a template while empty, a search, `PALETTE_GROUPS` (start, values, logic, people, message, system) with coloured headings; start says "This flow: <kind>" (`paletteHeading()`). Click or drag; phones: the outline's buttons.
- Stage: a layer moved by translate+scale; SVG arrows under 208×80 HTML boxes, grid 16. Pan: empty canvas, Space+drag, middle button, wheel. Zoom: Ctrl+wheel, pinch, − % + fit (0.25-2).
- Run/view access: the live graph read-only, no palette, drag, Publish. Share dialog (⋯): `workspace-flows-shares.md`.
- Box look, kind icons: `workspace-flows-box-look.md`.

## How is it saved?
- 1.5 s after the last change, on blur, Ctrl+S; Publish, Pause, another flow, "New flow" save first.
- Hidden tab or page left: a keepalive `ws_flow_save`; none over 60 000 bytes (a hidden tab saves as usual, leaving asks). `beforeunload` asks while changes are unsent.
- Unsaveable changes are dropped on a switch after `ask()` only. A 409 shows "Load it again".

## Focus and keys?
- `state.refocus`: `data-refocus` keys, space-separated fallbacks. `keepFocus()` redraws, then focuses the control redrawn in place, else its box (`box-<id>`), outline heading, the run's title or the run bar's Close.
- Tab in arrow order; arrows move 16 (Shift 64); C "Connect to…"; Delete; Enter: first field; + − 0; Esc. Undo: `workspace-flows-undo.md`.

## What is the outline view?
- A toggle, default under 768 px: boxes in arrow order; a box's heading opens its settings and "Connect to…" (the selected box, `outlineOpen()`), one at a time; the hidden canvas redraws when shown. All forms at once was 686 controls at 97 boxes (#3497).

## Where is the run view?
- `workspace-flows-run-view.md`: Run, the runs list, a run shown box by box, how it is read again and what a failed read shows.

## What is left?
- Canvas notes, auto-layout, multi-select. Tests `tests/workspace_flows_canvas_test.php`, `workspace-flows-canvas-harness.md`.
- Templates and the gallery: `workspace-flows-template-format.md`.
