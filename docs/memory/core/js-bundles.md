---
name: js-bundles
description: Scripts minified from a list (pg_js_bundles(), tools/build_js.php): the bundles, how a screen prints one, src mode, the CI check, the test, the Workspace drawer's loader on record screens.
---

## Which scripts are built, and from what?
- `pg_js_bundles()` (`includes/fn/output.php`) maps each `.min.js` under `assets/js/` to its sources in run order. Today `workspace.min.js` joins thirteen Workspace scripts (`workspace_editor.js` first, `workspace.js` last); `workspace_palette`, `workspace_board_channel`, `workspace_tour`, `workspace_guest` and `workspace_side` are one-to-one. `workspace_side.min.js` (the Workspace list of the main menu) is loaded by `output_menu()` on every panel page of the team, so it stays small and apart from `workspace.min.js` (`tests/js_bundles_test.php`). The sources carry no `.src` in their names.
- After editing a source run `php tools/build_js.php` (needs Node with `npx`). terser is pinned in the tool (`TERSER`) so every machine writes the same bytes; change it only together with a rebuild of every bundle. CI runs `--check`: a `.min.js` that no longer matches its sources fails the PR (line endings do not count).

## How does a screen load a bundle?
- Never by a source name: `pg_js_bundle_tags('assets/js/workspace.min.js')` (`<script defer>` tags) or `pg_js_bundle_urls()`; each URL carries the file's mtime as `?v=`.
- `pg_js_bundle_files()` answers with the `.min.js` when the site runs minified (`ENVIRONMENT_SUFFIX` `min`), with the sources one by one in `src` mode, and with the sources too when the `.min.js` is missing.

## What does the test check?
- `tests/js_bundles_test.php`: every bundle and source exists, a source sits in one bundle only (a screen loading both would run it twice), every `assets/js/workspace*.js` is in a bundle (one written into its own `<script>` would be served unminified and drift), `workspace.js` runs last. A new Workspace script: add it to a bundle, or add a bundle to the list, and run the tool.

## Why is there no workspace.js when a record screen opens?
- Record screens with a Workspace button (`ws_record_button()`: order, product, contact, invoice, receipt, waybill, ERP account, calendar event, submitted form, incoming e-invoice) print `ws_record_loader()` (`includes/workspace/screen.php`) instead: the first click on a `[data-ws-record]` button fetches the bundle's files in order, then opens that record's drawer (`window.PGWsPendingRecord`). Code on those screens cannot assume `workspace.js` is there at page open.

Related: docs/memory/core/important-files-and-js-twins.md
