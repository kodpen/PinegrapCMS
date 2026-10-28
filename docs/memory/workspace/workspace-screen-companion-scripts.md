---
name: workspace-screen-companion-scripts
description: Why the Flows canvas and the channel Board tab wait for their own bundle (workspace_flow.js, workspace_board_channel.js) instead of racing ws_bootstrap; the "script did not load" fallback (#2516)
---

## Which scripts does a workspace screen load?
- `includes/workspace/screen.php` emits `workspace.min.js`, `workspace_palette.min.js` and, per mode, `workspace_board_channel.min.js` + `workspace_tour.min.js` (channels) or `workspace_flow.min.js` (flows), all `defer`. `workspace.js` calls `start()` as soon as it runs and sends `ws_bootstrap`; the companion bundle defines its global (`window.PGWsFlow`, `window.PGWsChannelBoard`) only when it has run.

## Why did Flows stay on "Loading…" on the first open?
- The browser runs defer scripts in order but turns the event loop between them: when `workspace_flow.min.js` (120 KB) was still downloading, the `ws_bootstrap` answer (~500 ms) was handled first, `window.PGWsFlow` was missing, and `start()` had no second try and no `else` (#2516). A reload served the bundle from the cache, so it ran before the answer. The race was lost exactly after a PR that changed only `workspace_flow.js` (its `?v=` changed, `workspace.min.js` stayed cached).

## How does the start wait now?
- `whenCompanion(name, callback, fallback)` in `workspace.js`: the global present → start; the document not yet `complete` → try again on `DOMContentLoaded` (every defer script has run by then) and on `load`; the document complete and the global still missing → the fallback, once. Flows waits for `PGWsFlow` and falls back to `t('script_missing')` ("A script of this screen did not load. Reload the page.") in the root instead of "Loading…"; channels wait for `PGWsChannelBoard` before `app.start()`, so `hasBoard()` sees the Board tab's script on the first draw, and open boardless when it never loads. `PGWsFlow.showAction` is only called from a started flows screen.
- The waits add nothing on a cached page (the global is there at once); the fallback shows only when a bundle is really missing.
