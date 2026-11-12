---
name: menu-item-shared-form
description: The menu item create and edit screens share includes/forms/menu_item_form.php
---

## Where is the menu item form?
- `includes/forms/menu_item_form.php` (gate `PG_FUNCTIONS_DIR`). `add_menu_item.php` and `edit_menu_item.php` stay at the root and `require_once` it.
- `pg_menu_item_form_cards($liveform, $form)` prints the form column (Menu Item, Link Menu Item To, Classes cards) and the sticky status column (`ui/edit-screen-wide-layout.md`); `pg_menu_item_form_layout($cards, $buttons)` wraps them with the sticky save bar. `$form` keys: `parent_id_options`, `extra` (after the parent: edit's Position select), `menu_name`, `menu_url` (escaped), `status` (extra Summary rows).
- The Summary shows the menu (linked to its items screen; the old "Menu: …" line above the form left), what the item links to (the page field's text, else the URL, else "Nothing", kept live by the screen script) and the link target; edit adds `pg_menu_item_form_children_status()`, the count of items directly under it, because Delete removes them too.

## How is a stored page kept?
- Page is a page picker field (`picker-page-fields-screens.md`): a stored page in an archived folder or one a role 3 user cannot edit is shown marked and posted unchanged. Before, the select dropped it and a plain Save wrote `link_page_id = ''`.
- `pg_menu_item_form_link($post)` returns `link_page_id` / `link_url`: a chosen page wins and blanks the URL; the URL is trimmed.
- `pg_menu_item_form_last_sort_order($menu_id, $parent_id)` is the sort order that puts an item last on its level (1 on an empty level); create uses it for every new item, edit when the parent changed.
- `pg_menu_item_form_touch_menu($menu_id, $user_id)` stamps the menu's last modified columns.

## Which URLs does the URL box refuse?
- `pg_menu_item_url_problem($url)` returns '' or the message: spaces and control characters (`[\x00-\x20\x7f]`, after trim), any scheme but http, https, mailto and tel, a leading `//`, any backslash. Paths, `?query` and `#anchor` pass.
- Why: the box stored what was posted and the classic site menu prints it as `href`, so a role-3 user with rights to a menu could plant `javascript:` (or ` java<TAB>script:`) that ran when an administrator clicked the item. The external API already refused these.
- Both screens call it (when no page is chosen) with `mark_error('link_url', …)` before `check_form_errors()`; `api_menu_link_url_or_fail()` (`includes/api/resources/menus.php`) loads this file and fails with the same message, so the rule cannot drift. Only new saves are checked; stored rows are not rewritten.
- No other schemes (sms:, skype:): adding one is a product question.

## Which parent and target does a save accept?
- `pg_menu_item_parent_allowed($menu_id, $item_id, $parent, $lookup)`: 0, or an item of the same menu outside the item's subtree (walks up, max 1000 steps). A posted child as parent stored a cycle that hid the subtree. `pg_menu_item_form_link_target()` keeps only `New Window`, else `Same Window`. Classes over 255 characters are refused.

## What stays in the screens?
- Create: the menu lookup and role-3 check, the INSERT, redirects.
- Edit: the not-found guard, the record load and position prefill, the Position options, delete (recursive), the UPDATE and the reorder of the level.

## Why this pair?
- PR #2539. Measured: add 273 and edit 446 lines, about 198 shared, the largest one no open PR touched (product group, arrival date and zone were held). Tests: `tests/menu_item_form_test.php`.
