---
name: component-overrides-render
description: How a place's values of a shared component reach the site, the canvas and the Preview (CO2, #11595): the place marker, the per-call render and the cache rule, the draw checks, translation
module: component_overrides
---

## How does the site draw a place's values?
- `_render_tree_node()` writes `<!--pg-place:BASE64URL-->` (`pg_co_place_marker()`) just before the place's `<!--pg-shared-ref:ID-->`, after a widget's `<!--pg-placement:…-->`. A place without values writes no marker: every page saved before, and a component with no open field, draw byte for byte as before (`tests/component_overrides_render_test.php`).
- `_expand_shared_refs()` keeps each id's own drawing and its tree; `_pg_shared_refs_replace()` draws a place with values from `pg_co_apply($tree, pg_co_safe_values(…))` through `_pg_shared_tree_html()`, once per id and values within the call. Values that change nothing give the component's own drawing.
- A system widget takes no place values: its place marker is dropped, its placement marker kept.

## Can a place's values reach another page?
- No. The per-place drawings live only in the one `_pg_shared_refs_replace()` call; nothing is kept between calls, so the page cache holds them only in the copy of the page whose body carries the marker. The cache test in `tests/component_overrides_render_test.php` keeps this.

## Which checks does a value pass when drawn?
- `pg_co_safe_values($tree, $values)` (JS `safeValues`): only values an open field takes and that fit its kind. A link that would run a script (`javascript:`, `vbscript:`, `livescript:`, `data:`) or a picture with such a src (a picture may be `data:image/`) is left out and the component's own is drawn (`pg_co_address_runs_script()`). A text's `<!--pg-` markers and `<?` are made inert, as the content-role gate does with a content editor's text.

## How do canvas, Preview and code view draw them?
- `_sdPlaceDraw(refNode, tree, draw)` in `style_designer.src.js`: puts the place's values into the component's own nodes while `draw` runs and puts them back after, so canvas handlers keep the component's nodes. Used by the canvas (`buildNodeEl`) and by `_sdEmitNode()` (Preview, export, code view), never for a system widget.
- The model is the first file of the part `overrides` (`component-overrides-fields.md`), loaded the first time a place with values is drawn; until then the component's own tree is drawn, then `render()` runs once.

## How does translation see a place's texts?
- `pg_tr_walk_tree()` adds a placing node's open text values to the page's segments (`pg_tr_place_segments()`, node id `<place id>.<field id>`, field `text`), reading the component's tree once per request (`pg_tr_shared_tree()`). At render the drawn text goes through `pg_tr_props()` like any text, so a translation of it is shown.
