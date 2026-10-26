---
name: menu-widget
description: Menu widget (menu_region, #1781): a classic menu region in the designer; items stay in the menu screen; menu.children lists, row tokens, current item, access, window, edit chip, gaps.
module: widgets_lists_menu, widgets_menu
---

## What is it?
- Kind `menu_region` (`includes/fn/widgets_menu.php`; not `menu`, the region node's type), label "Menu". `cfg.menu_name` names the menu as `<menu>name</menu>` does; a new widget takes the site's first menu. R6 of [[legacy-retirement-prerequisites]].
- The items stay where they were: `menu_items`, edited in the menu screen by managers and the users of `users_menus_xref`. The widget reads them on every request through `pg_menu_tree()` (which now also reads `link_page_id`).

## How are the levels drawn?
- The main loop repeats the top-level items. The sub-items of a row are a list, source `menu.children` (family `menu`, key `__menu_item_id`, `includes/fn/widgets_lists_menu.php`); a list in its row draws the level below (four lists deep at most, [[widget-lists]]). Outside the main loop the key reads `0`, so a list there lists the top level.
- The renderer publishes every level at once (`pg_sw_list_menu_rows()`: parent id => rows); the list pass reads them, no query is added.

## What does a row hold?
- `__menu_item_name` (first: A to Z sorts by it), `__menu_item_id`, `__menu_item_url`, `__menu_item_target`, `__menu_item_has_children`, `__menu_item_is_current`, `__menu_item_holds_current`; the last three are `1` or empty, for conditions (`show_if` works in the main loop and in lists alike, `eo_visible_if` only outside lists).
- Address: the page's (`OUTPUT_PATH` + page name), else the URL, else `#`. Window: `_blank` for "New Window" on an item that links somewhere. `pg_mw_stamp_targets()` puts `^^__menu_item_target^^` in the target prop of every link bound to `__menu_item_url` without one; an empty target is dropped from the main loop's markup, a list row keeps `target=""` (the same window).
- Current item: the first item by id linking to the page shown, as the classic menu marks it; the items above it hold it. Names translated with `pg_tr_text()` on a page in another language.

## Who sees which item?
- As `get_menu_content()`: an item with security on that links to a page the visitor may not view (`check_view_access()`) is left out with everything under it. Flags `has_menu_items` / `no_menu_items`.

## What does the starter look like?
- A Bootstrap navbar: toggler, collapse, `ul.navbar-nav` with `smartActive` (the link to the page shown gets `active` and `aria-current`), an item with sub-items as a dropdown whose menu is a `menu.children` list. No Messages block (`_SW_NO_MESSAGES`; the server names it `menu_region`).

## Edit chip?
- On the front end, for a manager or a user granted the menu (`pg_mw_can_edit_menu()`), a chip opens `view_menu_items.php` for that menu.

## What is left out?
- The menu's effect (pop-up, accordion and `software.init_menu()`), its `class` and `active_item_class` and the item `class` column: the design gives the look. A parent with sub-items is a dropdown toggle, not a link. A `<menu>` region node becomes this widget with "Make designable" ([[region-node-to-widget]]).
