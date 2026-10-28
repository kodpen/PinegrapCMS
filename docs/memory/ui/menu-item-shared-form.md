---
name: menu-item-shared-form
description: The menu item create and edit screens share includes/forms/menu_item_form.php
---

## Where is the menu item form?
- `includes/forms/menu_item_form.php` (gate `PG_FUNCTIONS_DIR`). `add_menu_item.php` and `edit_menu_item.php` stay at the root and `require_once` it.
- `pg_menu_item_form_card($liveform, $parent_id_options, $extra)` prints the Options card; `$extra` is printed after the classes (edit passes its Position select).
- `pg_menu_item_form_link($post)` returns `link_page_id` / `link_url`: a chosen page wins and blanks the URL.
- `pg_menu_item_form_last_sort_order($menu_id, $parent_id)` is the sort order that puts an item last on its level (1 on an empty level); create uses it for every new item, edit when the parent changed.
- `pg_menu_item_form_touch_menu($menu_id, $user_id)` stamps the menu's last modified columns.

## What stays in the screens?
- Create: the menu lookup and role-3 check, the INSERT, redirects.
- Edit: the not-found guard, the record load and position prefill, the Position options, delete (recursive), the UPDATE and the reorder of the level.

## Why this pair?
- PR #2539. Measured: add 273 and edit 446 lines, about 198 shared, the largest one no open PR touched (product group, arrival date and zone were held). Tests: `tests/menu_item_form_test.php`.
