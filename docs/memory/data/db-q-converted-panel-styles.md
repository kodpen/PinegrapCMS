---
name: db-q-converted-panel-styles
description: Calendar location, calendar event copy, ad, login and common region, style, design file, menu item, field and file screens converted to db_q(): PR, special cases, what stayed raw and why, guard test.
---

## Which files are converted, and what stayed raw?
- PR #2338: `edit_calendar_event_location.php` 6, `duplicate_calendar_event.php` 6, `edit_ad.php` 5, `view_ads.php` 1, `edit_login_region.php` 4, `edit_common_region.php` 3, `duplicate_style.php` 4, `edit_design_file.php` 5, `view_menu_items.php` 4, `add_menu_item.php` 4, `view_fields.php` 2, `edit_files.php` 4 calls with values, `(string)` where the SQL quoted. `view_styles.php` has no query with values.
- Values written unescaped into quotes (`$user['id']`, `$new_calendar_event_id`, `$calendar['id']`, the exception's recurrence number, `$new_sort_order`, `$ad_region['id']`, `$menu_item['id']`, the copied style's cells and `social_networking_position`) are bound as `(string)`, now escaped, same text for an id or a fixed value.
- `duplicate_style.php` wrote `NULL` or the quoted tree JSON through a ternary: the value is now `null` or `(string)` behind one `?`, and `pg_db_bind()` writes `NULL` for null, so both texts are kept.
- Raw stay: the list queries completed by an interpolated filter or sort (`view_ads.php` `$sql_join` / `$where` / `$order_by`, `view_styles.php` `$order_by`, `view_fields.php` `$form_type_filter`), the region update (`$sql_content`) and design file update (`$sql_theme`) that carry escaped values, the form name read from the table named by `$form_settings_table` (a bare table name) and the calls without values.
- A query with no value of its own but an interpolated clause is not a `db_q()` call: `db_q($sql, array())` would still read any `?` in that clause as a placeholder.
- `tests/db_q_regions_styles_menus_test.php` guards all thirteen: no escaped value in a `db_q()` SQL, no value in a raw query outside the clauses above, one value per `?`, and the NULL / JSON tree bind.

Related: docs/memory/data/db-q-converted-files.md (conversion rules), docs/memory/data/db-q-placeholder-helper.md
