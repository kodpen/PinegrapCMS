---
name: bug-hunt-areas-menus-navigation
description: The bug hunt of site menus and navigation (2026-11-24, hunt 36): the faults fixed, what was left for a decision, what was read clean
---

## Which area was hunted?
- Hunt 36, menus-navigation: the classic `<menu>` and `<menu_sequence>` regions (`get_menu_content()`, `get_menu_sequence()`, `pg_menu_tree()`), the Menu and Menu Sequence widgets (`includes/fn/widgets_menu.php`, `widgets_lists_menu.php`), the Smart active state pass (`pg_apply_smart_active()`), the menu items screen's Save Order (`pg_panel_sort_menu_items()`, `pg_menu_item_sort_plan()`), the menu item form's address check.
- Asked for: a hidden, members-only, unpublished or deleted page never shown as a link; the active mark on nested items and on a translated page; the order after a drag saved once; a very deep or long menu on a phone; an outside link's target and rel.

## What did the hunt's PR fix (#11428)?
- An item linked to a page sent to the Recycle Bin or kept in the drafts folder stayed a link to a page nobody may open, unless the item's Enable Security was on; an item whose page was deleted stayed as a dead row (`javascript:void(0)` or `#`). Now `pg_menu_item_shown()` (`includes/fn/content.php`) decides for the classic menu and the Menu widget: a deleted page's item is left out, an offline page (`pg_menu_page_offline()`) is checked like security on, so a manager still sees it.
- The classic `<menu_sequence>` and the Menu Sequence widget gave a previous or next link to an offline page; such a page is no step now.
- A "New Window" item had `target="_blank"` with no `rel`: both menus add `rel="noopener"` (`pg_menu_link_rel()`); the widget stamps `^^__menu_item_rel^^` on an item's link as it stamps the target, and drops an empty one.
- The classic menu's current item was read without an order; it is the first by id (`ORDER BY id LIMIT 1`), as the widget marks it.
- Tests: `tests/menu_tree_test.php`, `menu_widget_test.php`, `menu_sequence_widget_test.php`.

## What was left for a decision?
- A parent item with sub-items is a dropdown toggle in the widget's starter, so its own page has no link there (documented in `designer/menu-widget.md`); whether the dropdown should carry a link to the parent's page is a product question.
- The widget's current item is the first item by id linking to the page; when that one is hidden by security, a second visible item to the same page gets no mark.

## What was read clean?
- Save Order on the menu items screen: one button, disabled while the request runs, reloads on success; `pg_menu_item_sort_plan()` refuses an item twice, a parent outside the menu and a loop.
- Translated pages: item names through `pg_tr_text()`, links prefixed by `pg_tr_rewrite_links()`, the Smart active state compares the path without the language directory.
- An item's URL: `pg_menu_item_url_problem()` allows only http, https, mailto, tel and site paths, in the form and the API.
- A deep menu: `pg_menu_tree_flatten()` and the widget's walks stop on parent loops; the starter navbar collapses below `lg`.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/data/menu-tree-queries.md, docs/memory/designer/menu-widget.md
