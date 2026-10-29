---
name: graph-canvas-core
description: Plan (2026-10-24, not started, answers open): a reusable box-and-arrow canvas core split from workspace_flow.js behind an adapter; first Flows, then designer prototype links
---

## What is asked? (owner, 2026-10-24)
- The Flows canvas will be reused by other features; build it for that. Next user: plans/designer-prototype-links.md.

## What is there today?
- assets/js/workspace_flow.js (3,600 lines with #1555): one IIFE exporting `PGWsFlow.start(root, boot)`, module-level state, helpers from `window.PGWsKit` (`shareKit()` in workspace.js), texts `ws_flow_js_strings()`, config `ws_flow_js_config()`; its own bundle, printed by `ws_screen_assets()` in mode flows.
- Generic, about 1,700 lines: viewport, pan, zoom, pinch, grid, boxes, ports, arrows, drag-to-connect, keyboard "Connect to…", select, move, delete, outline view, live region, kept focus, debounced autosave (keepalive, 409 banner), problems.
- Flows only, about 1,900: `KINDS`, `PALETTE`, box forms, list, toolbar, publish, run view.
- CSS: the `.wsf-` block in backend.src.css, about half generic, on `--ws-*` tokens of `body.workspace`, `.ws-app`, `[id^="ws-"]` only.
- Fixed: box 208x80, one in and one out port, arrows `{from, to, on}` addressed by index (problems too), zoom 0.25-2, global ids (`#wsf-arrow*` used from CSS), document listeners never removed, Ctrl+S taken.
- Checks: tests/workspace_flows_canvas_test.php reads workspace_flow.js (keyboard rows, `K.api('ws_flow_run_start', {`, `RUN_STATES`, `STEP_STATES`), wants each `'fl_*'` literal in `ws_flow_js_strings()`; tests/js_bundles_test.php globs only workspace*.js. The Chromium harness of #1529 and #1555 is not in the repo.

## What is proposed? (not approved yet)
- `PGGraphCanvas` in assets/js/pg_graph_canvas.js, bundle pg_graph_canvas.min.js, CSS `pgc-` on `--pgc-*` tokens with Bootstrap fallbacks (the Workspace maps them to `--ws-*`), texts from `pg_graph_canvas_js_strings()` in a new includes/fn/graph_canvas.php.
- `PGGraphCanvas.create(root, adapter)` returns load, graph, select, readOnly, overlay, destroy; ids per instance, arrowheads set from JS, listeners removed on destroy, Ctrl+S optional.
- Adapter: kit (or the core's own helpers and dialog), texts, kinds (icon, name, arrows in, outcomes, defaults, size), ports, canConnect, isStart, node renderer, side-panel forms, palette, save(graph, {keepalive}) giving problems or a conflict (or no autosave, an onConnect that may refuse), a state overlay (tone, badge, taken arrows, focus keys), onSelect, onOpen.
- Flows keeps `KINDS`, `RUN_STATES`, `STEP_STATES`, forms, toolbar, publish, runs, graph JSON; new boxes (workspace/workspace-flows-scheduled-merge.md) live in its adapter only.

## Slices (one PR each)
1. Commit the harness (tests/js/, run by hand; needs Node and Chromium) and record today's result.
2. Pure refactor: core plus Flows adapter, behaviour identical, harness green before and after; new tests: core keys in the shards and `pg_graph_canvas_js_strings()`, the core bundled; the literals the canvas test reads stay in workspace_flow.js; core printed first.
3. CSS to `pgc-` with tokens.
4. Only when a second user starts: size per node, lower minimum zoom, named ports inside a node (`{from, port, to}`, one arrow each), arrows into one frame spread out, non-box targets, stable arrow ids, a thumbnail hook, partial redraw, load without defer.

## Which questions are answered? (#2445)
1. Name: `pg_graph_canvas` / `pgc-` (not only the Workspace).
2. When: after the current flows wave (channels C-steps, data threads) lands and before designer-prototype-links slice 1; the 1,700 moved lines conflict with open PRs on the file.
3. Harness in the repo, run by hand: yes.
4. Designer texts: `lang()` via `pg_graph_canvas_js_strings()`; the scan reads style_designer.js only, and the core is the designer's first bundle.

## Where to start?
- Slice 1 (#1551, #1555 are in); claim with a draft PR. Read workspace/workspace-flows-canvas.md.
