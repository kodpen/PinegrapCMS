---
name: db-q-converted-files
description: Content files converted to db_q() (get_page_content, content, seo, get_page): PR, how values are passed, what stayed raw and why, guard tests; the conversion rules.
---

## Which files are converted, and what stayed raw?
- `get_page_content.php` (PR #2159): its 24 queries with values; values are passed `(string)` where the old SQL quoted them, so the SQL text stays the same (`escape(null)` gave `''`, a bound null would be `NULL`). The two watcher queries completed by `pg_comments_thread_sql()` stay raw: that suffix is escaped text and may hold a `?`. `tests/db_q_get_page_content_test.php` fails on an escaped value back in its query text.
- `includes/fn/content.php` (PR #2169): its 23 queries with values, `(string)` as above. Raw stay the two queries without values and those completed by a clause built with escaped values (`$where`, `$sql_where`, `$sql_comparison` of the product search), which may hold a `?`. `tests/db_q_content_test.php` guards it.
- `includes/fn/seo.php` (PR #2189): its 35 calls with values (visitor and article-view counters, visitor tracking, tag cloud, sitemap hash, perf monitor), `(string)` where the SQL quoted, `(int)` where it wrote a bare number (LIMIT, OFFSET, cursors). A clause that held an escaped value now holds `?` with its value added to the list (user agent, landing page name, perf entity type); `perf_stats` keeps its PHP-int counters inline. Raw stay the calls without values, the content backfill (`$sql_page_id` / `$sql_page_name` carry escaped names) and the integer IN lists (`$id_list`, `$page_list`, `$where`). Its `db()` / `db_value()` calls with `e()` are untouched. `tests/db_q_seo_test.php` guards it.
- `get_page.php` (PR #2227): its 29 queries with values (page lookup, membership check, the RSS and Google feeds of form list views, calendars and catalogs, the iCalendar export), `(string)` as above. The two calendar OR lists are built as `?` with a value list beside them. Raw stay the calls without values, the home page lookup (integer list `$sql_not_binned`) and the two submitted form lists of the form list view feed, completed by `$sql_where` / `$sql_filters` with escaped filter values. `tests/db_q_get_page_test.php` guards it.

- Commerce files (`includes/fn/ecommerce.php`, `shopping_cart.php`, `submit_order.php`, `includes/fn/forms.php`): `db-q-converted-commerce.md`.
- Catalog, photo gallery and custom form submit (`get_catalog.php`, `get_photo_gallery.php`, `custom_form.php`): `db-q-converted-catalog-forms.md`.
- Sign-in, access checks, contact merge and designer asset sync (`includes/fn/auth.php`, `contacts.php`, `designer.php`, the `forms.php` clauses): `db-q-converted-access.md`.
- User and page screens (`edit_user.php`, `add_user.php`, `import_users.php`, `edit_pages.php`, `edit_page.php`): `db-q-converted-panel.md`.
- Users list, account profile, comments, search, sitemap: `db-q-converted-comments-search.md`.
- Front-end files (`get_shopping_cart.php`, `get_form_item_view.php`, `get_view_order_screen_content.php`, `email_preferences.php`, `add_or_remove_watcher.php`, `affiliate_sign_up_form.php`): `db-q-converted-front.md`.

## What is the rule for a query that stays raw?
- A query without values, or one completed by a clause built elsewhere with escaped values (it may hold a `?`, which `db_q()` would take as a placeholder), stays `mysqli_query()`. An integer-only IN list may stay too. Each file's test lists those clause variables by name.
- Values are passed `(string)` where the old SQL quoted them and `(int)` where it wrote a bare number, so the SQL text sent stays the same. A value that arrived already escaped (`escape()`, `e()`, `mysqli_real_escape_string()`) goes in unwrapped, or it would be escaped twice.
- `router.php` runs before `functions.php` is loaded, so `db_q()` does not exist there; it keeps its raw queries, as `get_file.php` keeps its own db copies.

Related: docs/memory/data/db-q-placeholder-helper.md, docs/memory/plans/refactor-candidates.md
