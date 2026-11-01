---
name: workspace-flows-canvas-harness
description: Browser harness of the Flows canvas (tests/js/flows_canvas.js, #3162, by hand, not in CI): what is real, how to run it, the scenarios, the result, touch emulation
---

## What is it?
- `tests/js/flows_canvas.js` serves workspace_flows.php as the panel prints it (Bootstrap 6 CSS, `pg_bs6_bridge.js`, `pg_ui.js`, backend.src.css, `#ws-config`, the `SCREEN_BUNDLES` from their sources, with `--min` the built ones) and drives headless Chromium over the DevTools protocol through a pipe. Node built-ins only, no packages.
- workspace.js runs for real (its kit, the `#ws-ask` dialog, toasts, menus); only api.php stands in: `flows_canvas_store.js`, fixtures draft (default), problems, threads, big, empty; the server's words, 409 on `draft_rev`, start tokens.
- `flows_canvas_server.php`, one PHP process: the texts `ws_screen_config()` merges, `pg_js_bundles()`, the part of `ws_flow_js_config()` that reads no database; every graph saved goes through `ws_flow_graph_normalize()` and `ws_flow_graph_check()`, so the problems are the server's (`ws_flow_problems()` reads the database).
- `flows_canvas_page.js`: the `window.PGHarness` probes; toasts kept, keepalive requests marked by a header. A browser context and a store per scenario.
- The screen without `?flow=` shows the flow list (#3172), not a flow: `h.open()` opens the list and clicks its first row, `h.open('?flow=8')` opens by address, `h.openList()` stays on the list; another flow is opened by the page's back arrow (`#ws-view-back`) and its row (`openOther()`). The list is `.wsf-flow-row .wsf-flow-open`, drawn again only while it is shown.

## How is it run?
- `node tests/js/flows_canvas.js [filter] [--min] [--motion] [--dark] [--shots=<dir>] [--verbose]`; `PG_CHROMIUM` and `PG_PHP` name the binaries. About 75 s. Exit 0 all pass, 1 a failure, 2 could not run.
- Reduced motion by default; `--motion` keeps fades. `--shots` pictures each end state (light, or `--dark`).

## What do the scenarios cover?
- 30 scenarios (`flows_canvas_scenarios.js`): boxes at their places and sizes, arrows port to port, zoom, fit, pinch, pan (drag, wheel, Space, middle button), select, Tab order, arrow keys, drag on the grid, Esc, connect by port, C and the side panel, outcomes, Delete, undo, data threads (drag to bind, toggle kept), outline, phone, autosave after 1.5 s, 409, keepalive on hide and leave, the 60 000-byte limit, Ctrl+S, blur, Retry, back to the list for another flow; palette and drop, problems, Publish, read-only, ⋯ Share and Delete, no flow, New flow, Run and its token, runs list, polling.

## What does it give?
- 2026-11-01: 30 of 30, from sources and with `--min` (Chromium 153, Linux; Chrome 156, Windows 11 after #4418). The C › "Add the arrow" focus and the double keepalive on leave pass since #4322.
- Before #3983 only 4 of 30 passed: the harness still waited for the first flow to open by itself (adapted in #4316).
- Seen: after Fit the first boxes sit under the palette (`fit()` ignores it); the no-flow screen shows an empty palette frame.

## Why does a scenario switch touch emulation itself?
- Touch emulation turns `(pointer: coarse)` on: the panel's tap targets grow to `--pg-tap` (44px) and the canvas moves a few px. `page.touches()` on a page that is not a touch screen turns it on only for the gesture, so a measure taken before and after it is in another layout. A scenario that compares positions around a gesture calls `h.touchScreen(true)` before its first measure and `h.touchScreen(false)` after its last (pinch, #4418: on Windows the pinch point moved 3 px).
- A profile folder Chrome still holds at the end (Windows, EPERM) is a warning on stderr; the exit code stays the result's.

## What keeps it in step?
- A bundle the flows screen adds goes into `SCREEN_BUNDLES`, in order (`pg_graph_canvas.min.js` before `workspace_flow.min.js`). Scenarios find controls by their texts (`h.t()`), the `pgc-` and `wsf-` classes, `data-node`, `data-edge` and `data-refocus`; a refactor that renames them changes the scenarios with it.
