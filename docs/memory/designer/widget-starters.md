---
name: widget-starters
description: W20b layout starters (#3179): style-neutral layouts per widget kind shipped as files, the gallery's fourth source, how the editor builds one, the binding test, kinds left
module: widgets_starters
---

## What is a starter?
- A ready-made, style-neutral layout a widget kind ships with the release (`includes/fn/widgets_starters.php`, no schema, no network): the repeated part (children of the loop area) and `wrap`, props for the element holding the loop area (`cssClass`, `gutter`). Bootstrap 5.3.8 site classes and theme colours only, no inline style; every word through `lang()`.
- `pg_sw_starters($kind)` holds them; `pg_sw_starter_kinds()` names the kinds; `pg_sw_starter_layouts($kind)` (`widgets_gallery.php`) is the gallery listing: `key`, `label`, `loop`, `wrap`.

## How does the gallery offer one?
- A fourth source "Starters" below Default in the widget panel's Layout Template section (W5, [[widget-layout-gallery]]), read once per widget through `shared_component` `starter_layouts` (read-only, in page_cache's clean list); hidden for a kind without starters.
- Try builds `_sdLgStarterTree(kind, starter, fields)`: the kind's Default tree (`_buildStarterTree()`), the starter's nodes in its first loop area, `wrap` merged onto the loop's parent; then `_sdLgPrepare()` like a template layout and `_sdLgShow()`: the same Try/Keep/Cancel and one undo step. The toolbar, filters, pages and messages of the Default stay.
- A form list marks where the record goes with `props.starterFields`; the editor fills it with `_swFormFieldNodes(fields, 'list')` from the chosen form (none chosen: reference code and date) and drops the marker.
- Running sites change nothing until an operator presses Keep; stored trees are never touched.

## Which kinds have starters?
- W20b-1: catalog card (`catalog_listing`: card grid, list with the picture beside, picture tiles), form list (`form_list_view`: three cards, one record, two wide cards to a row), recent comments (`comments_recent`: cards, quotes).
- Left for W20b-2: product detail, cart, order preview, form detail. Their Default trees carry forms and controls inside the loop (add to cart, quantities, checkout parts), so a starter there must rebuild those parts, not only the card.

## What does the test pin?
- `tests/widget_starters_test.php`: every binding value is in the kind's designer palette (the `SW_*` lists `check_bindings.php` compares with the renderers), every `eo_visible_if` flag is listed for the kind in `SW_VISIBILITY_FLAGS`, every action or section is one the kind's Default uses; no style attribute or fixed colour; the listing shape and keys; the wiring.
- Not run in a browser: the section's drawing and Try on the canvas.
