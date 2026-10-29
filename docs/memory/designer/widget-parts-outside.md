---
name: widget-parts-outside
description: W17 parts anywhere (#3074): a detail widget's read-only parts placed outside its box; page marker, first render, marks cut out, sweep.
module: widgets_outside_parts
---

## What is a part outside the widget?
- A `widget_part` node in the page tree: `props.widgetId` (a detail widget the page places), `props.part` (one of `pg_sw_outside_part_kinds()`: `catalog_item_view` → price, gallery, breadcrumb, rating, stock), children = the part's design elements. Decided in #3074 option (b) ([[widget-designability-decisions-2]]).
- Read-only: `pg_sw_outside_part_clean()` drops form controls, nodes bound to `action`, `value` or `section`, widgets, shared components, regions, a main loop (a sourced list stays) and content types outside `pg_sw_outside_part_content_types()`. Parts with controls (add to cart, variant picker, comment form, cart summary) stay in their widget.

## How is it drawn?
- Outside a widget `_render_tree_node()` writes `<!--pg-widget-part:SID:PART:B64URL-->` (the cleaned children) into the stored body; an unknown part or no widget writes nothing.
- `_expand_system_widgets()` reads the markers first (`pg_sw_outside_parts_find()`). The first render of widget SID of a kind drawing that part takes them (`pg_sw_outside_parts_for()`); `pg_sw_outside_parts_tree()` adds each to the end of the widget's root as a `widget_part` with `_partNo`. In the widget scope that node draws its children between `<!--pg-wpart:NONCE:N-->` marks: same record, same bindings, conditions and finish pass, no second query path.
- After the finish pass (and the image passes) `pg_sw_outside_parts_take()` cuts each span out of the widget's output; `pg_sw_outside_parts_place()` puts it at its marker, '' when the widget drew none (no record: not-found output).
- `get_page_content.php` sweeps markers no widget took (`pg_sw_outside_parts_sweep()`), before the page conditions.

## Why is caching right?
- The marker is plain stored text; its markup depends on the address only, as the widget's own. Nothing posts. A tree without the node renders byte-identical (no marker, `find()` returns nothing).

## What is left? (plan cut)
- PR 2: the editor: placing a part (a part list per detail widget on the page, the starter elements built like the default parts), the canvas preview, the editor's Preview.
- The variant script (`pg_civ_variants.js`) updates only the widget's form: a part outside does not follow a variant switch yet.

## Tests?
- `tests/widget_outside_parts_test.php`: marker, clean, render inside and outside a scope, take, place, sweep.
