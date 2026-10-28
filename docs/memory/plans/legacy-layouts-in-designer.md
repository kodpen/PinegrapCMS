---
name: legacy-layouts-in-designer
description: "Plan (2026-10-19; Y1-Y4, Y5a, Y6 done): a classic page's custom layout in the visual designer"
---

## What is a layout, and why is it the hardest part of the retirement?
- 26 page types (`check_if_page_type_supports_layout()`) draw their system region through `render_layout()`: the generated default `includes/templates/<type>.php`, or, when `page.layout_modified`, the PHP file `data/layouts/<page_id>.php` written from `page_designer.php?type=layout` (`pg_panel_update_layout()`; `generate_layout_content()` is the reset). Hosted sites ignore the file (`pg_hosted()`).
- A layout is a view template over a fixed data contract (`$items`, `$product_groups`, `$field`, `$recipient`…): no queries, no includes; 5 of 88 carry an inline `<script>`.
- The starting site shows the weight: 88 of 180 pages have a custom layout, 86 of them modified (`data/backups/english_default/layouts`, 35k lines: form list view 26, custom form 13, order form 10, photo gallery 6, catalog 5…). Half the look of a typical site is in these files.
- The designer has no such thing: a system widget is a tree with bindings. A `region` node of type `system` prints `<system></system>` and the classic renderer, layout included, fills it (`get_page_content.php`): the look survives conversion, editing it does not.

## What is done?
- Y1 (#1804) design page queries (`pg_page_visual_design_sql()`); Y2 (#1819, #1851) page-type pane with Layout Type; Y3 (#1823) layout as a code tab and Y6 (#1881) Ctrl+G to it ([[layout-code-tab]]); Y4 (#1950) canvas preview ([[legacy-region-preview]]).

## What is left?
- Y5 Replace with widget, opt-in per page: a system widget of the matching type with its default tree beside the region; the designer restyles it by hand and removes the `system` node when they say so. Never automatic. Cut in three:
  - Y5a done (#2838, server): `pg_layout_widget_offers()` → `_design.layoutWidgets`, the kind and the type settings carried into its config ([[layout-widget-offer]]).
  - Y5b (open PR, `style_designer.js`): on the region's panel of a page in `_design.layoutWidgets`, "Put a widget beside it": `_swCreateRow()` with the seed then `_swTypeDefaults()` and `_buildStarterTree(kind)`, the `shared_ref` placed after the region node (as `_sdLayoutConvert()`), one undo step; a hint that the region stays until deleted. Once a widget of that kind sits on the page, a "Remove the classic region" button (confirm) deletes the `system` node; the layout file stays.
  - Y5c (only if needed): 301s. Not needed while the page keeps its type (see [[layout-widget-offer]] "Are 301s needed?"); check again after Y5b and drop the step if nothing changes the type.

## Decisions?
- Decided (Y5a): `page_type = 'standard'` with `layout_type = 'custom'` (2 starter pages) is left as it is, no normalisation: a standard page never calls `render_layout()`, gets no tab and no widget offer.

## Where to start?
- Y5c only if a step changes the page type; otherwise the plan ends with Y5b.
