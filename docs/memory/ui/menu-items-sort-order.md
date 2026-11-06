---
name: menu-items-sort-order
description: The menu screen's Save Order (sort_menu_items): the posted tree is checked against the menu as it is now before any row is written; stale or looping trees are refused
---

## What does Save Order write?
- `view_menu_items.php` posts every group of the drag-and-drop tree (`parent_id`, `order`) to `api.php` action `sort_menu_items`, `pg_panel_sort_menu_items()` (`includes/panel/content.php`). Each placed item gets its group's parent and its place (1, 2, 3...) in it.

## What is checked first?
- `pg_menu_item_sort_plan($groups, $parent_of)` (`includes/forms/menu_item_form.php`, pure, `tests/menu_item_form_test.php`) against the menu's items read in one query. Refused (null, "The menu has changed since this page was opened...", nothing written): a group parent that is neither 0 nor an item of the menu now, an item placed twice, a move that puts an item under itself or one of its own sub-items. Items no longer in the menu are skipped and take no place.

## Why?
- The action wrote any posted parent. A second editor deleting an item while the screen was open, then a drop under that item, stored a parent that no longer exists: the moved items vanished from the site and from the menu screen (it walks from the top level), with no way back but the database. A forged post could also store a cycle or another menu's item as parent. The item screen and the external API already checked the parent (`pg_menu_item_parent_allowed()`, `api_menu_items_update()`).
