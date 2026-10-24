---
name: workspace-flows-canvas
description: The flow canvas (workspace_flows.php, workspace_flow.js): who sees it, the ws_flow_* panel actions, how the editor is built, autosave and 409, keyboard, outline view, read-only, what is left
---

## What is there?
- Screen `workspace_flows.php` (mode `flows`, rail key `flows`). `ws_flows_nav_visible()` shows it to staff (`ws_can_schedule()`) and to anyone who may run a live flow of a channel they read; the channel sidebar shows it through `CFG.flows.nav`.
- `workspace.js` loads `ws_bootstrap`, then calls `window.PGWsFlow.start(root, BOOT)` (people, departments, channels, priorities). The canvas uses the `PGWsKit` helpers (`api`, `ask`, `toast`, `select`, `clear`). Bundle `workspace_flow.min.js`, printed by `ws_screen_assets()` in mode `flows` only.
- Texts: `ws_flow_js_strings()` (`fl_*`). Config: `ws_flow_js_config($viewer, $screen)` (nav; on the screen also build, subjects from `ws_scheduled_record_types()`, limits).

## Which panel actions?
- `ws_flow_list`, `ws_flow_get` (the owner gets the draft, problems and `changed`; a runner gets the live graph), `ws_flow_create`, `ws_flow_save`, `ws_flow_publish`, `ws_flow_pause`, `ws_flow_bin`. All are rows of the `ws_` family (exempt, token, write).
- The cases call `ws_flow_save()` / `_publish()` / `_pause()` / `_bin()`. Their `*_check()` decide. `draft_rev` is always sent (`ws_action_flow_fields()`). A 409 answers `field: changed_meanwhile`, and the screen offers "Load it again".
- Presenters: `ws_flow_brief()`, `ws_flow_detail()`, `ws_flow_graph_present()` (the API's graph presenter delegates to it).

## How is the editor built?
- The toolbar (`.pg-toolbar`) holds the name, the status badge, the save state, Problems, Publish, Pause and a ⋯ menu with Delete. Below it a grid: list | stage | side.
- The stage has a viewport and a layer moved with a CSS translate+scale. On the layer: an SVG of cubic arrows (markers `wsf-arrow*`) under 208×80 HTML boxes. Places are canvas units snapped to 16. The out port is on the right, the in port on the left; a start has no in port, an end no out port.
- Pan: drag the empty canvas, Space+drag, middle button or the plain wheel. Zoom: Ctrl/⌘+wheel, pinch with two pointers, or the − % + fit buttons (0.25 to 2). The palette adds by click or HTML5 drag; ids are `<kind>_<n>`. A second start is disabled in the palette.
- Saving: 1.5 s after the last change, on window blur, Ctrl+S, and a keepalive fetch on pagehide or a hidden tab. The save answer brings the problems (server texts): boxes get `.wsf-bad`, arrows a dashed red line, and `#wsf-problems` lists them (a click selects the box).
- Publish needs no problems. `ask()` names version n+1. Afterwards the badge reads `Live · vN`, and "Changes not published yet" shows while the draft differs.

## Which keys?
- Tab goes through the boxes in arrow order (`orderedNodes()` is also the DOM order). The arrow keys move a box 16 (Shift: 64). C opens "Connect to…" in the `ask()` dialog. Delete/Backspace removes the box (with its arrows) or the arrow. Enter goes to the first field; + − 0 zoom and fit; Esc clears.
- The side panel offers the same without a pointer: "Connect to…" and Add the arrow, remove buttons, and done/cancelled for an arrow out of a task.

## What is the outline view?
- A toolbar toggle; it is the default under 768 px. It lists the boxes in arrow order, each with its full settings, its arrows and "Connect to…", with the palette below. A box added there goes right of the rightmost box.

## What does run-only access show?
- Access `run` shows the live graph read-only: no palette, disabled fields, no drag, no Publish.

## What is left?
- Notes on the canvas: the store keeps them, and the editor passes them through untouched. A version with notes waits for its own PR.
- Undo/redo, auto-layout, "from a template", multi-select. F3 adds Run and the run view.
- Tests: `tests/workspace_flows_canvas_test.php`. The headless harness is not in the repo.
