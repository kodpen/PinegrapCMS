---
name: widget-parts-anywhere
description: W17 parts anywhere (#3074, #3085, #3111): a read-only part of the page's record placed outside its detail widget, drawn by that widget's renderer in the same request
module: widgets_parts_anywhere
---

## What is it?
- A node outside any widget with `props.partOf` = the shared id of the page's detail widget (`pg_sw_away_kinds()`: product, form record and event views). The widget draws its subtree from the same record where the node stands. Decided on #3074 (option b), built in #3085 (server).
- Read-only parts only (price block, gallery, breadcrumb, rating, stock line). `pg_sw_away_read_only()` refuses a subtree with a form control, an `action` or `section` binding, a messages node, code, a region, a shared component or a main loop: it draws nothing. Parts with controls stay inside their widget (W16; a later step asks first).

## How is it drawn?
- `_render_tree_node()` outside a widget scope writes `<!--pg-wpart:SID:SPEC-->` (`pg_sw_away_marker()`): SPEC is the subtree without `partOf`, URL-safe base64 JSON. Conditions go with it and the widget decides them; when every one reads a page fact the marker stands inside the `pg-pf` span instead ([[page-conditions]]).
- `_expand_system_widgets()` collects the markers whose widget is drawn in this pass, adds each subtree to the end of the widget's main loop (`pg_sw_main_loop_ref()`, else the tree's end) between `<!--pg-wpart-s:KEY-->` / `-e:` fences (`pg_sw_away_inject()`). After the finish pass the span is cut out of the widget's output (`pg_sw_away_take()`) and replaces the marker (`pg_sw_away_place()`). Keys carry a per-pass prefix; a marker of a widget drawn in a later (nested) pass stays for it.
- No record, or a condition cut it: the part shows nothing. A marker left after every pass (the page draws no such widget) is removed in `get_page_content.php`.

## Why is caching right?
- No second query and no new value: the part shows what its widget shows, from the address alone. Nothing posts.

## Byte identity?
- A tree without a `partOf` node renders as before: no marker, and every pass returns early on `strpos()`.

## And the designer?
- It places and previews parts (W17b, #3111): [[widget-parts-anywhere-editor]].

## Tests?
- `tests/widget_parts_anywhere_test.php`: unchanged tree, marker, refused controls, the part drawn as inside the widget (conditions, finish pass), leftovers.
