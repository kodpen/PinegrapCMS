---
name: menu-sequence-widget
description: Menu Sequence widget (menu_sequence_region, #PRNUM): the classic <menu_sequence> region in the designer; reading order of a menu's pages, previous and next links, wrap to the first, tokens, flags.
---

## What is it?
- Kind `menu_sequence_region` (`includes/fn/widgets_menu.php`, beside the Menu widget [[menu-widget]]), label "Menu Sequence". `cfg.menu_name` names the menu as `<menu_sequence>name</menu_sequence>` does. One screen, no loop, no Messages block.

## Which pages, in which order?
- `pg_mw_sequence()`, as `get_menu_sequence()`: each item before the items under it, an item without a page left out, a page once (its first item). No access check, as the classic region. It does not use `get_menu_sequence()`: that function keeps the pages it has seen in a global for the whole request, so a second sequence on the page came out empty.
- The page shown is matched by `link_page_id` (the classic region compares page names; one page has one name).

## What does it draw?
- `pg_mw_sequence_values()`: `__previous_name` / `__previous_url` (none on the first page), `__next_name` / `__next_url` (on the last page the first page again, as the classic `>` link), `__sequence_position`, `__sequence_count`, `__menu_name`. Flags `in_sequence`, `not_in_sequence`, `has_previous`, `next_wraps`. A page the menu does not link to gets no links.
- Starter: a `nav` row with a previous and a next button (`rel` prev / next), the previous one shown only with `has_previous`. Edit chip as the Menu widget.
