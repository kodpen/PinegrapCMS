---
name: error-page-widget
description: The error page system widget: when it is shown, which block shows for 404, maintenance and other errors, its tokens, and the maintenance screen's title.
---

## When is the designed error page shown?
- `output_error()` records the message and the code (`pg_sw_error_context()`); `get_error_screen()` renders the first designed page carrying an `error_page` widget (`pg_sw_error_page_id()`) before the legacy `error` page type. Opened directly, the widget reads as "not found" and the page answers 404.

## Which page carrying the widget is chosen?
- `pg_sw_error_page_id()` → `pg_sw_error_page_pick()`: only a page in a **public** folder (`get_access_control_type()`), since `get_error_screen()` draws it for every visitor without an access check; among those a page of the home page's design (`pg_sw_home_style_id()`, the published design) first, else the lowest page id. No such page: the legacy `error` page.
- Why: the list came in title order with no folder check, so a design tried out as a draft (Drafts, a private folder) whose error page title sorted first became every error screen of the live site (issue #4477, PR #4530). The widget's panel note says the rule. Test: `tests/system_page_pick_test.php`.

## Which block shows for which error?
- Flags from `pg_sw_error_flags($code, $maintenance_block)` (`includes/fn/widgets_error.php`): `is_not_found` (404), `is_maintenance` (503; maintenance mode is the only sender), `is_other_error` (any other error), `has_error_code` (code 400 and up).
- A page with an `is_maintenance` block shows maintenance only there. A page designed before PR #692 has no such block, so for it `is_other_error` still covers maintenance (its "503", "Something Went Wrong" and the maintenance sentence); `pg_sw_error_tree_shows()` looks for the block.
- The starter tree (`case 'error_page'` in `style_designer.js`) has three blocks: Not Found, Maintenance (heading "Down for Maintenance", the software's sentence bound to `__error_message`, no links: every page answers with this screen until it ends) and Other Error. The canvas shows all three, with the generic message sample in both bound paragraphs.

## What is the maintenance screen's title?
- `get_page_content.php` asks `pg_sw_error_title()` before it writes `<title>`: "Down for Maintenance" for a 503, otherwise the page's own title, on the designed and the legacy error page alike. The starter sites title their error page "Page Not Found", which the maintenance screen showed before. 403 and 410 keep the page's title.

## Which tokens does it fill?
- `__error_code`, `__error_message` (plain text, markup dropped), `__requested_url` (the visitor's address; in the editor's Preview and the search index pass the page's own, `pg_sw_error_requested_url()`), `__site_name`, `__home_url`, `__back_url` (the link drops without a same-site referrer).
- Tests: `tests/error_page_test.php`.

Related: docs/memory/designer/account-widget-forms.md
