---
name: error-page-widget
description: The error page system widget: when it is shown, which block shows for 404, maintenance and other errors, its tokens, and the maintenance screen's title.
---

## When is the designed error page shown?
- `output_error()` records the message and the code (`pg_sw_error_context()`); `get_error_screen()` renders the designed page carrying an `error_page` widget that `pg_sw_error_page_id()` picks (see below) before the legacy `error` page type. Opened directly, the widget reads as "not found" and the page answers 404.

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

## Which page carrying the widget is the error page?
- `pg_sw_error_page_id()`: only pages whose folder's access type is `public` (`get_access_control_type()`), a page of the home page's design (`pg_sw_home_style_id()`) first, then the lowest `page_id` (`pg_sw_pick_viewable_page()`).
- Why: `get_error_screen()` draws the page without an access check; the first page by title was taken, so a design tried in the private Drafts folder whose error page sorted first became the whole site's error screen (#4477). Test: `tests/visitor_widget_page_pick_test.php`.
- The editor's info line still says "the first published page"; the rule above is what runs.
