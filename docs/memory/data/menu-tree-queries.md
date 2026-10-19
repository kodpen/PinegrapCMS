---
name: menu-tree-queries
description: Classic menus (get_menu_content(), get_menu_sequence()) read a menu once per request through pg_menu_tree(): two queries, printing order, children counted across menus, what the output must keep
---

## How is a classic menu read?
- `pg_menu_tree($menu_id)` (`includes/fn/content.php`): every item of the menu in one query (`ORDER BY sort_order, id`, with the linked page's name and folder), then how many items sit under each of them in a second (`GROUP BY parent_id`, any menu). Kept in a static for the request, so a menu printed twice, or on every page a command-line render loops over, is read once. `pg_menu_tree_from_rows()` groups the rows by parent (pure, `tests/menu_tree_test.php`).
- `get_menu_content()` (the `<menu>` tag of classic pages and custom layouts) and `get_menu_sequence()` (`<menu_sequence>`, `includes/fn/designer.php`) walk the tree; neither runs a query per level or per item. PR #666.
- Before, every level was a query and every shown item asked `COUNT(*)` of its children. Sandbox, starter site, 139-item menu (2026-10-19): 168 `menu_items` queries per page view, now 3 (the current item's id in `get_page_content.php` plus the two); a page went from 257 to 92 queries, the catalog from 284 to 119, the express order from 278 to 113.

## What must the output keep?
- Byte for byte the old one: compared on the sandbox over every menu, the current item at the top, middle and end, edit mode, sub-menus drawn alone, the sequence and its second call, and a menu with ties, a hidden subtree, an orphan, an accordion and a child in another menu.
- Children are counted across menus and before security: an item whose only children belong to another menu, or are all hidden from the visitor, still gets the `parent` class and an empty `<ul></ul>`, and an accordion gives it no link.
- Ties in `sort_order` print by id (the per-level query left them to the database, which gave them by id).
- Security (`check_view_access()`) is asked per item while drawing, not kept in the tree; the sequence does not ask it.
- `get_menu_sequence()` keeps its global `$page_names_in_menu_sequence`: a second call in the same request adds only pages not seen yet, so a second `<menu_sequence>` of the same menu prints nothing.

## Where does the pattern remain?
- `view_menu_items.php` (panel list of a menu) still counts children item by item; it is not on a page view.
