---
name: widget-list-flags
description: A widget flag (eo_visible_if) inside a list's row naming a row value is a value condition on it (W23): the alias, the two tree walkers that leave it, the editor's offer, what is not covered
module: widgets_lists
---

## What does the alias do?
- Inside a list's row template, `eo_visible_if: X` where `__X` is a row token of that list or of a list around it reads as `show_if: __X not_empty`: `menu_item_has_children` keeps a caret only in rows with sub-items (`pg_sw_list_flag_alias()`, `includes/fn/widgets_lists.php`). Every list source's "1 or empty" tokens are such flags.
- `pg_sw_list_span()` pushes the list's flag names (`pg_sw_list_row_flags()`) while it draws the row template; `_render_tree_node()` wraps the node with the alias before its own conditions, so both must pass. Outside a widget render, or outside a list, nothing changes.

## Why do the tree walkers leave it?
- `_eo_apply_visibility_bindings()` and `_pg_sw_mark_visibility()` judge the widget's own flags on the whole tree before the render; a row flag is in no widget's flag map, so the first dropped it everywhere. Both now skip a flag a list around the node answers (their third argument, the row flags gathered on the way down). The widget's flags still work inside lists.

## What does the editor give?
- Bind Data "Show only when" on an element inside a list adds the row flags of the lists around it, nearest first, labelled "<list>: <value>" (`_sdListRowFlags()`, tokens matching `_is_`, `_has_`, `_holds_`).

## What is not covered?
- A row flag in the main loop stays each renderer's; the catalog's and comments' own walkers do not read lists. The canvas draws a row flag as before (no state). Tests: `tests/widget_list_flag_alias_test.php`.
