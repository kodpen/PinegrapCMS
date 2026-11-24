---
name: bug-hunt-areas-menus-navigation
description: The bug hunt of site menus and navigation (2026-11-24, hunt 36): the faults fixed, what was left for a decision, what was read clean
---

## Which area was hunted?
- Hunt 36, menus-navigation: the classic `<menu>` and `<menu_sequence>` regions (`get_menu_content()`, `get_menu_sequence()`, `pg_menu_tree()`), the Menu and Menu Sequence widgets (`includes/fn/widgets_menu.php`, `widgets_lists_menu.php`), the Smart active state pass (`pg_apply_smart_active()`), the menu items screen's Save Order (`pg_panel_sort_menu_items()`, `pg_menu_item_sort_plan()`), the menu item form's address check.
- Asked for: a hidden, members-only, unpublished or deleted page never shown as a link; the active mark on nested items and on a translated page; the order after a drag saved once; a very deep or long menu on a phone; an outside link's target and rel.

## What did the hunt's PR fix?
- In progress.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/data/menu-tree-queries.md, docs/memory/designer/menu-widget.md
