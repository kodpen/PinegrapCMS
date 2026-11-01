---
name: graph-canvas-core
description: Plan (2026-10-24, answers #2445, 1, 2a-2d done): a box-and-arrow canvas core split from workspace_flow.js behind an adapter; first Flows, then designer prototype links
---

## What is asked? (owner, 2026-10-24)
- The Flows canvas will be reused by other features; build it for that. Next user: plans/designer-prototype-links.md.

## What is there today?
- assets/js/workspace_flow.js (11,300 lines): one IIFE, `PGWsFlow.start(root, boot)`, module state, `window.PGWsKit` helpers, texts `ws_flow_js_strings()`, config `ws_flow_js_config()`.
- Generic: viewport, pan, zoom, grid, boxes, ports, arrows, connect (drag, C), select, move, delete, outline, live region, kept focus, autosave (keepalive, 409), problems.
- Flows only: `KINDS`, `PALETTE`, box forms, list, toolbar, publish, run view.
- CSS: the `.wsf-` block of backend.src.css, half generic, on `--ws-*` tokens of the Workspace only.
- Fixed: box 208x80, one port in and out, arrows `{from, to, on}` by index, zoom 0.25-2, global ids (`#wsf-arrow*`), document listeners kept, Ctrl+S taken.
- Checks: tests/workspace_flows_canvas_test.php reads workspace_flow.js (keyboard rows, `RUN_STATES`, `STEP_STATES`, each `'fl_*'` literal) and extracts functions by name; harness tests/js/flows_canvas.js.

## What is proposed? (not approved yet)
- `PGGraphCanvas` in assets/js/pg_graph_canvas.js, bundle pg_graph_canvas.min.js, CSS `pgc-` on `--pgc-*` tokens with Bootstrap fallbacks (the Workspace maps them to `--ws-*`), texts from `pg_graph_canvas_js_strings()` in a new includes/fn/graph_canvas.php.
- `PGGraphCanvas.create(root, adapter)` returns load, graph, select, readOnly, overlay, destroy; ids per instance, arrowheads set from JS, listeners removed on destroy, Ctrl+S optional.
- Adapter: kit, texts, kinds (icon, name, arrows in, outcomes, size), ports, canConnect, isStart, node renderer, forms, palette, save(graph, {keepalive}) giving problems or a conflict, a state overlay (tone, badge, taken arrows, focus keys), onSelect, onOpen.
- Flows keeps `KINDS`, `RUN_STATES`, `STEP_STATES`, forms, toolbar, publish, runs, graph JSON; new boxes (workspace/workspace-flows-scheduled-merge.md) live in its adapter only.

## Slices (one PR each)
1. Done #3162: the harness, run by hand (workspace/workspace-flows-canvas-harness.md); 30 of 30 since #4316.
2. Pure refactor: core plus Flows adapter, behaviour identical, the harness's result the same before and after; new tests: core keys in the shards and `pg_graph_canvas_js_strings()`, the core bundled; the literals the canvas test reads stay in workspace_flow.js; core printed first. Cut into PRs of about 1,000 lines:
   - 2a done #4402: the core file and bundle, printed first; helpers, undo history, `viewport(host)` moved; workspace_flow.js aliases them (`var x = G.x`; `ws_flows_canvas_test_node()` searches the core too).
   - 2b done #4497: `create(adapter)`: pointers, pinch, Space, zoom keys, `destroy()`.
   - 2c done #4518: `box()`, `place()`, `edge()`, `mark()`, `announce()`, box move and connect drags; texts `pgc_*` (`ui/graph-canvas-core-module.md`). The outline stays in Flows (its forms, nothing generic yet).
   - 2d done #4557: `autosave(host)`, its state on the host's record; Flows' host sends `ws_flow_save`.
3. CSS to `pgc-` with tokens.
4. Only when a second user starts: size per node, lower minimum zoom, named ports (`{from, port, to}`), arrows into one frame spread out, non-box targets, stable arrow ids, a thumbnail hook, partial redraw, load without defer.

## Which questions are answered? (#2445)
1. Name: `pg_graph_canvas` / `pgc-` (not only the Workspace).
2. When: after the current flows wave (channels C-steps, data threads) lands and before designer-prototype-links slice 1.
3. Harness in the repo, run by hand: yes.
4. Designer texts: `lang()` via `pg_graph_canvas_js_strings()`; the scan reads style_designer.js only, and the core is the designer's first bundle.

## Where to start?
- Slice 3 (CSS to `pgc-`) now that slice 2 is merged; claim with a draft PR. Harness before and after (`--shots` to compare); read workspace/workspace-flows-canvas.md.
