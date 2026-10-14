---
name: functions-php-split-rules
description: Why functions.php became a manifest over includes/fn/ modules and the rules for adding functions, paths, PHPMailer use, get_file.php copies and line references.
---

## What is functions.php now?
- It had reached 3 MB / 63,000 lines / 782 functions: not openable in an FTP editor, unreadable diffs, a half-finished upload took the site down. Plan: `docs/_plan_functions_bolme.md` (Step 2a, eager manifest).
- The split was MECHANICAL: a tokenizer cut every top-level function (with its preceding comment) verbatim into a module, order kept, no body changed.
- `functions.php` is now a ~70-line manifest: two old `require`s, the `PG_FUNCTIONS_DIR` constant and 24 `require_once`. No classes, no namespaces, no lazy loading; none of the 313 entry points was touched.
- Module list: `docs/memory/core/fn-module-map.md`.

## Rules for writing code in modules
- `dirname(__FILE__)` / `__DIR__` in a module is NOT the software directory; moved code uses `PG_FUNCTIONS_DIR` (106 places). For a path in a new module function use `PG_FUNCTIONS_DIR . '/data/temp/...'`. Root files and `init.php` keep `dirname(__FILE__)`.
- A new function is written into the module matching its topic, NOT into `functions.php` (code added there works but regrows the file). Module gate: `if (!defined('PG_FUNCTIONS_DIR')) { exit; }`.
- `use PHPMailer\...` is valid only in `mail.php`. It used to be file-scoped, so every unqualified `Exception` after line 49,558 resolved to `PHPMailer\PHPMailer\Exception`; after the split `pg_purge_caches()`'s `catch (Exception)` catches global `\Exception` for the first time (behaviour fix; other places already wrote `\Exception`).
- Module order matters only for non-function statements (polyfill, PHPMailer `require` + `use`, tour constant, image-id global); functions are hoisted at compile time so they call each other in any order.
- `functions.php:NNNNN` line references in docs predate the split; search by function name.

## What about get_file.php copies?
- `get_file.php` does not load `functions.php`, so it keeps copies of functions (18 before 2026.4.8; the split did not change that).
- Since 2026.4.8 the folder access functions (`get_access_control_type`, `check_view_access`, `check_edit_access`, `pg_folder_edit_access`, `check_private_access`) live as a single copy in `includes/authentication.php`; `get_file.php` has 14 functions left, 11 of them deliberate copies. List and reason: `docs/_get_file_kopyalar.md`; `tools/check_copies.php` (in CI) `FAIL`s on a new copy not on the list.
- When touching shared code obey the contract in the `includes/authentication.php` header: only `db/db_value/db_item/db_items/escape` + PHP; no `lang()` and no `output_error()`; a `get_file.php`-specific function is called via `function_exists()`.

## How was the split verified?
- Three gates of `docs/_plan_functions_bolme.md` section 5 passed: function inventory (801 user functions, signature-exact), `php -l` (25 files), byte count. The fourth gate (smoke test of the 313 entry points) must be run on the dev site.
