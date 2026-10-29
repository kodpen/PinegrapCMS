---
name: js-bundles
description: Scripts minified from a list (pg_js_bundles(), tools/build_js.php): the bundles, how a screen prints one, src mode, the CI check, the test, the Workspace drawer's loader on record screens.
---

## Which scripts are built, and from what?
- `pg_js_bundles()` (`includes/fn/output.php`) maps each `.min.js` under `assets/js/` to its sources in run order. Today `workspace.min.js` joins thirteen Workspace scripts (`workspace_editor.js` first, `workspace.js` last); `workspace_palette`, `workspace_board_channel`, `workspace_tour`, `workspace_guest` and `workspace_side` are one-to-one. `workspace_side.min.js` (the Workspace list of the main menu) is loaded by `output_menu()` on every panel page of the team, so it stays small and apart from `workspace.min.js` (`tests/js_bundles_test.php`). `backend.min.js` is built from `backend.src.js` (the panel's own script, every panel screen; #2054: 502 KB to 219 KB, 112 to 52 KB gzipped); `output_header()` prints it with `pg_js_bundle_tags(..., false)`, not deferred, because the screens' inline scripts call it as they run. `style_designer.min.js` is built from `style_designer.src.js` (the visual designer, #3063: 4.7 MB to 2.0 MB, 1.03 MB to 0.52 MB gzipped), printed the same way by `includes/designer_screen.php`; the installer's starter step loads its one URL. The PHP that reads the designer's code (`pg_designer_i18n_keys()`, the block styles), `check_lang.php`, `check_bindings.php` and the tests read the source, which ships with the product; `clean_up.php` lists the old `style_designer.js`. The Workspace sources carry no `.src` in their names.
- After editing a source run `php tools/build_js.php` (needs Node with `npx`). terser is pinned in the tool (`TERSER`) so every machine writes the same bytes; change it only together with a rebuild of every bundle. CI runs `--check`: a `.min.js` that no longer matches its sources fails the PR (line endings do not count).

## How does a screen load a bundle?
- Never by a source name: `pg_js_bundle_tags('assets/js/workspace.min.js')` (`<script defer>` tags) or `pg_js_bundle_urls()`; each URL carries the file's mtime as `?v=`.
- `pg_js_bundle_files()` answers with the `.min.js` when the site runs minified (`ENVIRONMENT_SUFFIX` `min`), with the sources one by one in `src` mode, and with the sources too when the `.min.js` is missing.

## What does the test check?
- `tests/js_bundles_test.php`: every bundle and source exists, a source sits in one bundle only (a screen loading both would run it twice), every `assets/js/workspace*.js` is in a bundle (one written into its own `<script>` would be served unminified and drift), `workspace.js` runs last. A new Workspace script: add it to a bundle, or add a bundle to the list, and run the tool.

## Why is there no workspace.js when a record screen opens?
- Record screens with a Workspace button (`ws_record_button()`: order, product, contact, invoice, receipt, waybill, ERP account, calendar event, submitted form, incoming e-invoice) print `ws_record_loader()` (`includes/workspace/screen.php`) instead: the first click on a `[data-ws-record]` button fetches the bundle's files in order, then opens that record's drawer (`window.PGWsPendingRecord`). Code on those screens cannot assume `workspace.js` is there at page open.

Related: docs/memory/core/important-files-and-js-twins.md
