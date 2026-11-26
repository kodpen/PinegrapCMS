---
name: designer-my-library-save-and-group
description: "Save to my library" (element menu, command palette) and its window, the "My library" group atop the Blocks list with search and hover card, adding a copy, the amber mark of a cleared binding (ML2)
---

## Where does "Save to my library" sit?
- One row in the three-dot menu of the Options panel's element row (`_sdIdentityBind()`) and the command palette row `act_library_save`. No button. Shown to the designer roles only (`_sdLibMaySave()`: full access and the part handed over), for one element that holds others (`_sdLibOffered()`); with two or more selected it is not in the menu.
- The window (part `mylibrary`, `assets/js/style_designer_mylibrary.js`): Name (the element's label), Group (the group used last, `localStorage` `pinegrap_designer_library_group`; offers the groups in use, nearest first, and "New group …"), the line "A copy you start from…", Cancel / Save. Enter saves, an empty name keeps Save off, 60 characters each.
- A name the library has: "The library has an item of this name in "…". Both are kept." A server refusal shows its reason in the window, which stays open. Saved: one line with "Show", which opens Blocks on the item.

## What does the server do?
- Sub actions of the `designer` panel action, in `pg_library_editor()` (`includes/fn/designer_library.php`): `library_list` (cards: `pg_library_card()`), `library_save`, `library_prepare`. List and prepare are open to content roles; each call checks `pg_library_may()`.
- The editor sends the User Styles text as `css` and the canvas's computed values of the variables named as `vars` (`_pg_library_request_design()`: the User Styles' own definitions win). Save reads the rows the subtree places (`pg_library_site_rows()`) and the site's pages, contact groups and folders by name; a new item goes last in the order.
- `library_prepare` runs `pg_library_item_into_design()`; with `make=1` (an item holding widgets or shared components, designer roles only) it makes their rows (`_pg_library_make_rows()`, inner ones first) and points the tree at them (`pg_library_tree_place_rows()`).

## How is the group drawn?
- First group of `#sd-blocks-list` (of `#sd-comp-list` in a design without Bootstrap), drawn the first time that list is shown (`_sdLibMount()` from `_sdPalSwitchShow()`; at open only when that list shows, after 1.5 s). Items with no group first, then each group's name and its items, in the library's order. Folding is kept in `pinegrap_designer_library_folded`.
- Rows are palette rows of type `library` (`extra.libraryId`); the list's search reads name and group, a group name hides with its items. Empty: the first-use line for designers; a content role gets no group.
- The hover card: `_sdBlockSketch()` of the item's tree, "My library, <group>", the kind and count, "Made with Bootstrap…" in a design without it. Library rows never go into Recently Used.

## What happens when a copy is added?
- `createFromPalette('library')` asks the part (`_sdLibNode()`). An item without rows is made ready while its card shows; then the copy lands at once. Otherwise a placeholder ("Adding …") stands in the spot until `library_prepare` answers, and is swapped in the same undo step.
- The prepared `css` is appended to the User Styles. A line only when a class was renamed (named, or a count over three), a binding cleared or pictures are missing (`designer-library-missing-pictures.md`).
- A node or widget placement with `props._libraryUnbound` draws an amber outline on the canvas (`.sd-lib-unbound`) and a line under the Options panel's element row; `_sdLibUnbound()` drops it once each cleared kind (`pg_library_ref_kind()`, `menu_name`, `shared`) has a record again.

## What is measured?
- `node tests/js/designer_render_timing.js` before and after (median of 25 runs, three runs each): unchanged within spread. The core bundle grew about 2.2 KB (0.8 KB gzip); the part is its own 13.7 KB bundle.
- Tests: `tests/designer_library_test.php` (card, root kind, rows placed, the request's design).
