---
name: graph-canvas-core
description: Plan (2026-10-24, not started, answers open): a reusable box-and-arrow canvas core split from workspace_flow.js behind an adapter; Flows the first user, designer prototype links the second
---

## What is asked? (owner, 2026-10-24)
- The Flows canvas will be reused by other features; build it with that in mind. Next user: plans/designer-prototype-links.md.

## What is there today?
- assets/js/workspace_flow.js (3,600 lines with #1555): one IIFE exporting `PGWsFlow.start(root, boot)`, module-level state, helpers from `window.PGWsKit` (`shareKit()` in workspace.js), texts `ws_flow_js_strings()`, config `ws_flow_js_config()`; its own bundle (`pg_js_bundles()`), printed by `ws_screen_assets()` in mode flows.
- Generic, about 1,700 lines: viewport, pan, zoom, pinch, grid, boxes, ports, arrows, drag-to-connect, keyboard "Connect to…", select, move, delete, outline view, live region, focus keeping, debounced autosave (keepalive, 409 banner), problems.
- Flows only, about 1,900: `KINDS`, `PALETTE`, box forms, list, toolbar, publish, run view.
- CSS: the `.wsf-` block in backend.src.css, about half generic, on `--ws-*` tokens set only on Workspace pages.
- Fixed: box 208x80, one in and one out port, arrows `{from, to, on}` addressed by index (problems too), zoom 0.25-2, global ids (`#wsf-arrow*` used from CSS), document listeners never removed, Ctrl+S taken.
- Checks: tests/workspace_flows_canvas_test.php reads workspace_flow.js text (keyboard row names, `K.api('ws_flow_run_start', {`, `RUN_STATES`, `STEP_STATES`); tests/js_bundles_test.php (one bundle per source). The headless Chromium harness of #1529 and #1555 is not in the repo.

## What is proposed? (not approved yet)
- `PGGraphCanvas` in assets/js/pg_graph_canvas.js, bundle pg_graph_canvas.min.js, CSS `pgc-` on `--pgc-*` tokens with Bootstrap fallbacks (the Workspace maps them to `--ws-*`), texts from `pg_graph_canvas_js_strings()` in a new includes/fn/graph_canvas.php.
- `PGGraphCanvas.create(root, adapter)` returns load, graph, select, readOnly, overlay, destroy; ids per instance, arrowheads set from JS, listeners removed on destroy, Ctrl+S optional.
- Adapter: kit (or the core's own small helpers and dialog), texts, kinds (icon, name, arrows in, outcomes, defaults, size), ports, canConnect, isStart, node renderer, side-panel forms, palette, save(graph, {keepalive}) giving problems or a conflict (or no autosave, with onConnect that may refuse), a state overlay (tone, badge, taken arrows, focus keys), onSelect, onOpen.
- Flows keeps `KINDS`, `RUN_STATES`, `STEP_STATES`, forms, toolbar, publish, runs, graph JSON. New boxes (plans/workspace-flows-scheduled-merge.md) live in the Flows adapter only.

## Slices (one PR each)
1. Commit the harness (tests/js/, run by hand; needs Node and Chromium) and record today's result.
2. Pure refactor: core plus Flows adapter, behaviour identical, harness green before and after; a twin test that every core key is in tr.json; the literals the canvas test reads stay in workspace_flow.js; core printed first; `php tools/build_js.php`.
3. CSS to `pgc-` with tokens.
4. Only when a second user starts: size per node, lower minimum zoom, named ports inside a node (`{from, port, to}`, one arrow each), arrows into one frame spread out, non-box targets, stable arrow ids, a thumbnail renderer hook, partial redraw, load without defer.

## Open questions (recommendation)
1. Name: `pg_graph_canvas` / `pgc-` or a `ws_` prefix → `pg_` (the designer is no Workspace screen).
2. When: (a) right after #1555, before F4, (b) after F4, (c) when the designer plan starts → a: F4 and new boxes then build on the adapter; about 1,700 moved lines conflict with any open PR on the file.
3. Harness in the repo, run by hand → yes.
4. Designer texts: PHP `lang()` through `pg_graph_canvas_js_strings()`, or `_sdT()` scans of a new file → `lang()`.

## Where to start?
- Slice 1 once #1551 and #1555 merge; claim this plan file first. Read workspace/workspace-flows-canvas.md.
