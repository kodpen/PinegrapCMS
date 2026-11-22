---
name: bug-hunt-areas-backend-sweep
description: The owner's whole-backend sweep of 2026-11-22: how every panel screen was loaded and read, what it found and fixed (#11051), what read clean
---

## What was asked?
- Owner (2026-11-22, in the session): a bug hunt across the whole software, panels included, from A to Z; simple faults fixed at once in PRs, panel shifts and breakages opened as `bug` issues.

## How was it swept?
- A sandbox (`tools/setup_sandbox.sh`, `tools/screenshots/seed.php`) and a Playwright crawl: every root file calling `pg_page_shell()` (302, no `delete_`/`duplicate_`/`remove_`/`export_`), plus the first `?id=` link of each list screen (32 edit screens with a real record), signed in as `admin`, at 1440 px and at 390 px.
- Per screen: the HTTP status, PHP error text in the page, script errors, failed same-origin requests, a page that scrolls sideways and its outermost element past the screen.
- PHP errors are not in the server log: `includes/fn/errors.php` writes them to `data/temp/php_errors.log`; the crawl added none.
- PHPStan at level 1 (`variable.undefined` is legacy noise, 69,650 lines): only its `arguments.count` lines were read.

## What did it find and fix? (#11051)
- Subscriptions: the price change notice was never written or mailed: `notice_read` handed the id to `db_item()`, which binds nothing; now `db_q()`.
- Booking widget: each service read "{var:1} min": `lang()` dropped the length given as a second argument.
- Supplier feed at 390 px: the Sign-in choice ran 94 px past the screen; `.pg-seg.pg-seg-wrap` wraps it inside its frame (the shared `.pg-seg` and the area strips unchanged).
- `tests/single_argument_helpers_test.php` reads the product for `lang()`, `db_item()`, `db_value()`, `db_values()` called with a second argument.

## What read clean, or is no fault?
- No PHP warning, no other script error, no other sideways scroll at 1440 or 390 px.
- An edit screen opened without an id answers 404: that is its "not found" page.
- `edit_system_style.php?classic=1` throws "M.documentReady is not a function": the starter sites' third-party `parallax.js` calls a method before defining it when jQuery is already ready; content, not product code.
- `logout.php` hands `get_logout_screen()` an argument it ignores (it reads the content itself); `Imagick::getConfigureOptions($pattern)` takes a pattern on current Imagick.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/ui/panel-screenshots.md, docs/memory/workflow/bug-hunt-areas-5.md
