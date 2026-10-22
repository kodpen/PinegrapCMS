---
name: important-files-and-js-twins
description: Key product files and their roles; which scripts have a .src/.min twin chosen by ENVIRONMENT_SUFFIX (edit both) and which are served as written; where front-end JS lives.
---

## Which files are the important ones?
Paths are relative to pinegrap/.
- `init.php`: bootstrap (DB, constants, session, error reporting). `router.php`: URL routing.
- `functions.php`: the manifest that loads the `includes/fn/` modules (`functions-php-split-rules.md`). `liveform.class.php`: form validation and session handling.
- `api.php`: the panel's JSON endpoint (`panel-action-table.md`). `settings_pane.php`: the settings dialog's endpoint.
- `view_folders.php`: File Manager. `view_files.php`, `view_design_files.php`: file and design file lists. `find_and_replace.php`: bulk find-replace (rewritten).
- `data/config.php`: live config (defines; `init-constants-and-config-file.md`). `data/config(default).php`: default config template. `edit_config.php` is gone.
- `includes/local/tr.json`: Turkish translation file.
- `install/index.php`: install screen; delegates upgrades to `includes/migrations/runner.php`. `includes/migrations/`: version history (`versions.php`), one schema file per version, `legacy.php`, `runner.php`.

## Which scripts have twins, and why does editing only `.src.js` change nothing?
- `ENVIRONMENT_SUFFIX` (`init.php`: `src` when `ENVIRONMENT` is `development`, else `min`; `min` on most installs) picks the file wherever a `<script src>` names it: `frontend.<suffix>.js`, `chat_site.<suffix>.js`, `assets/js/chat_backend.<suffix>.js`, `assets/lib/dropzone/dropzone.<suffix>.js` (and `.css`), `jquery-ui.theme.<suffix>.css`.
- Edit both twins. Editing only `.src.js` is silent: the code is right, the file is never read. Symptom: "I changed the code, the page still behaves the old way".
- `add_to_cart.src.js` / `add_to_cart.min.js` are a twin pair that no `<script>` in the code prints today; keep the two in step if you touch them.
- The criterion is not the file name: it is whether the `<script src>` line that prints the file contains `ENVIRONMENT_SUFFIX`. Served as written, with no twin: `assets/js/backend.src.js` (despite its name), `style_designer.js`, `page_designer.js`, `erp_invoice_editor.js`, `offer_editor.js`.
- The Workspace scripts are minified from a list instead (`pg_js_bundles()`, `js-bundles.md`).

## Where does JS that runs on the front end live?
- Two places: panel screens load `backend.src.js`; the page a visitor sees loads `frontend.<suffix>.js`. Each has its own keyboard-shortcut block and its own toolbar hooks.
- When moving a button, search both. Looking only at `backend.src.js` silently dropped Ctrl+G once.
