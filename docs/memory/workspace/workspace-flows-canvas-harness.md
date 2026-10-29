---
name: workspace-flows-canvas-harness
description: Browser harness of the Flows canvas (tests/js/flows_canvas.js, #3162, run by hand, not in CI): what is real and what stands in, how to run it, the scenarios, the result of 2026-10-29
---

## What is it?
- `tests/js/flows_canvas.js` serves workspace_flows.php as the panel prints it (Bootstrap 6 CSS, `pg_bs6_bridge.js`, `pg_ui.js`, backend.src.css, `#ws-config`, the `SCREEN_BUNDLES` from their sources, with `--min` the built ones) and drives headless Chromium over the DevTools protocol through a pipe. Node built-ins only, no packages.
- workspace.js runs for real (its kit, the `#ws-ask` dialog, toasts, menus); only api.php stands in: `flows_canvas_store.js`, fixtures draft (default), problems, threads, big, empty; the server's words, 409 on `draft_rev`, start tokens.
- `flows_canvas_server.php`, one PHP process: the texts `ws_screen_config()` merges, `pg_js_bundles()`, the part of `ws_flow_js_config()` that reads no database; every graph saved goes through `ws_flow_graph_normalize()` and `ws_flow_graph_check()`, so the problems are the server's (`ws_flow_problems()` reads the database).
- `flows_canvas_page.js`: the `window.PGHarness` probes; toasts kept, keepalive requests marked by a header. A browser context and a store per scenario.

## How is it run?
- `node tests/js/flows_canvas.js [filter] [--min] [--motion] [--dark] [--shots=<dir>] [--verbose]`; `PG_CHROMIUM` and `PG_PHP` name the binaries. About 75 s. Exit 0 all pass, 1 a failure, 2 could not run.
- Reduced motion by default; `--motion` keeps the fades. `--shots` takes a picture of each end state (light, or `--dark`) to compare CSS work.

## What do the scenarios cover?
- 29 scenarios, 407 checks (`flows_canvas_scenarios.js`): boxes at their places and sizes, arrows port to port, zoom, fit, pinch, pan (drag, wheel, Space, middle button), select, Tab order, arrow keys, drag on the grid, Esc, connect by port, C and the side panel, outcomes, Delete, undo, data threads (drag to bind, toggle kept), outline, phone, autosave after 1.5 s, 409, keepalive on hide and leave, the 60 000-byte limit, Ctrl+S, blur, Retry, switching flows; palette and drop, problems, Publish, read-only, ⋯ Share and Delete, no flow, New flow, Run and its token, runs list, polling.

## What did it give on 2026-10-29?
- 28 of 29 pass, from sources and with `--min`. Fails: C, then "Add the arrow": the focus ends on the page. The box is redrawn while the dialog keeps the page inert, and the dialog's close gives the focus back to the removed box; Cancel keeps it.
- `--motion`: 26 of 29; Publish (status) and deleting a flow (the next name) lose the focus too when the answer comes before the fade ends.
- Seen: after Fit the first boxes sit under the palette (`fit()` ignores it); the no-flow screen shows an empty palette frame.

## What keeps it in step?
- A bundle the flows screen adds goes into `SCREEN_BUNDLES`, in order. Scenarios find controls by their texts (`h.t()`), the `wsf-` classes, `data-node`, `data-edge` and `data-refocus`; a refactor that renames them changes the scenarios with it.
