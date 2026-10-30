---
name: menu-item-shared-form
description: The menu item create and edit screens share includes/forms/menu_item_form.php
---

## Where is the menu item form?
- `includes/forms/menu_item_form.php` (gate `PG_FUNCTIONS_DIR`). `add_menu_item.php` and `edit_menu_item.php` stay at the root and `require_once` it.
- `pg_menu_item_form_card($liveform, $parent_id_options, $extra, $keep_page_id)` prints the Options card; `$extra` is printed after the classes (edit passes its Position select).

## Why does edit pass a keep page id?
- The page picker leaves out pages in archived folders and, for role 3, folders the user cannot edit. A stored link to such a page opened as "-Select Page-", and a plain Save wrote `link_page_id = ''`, `link_url = ''`. Edit now passes the STORED `link_page_id` (read with the menu lookup, never from the post), so it stays listed, marked "[ARCHIVED]" in an archived folder. Add passes 0. Campaign profile edit does the same for its Custom Form and Body Page pickers (`pg_email_campaign_profile_form_cards(..., $keep_pages)`). Test: `tests/page_options_keep_test.php`.
- `pg_menu_item_form_link($post)` returns `link_page_id` / `link_url`: a chosen page wins and blanks the URL.
- `pg_menu_item_form_last_sort_order($menu_id, $parent_id)` is the sort order that puts an item last on its level (1 on an empty level); create uses it for every new item, edit when the parent changed.
- `pg_menu_item_form_touch_menu($menu_id, $user_id)` stamps the menu's last modified columns.

## What stays in the screens?
- Create: the menu lookup and role-3 check, the INSERT, redirects.
- Edit: the not-found guard, the record load and position prefill, the Position options, delete (recursive), the UPDATE and the reorder of the level.

## Why this pair?
- PR #2539. Measured: add 273 and edit 446 lines, about 198 shared, the largest one no open PR touched (product group, arrival date and zone were held). Tests: `tests/menu_item_form_test.php`.
