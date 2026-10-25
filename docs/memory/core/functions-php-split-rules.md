---
name: functions-php-split-rules
description: Why functions.php became a manifest over includes/fn/ modules (2026-09-12), what it loads, and the rules for adding a function or a module, paths, PHPMailer use and old line references.
---

## What is functions.php now?
- It had reached 3 MB / 63,000 lines / 782 functions: not openable in an FTP editor, unreadable diffs, a half-finished upload took the site down. Split on 2026-09-12 (plan `docs/_plan_functions_bolme.md`, Step 2a, eager manifest; the plan is not in the repository).
- The split was MECHANICAL: a tokenizer cut every top-level function (with its preceding comment) verbatim into a module, order kept, no body changed.
- `functions.php` is now a manifest: the `PG_FUNCTIONS_DIR` constant, four files shared with other entry points (`includes/authentication.php`, `includes/server_config.php`, `includes/image_engine.php`, `includes/http_range.php`; `get-file-shared-code.md`) and one `require_once` per module. No classes, no namespaces, no lazy loading.
- A module may instead be loaded by the few places that need it: `includes/fn/search_console.php` (its screen, its job, widget 31, `welcome.php`).
- Module list: `docs/memory/core/fn-module-map.md`.

## How is code written in a module?
- A new function goes into the module matching its topic, NOT into `functions.php` (code added there works but regrows the file). A new module gets its line in the manifest (or a `require_once` where it is needed) and the gate `if (!defined('PG_FUNCTIONS_DIR')) { exit; }`.
- `dirname(__FILE__)` / `__DIR__` in a module is NOT the software directory; moved code says `PG_FUNCTIONS_DIR` wherever it said either. For a path in a new module function use `PG_FUNCTIONS_DIR . '/data/temp/...'`. Root files and `init.php` keep `dirname(__FILE__)`.
- `use PHPMailer\...` is valid only in `mail.php`. It used to be file-scoped, so every unqualified `Exception` after line 49,558 resolved to `PHPMailer\PHPMailer\Exception`; in any other module an unqualified `Exception` is the global `\Exception` (after the split `pg_purge_caches()`'s `catch (Exception)` caught it for the first time).
- Module order matters only for non-function statements (`core.php` the `array_key_last()` polyfill, `mail.php` the PHPMailer `require` + `use`, `tour.php` its version constant, `content.php` the image-id global); functions are hoisted at compile time and call each other in any order.
- `functions.php:NNNNN` line references in older notes predate the split; search by function name.

## How was the split verified?
- Three gates of the plan's section 5 passed: function inventory (801 user functions, signature-exact), `php -l` (25 files), byte count. The fourth gate (smoke test of the 313 entry points) was left to the dev site.
