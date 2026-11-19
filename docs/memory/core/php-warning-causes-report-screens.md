---
name: php-warning-causes-report-screens
description: PHP 8.5 warnings logged by the Visitor Report, Order Report, Edit Form List View and workspace rights: which cause is fixed where; the missing collection B row
---

## Why these four files?
- The owner's PHP 8.5 log (1 to 13 November 2026, not in the repository) counted `view_visitor_report.php`, `includes/fn/order_report.php`, `edit_form_list_view.php` and `includes/workspace/access.php` among the files that logged the most warnings (`error-reporting.md`).
- The log's line numbers are those of the owner's checkout on the day, so a cause may be gone since; read the file's history from 1 November before hunting.

## Visitor Report (`view_visitor_report.php`)?
- Gone since 482baf842 (2 November): a group of the table had no `visitors` key until a detail row was filed under it, so Show Detail printed "Undefined array key" and a `foreach` over null for every group without a visit on the page. Every group now starts with `'visitors' => array()`.
- No other cause was found in the code.

## Order Report (`includes/fn/order_report.php`)?
- Gone since 104df4ee2 (2 November): a first row whose group value was empty did not open its group, so `pg_order_report_add()` added into a missing bucket ("Undefined array key" for `count` and every figure). `pg_order_report_group_keys()` opens a group for the first row whatever its name (`commerce/order-report-screen.md`, `tests/order_report_totals_test.php`).

## Edit Form List View (`edit_form_list_view.php`)?
- While a Page Style of collection B is previewed, the screen reads the view's collection B row (`get_page_type_properties(..., 'b')`). A view never saved in that state has none, `db_item()` returns null, and Header, Layout, Footer and the advanced search layout read offsets of null.
- The row is now taken as `?: array()` and each field `?? ''`: the four editors open empty as before, without the warnings. `edit_form_item_view.php` has the same read for its layout.

## Workspace rights (`includes/workspace/access.php`)?
- The logged shape, key "archived_at", is gone since #8446: the GitHub link card (`ws_github_link_channels()`) passed the sidebar's short channel row, which has no `archived_at`, to `ws_can_post_channel()`; it now passes the channel's own row. Every other check takes the full `ws_rights()` array and a channel or task row read with `SELECT *`.

## How were they looked for without the log?
- PHPStan at level 2 with `checkMaybeUndefinedVariables` on the four files only (a scratch config beside `tools/phpstan.neon`); its two hits in the Visitor Report were false alarms (the range and the id are set on every path that reads them).
- Then `git log --since` per file, and the shapes the log names: an unchecked `db_item()` / `mysqli_fetch_assoc()` row, `+=` into a key not created, a `$_POST` key the form may not send.
