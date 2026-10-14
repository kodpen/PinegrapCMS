---
name: important-files-and-js-twins
description: Key product files and their roles, the .src.js vs .min.js rule (edit both; ENVIRONMENT_SUFFIX decides which is served) and where frontend JS lives.
---

## Which files are the important ones?
Paths are relative to pinegrap/.
- `init.php`: bootstrap (DB, constants, session, error reporting). `router.php`: URL routing.
- `functions.php`: all helper functions (~24000+ lines). `liveform.class.php`: form validation and session handling.
- `edit_config.php`: config editing UI. `find_and_replace.php`: bulk find-replace (rewritten).
- `view_files.php`: file management. `view_design_files.php`: design files management.
- `data/config.php`: live config (defines). `data/config(default).php`: default config template.
- `includes/local/tr.json`: Turkish translation file.
- `install/index.php`: install screen; delegates upgrades to `includes/migrations/runner.php`.
- `includes/migrations/`: version history (`versions.php`), one schema file per version, `legacy.php`, `runner.php`.

## Why does editing only a `.src.js` change nothing?
- The `ENVIRONMENT_SUFFIX` constant picks which file loads (`frontend.<suffix>.js`, `chat_backend.<suffix>.js`, `add_to_cart.<suffix>.js`, `assets/lib/dropzone/dropzone.<suffix>.js`); on most installs it is `min`.
- Editing only `.src.js` is silent: the code is right, the file is never read. Symptom: "I changed the code, the page still behaves the old way". Edit both twins.
- Files under `assets/js/` (`backend.src.js`, `style_designer.js`, `page_designer.js`...) have NO minified twin and are served directly, so the distinction is easy to miss.
- The criterion is not the file name: check whether the `<script src>` line that prints that file contains `ENVIRONMENT_SUFFIX`.

## Where does JS that runs on the front end live?
- Two places: panel screens load `backend.src.js`; the page a visitor sees loads `frontend.<suffix>.js`. Each has its own keyboard-shortcut block and its own toolbar hooks.
- When moving a button, search both. Looking only at `backend.src.js` silently dropped Ctrl+G once.
