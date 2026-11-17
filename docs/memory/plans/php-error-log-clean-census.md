---
name: php-error-log-clean-census
description: PHP error log census (plan php-error-log-clean, EL1): what was read, totals, how shapes were checked, the fatal errors' verdicts; shapes by file in parts 2 to 4
---

## What was read?
- Read on 2026-11-17, streamed read-only: PHP's own `error.log` at the main checkout's root (15 Oct to 17 Nov 2026) and the site's `data/temp/php_errors.log` (JSON lines, 10 Oct to 14 Nov). Neither log enters the repository.
- Only lines whose file is under the main checkout's `pinegrap/` count; 33 lines from `.claude/worktrees` and 142,910 lines of other projects and scratch folders are left out.
- Counts are log lines, not requests: a warning the site's handler writes to `php_errors.log` often stands in `error.log` too.

## What are the totals?
- 14,782 lines (13,461 from `error.log`, 1,321 from `php_errors.log`) in 125 files and 339 shapes (one message per file, numbers and paths folded).
- 14,656 warnings, 93 fatal errors, 33 deprecations.
- 73 files hold only shapes that are gone or not faults (part 4); 52 still hold a shape that may still be produced (parts 2 and 3).

## How was each shape checked?
- The commit the main checkout ran at the time of the line comes from its `HEAD` reflog; the logged line's text is read there and looked up in `origin/development` (`f1586738ca`).
- **gone #n**: the text is no longer there; #n is the PR whose commit removed it. **gone** with a reason: read by hand.
- **still**: the line and the 30 lines above it are unchanged. **set since**: an undefined variable that now has one more assignment, likely gone. **check**: the line is there but the code above it changed; EL5 reads it first.
- **EL4**: one of the four files EL4 still holds (`view_visitor_report.php`, `includes/workspace/access.php`, `includes/fn/order_report.php`, `edit_form_list_view.php`). EL4a's files (#9657) are gone.

## Which fatal errors were real faults?
- Still on development, with a `bug` issue: opening `view_folder_and_files_f.php` by URL (`PG_FUNCTIONS_DIR` undefined), #10052; the dashboard's System Status permission walk outrunning 300 s on a slow disk, #10053.
- Gone: product group recursion in `get_product_groups()` (#5551), `get_style()` recursion out of memory (#6533), ERP profit period loop in `includes/erp/profit.php` (#5740), `$kept` reused as a closure in `edit_erp_expense.php`, `$audit_view_source` reused in `view_audit_log.php`, array request values in `view_parasut_inbox.php`, `allow_or_disallow_new_comments.php`, `edit_email_campaign.php` and `get_cross_sell_items.php`, `count()` of null in `product_attribute_form.php`.
- Not faults: eight "Cannot redeclare" on 3 Nov (a scratch script compiled every file in one process); ten design templates included alone from the command line on 1 Nov; `pg_label_account()` called by a scratch script; five "Call to undefined function" while a pull had updated a file before its module (each function exists and loads on development).
- `init.php` 300 s at `session_start()`: requests waiting on a session held by one of the long requests above, not a fault of its own.

## What do EL5's steps take?
- The **still**, **check** and **set since** shapes of parts 2 and 3, by family: undefined variables in the classic order pages, missing keys in the theme CSS generator and designer style arrays, `$route['params']` in the API, missing page properties, float-to-int casts in ERP money.
