---
name: designer-region-to-shared-component
description: A classic designer region made a designer-only shared component from the designer (R3): PHP blocks as custom_php nodes, placeholders as region nodes, nested refs
---

## What does it do?
- The Region panel of a `designer` region node shows "Make shared component" to roles 0-1 (`_design.designerRegionConvert`, the right `edit_designer_region.php` asks). `_sdDesignerRegionConvert()` calls `shared_component` sub-action `from_designer_region` (name) and puts a `shared_ref` in the node's place on that page only. Per page, never automatic; the `cregion` row stays and classic pages render it as before.
- `pg_designer_region_component()` (`includes/designer_region_import.php`) gives back the component made earlier from the same region (`category = 'cregion:<cregion_id>'`), else inserts one (name from the region, `_sc_unique_name()`, not opened to content editors). After the first conversion the two copies are separate: an edit to the classic region does not reach converted pages. The components screen lists the source as "Designer Region".

## How is the content converted?
- `pg_di_designer_region_tree()` lifts three kinds of block before DOMDocument parses (it would mangle them), then runs the import converter (`pg_di_node()`, fragment mode: writes nothing):
  - PHP, cut by `token_get_all()`: each self-contained block becomes a `custom_php` node (`<?=` becomes `echo`, the statement the closing tag ended gets its `;`). When any block is not whole (braces or alternative syntax across blocks), two blocks share a variable, or a block sits in a tag, a script or another raw block, the whole region becomes one `custom_php` node `'?>' . content`: nothing is cut apart.
  - raw blocks (comments, script, style, noscript, pre, textarea, link, meta) as `custom_html`, verbatim; tags written inside them stay text.
  - the classic placeholders (L1 reuses this for style code) as `region` nodes printing the same tag (`<menu>` takes its clearing div). A `<cregion>` naming a designer region becomes a nested `shared_ref` to that region's component, made recursively; one naming an ancestor stays a region node (no loop). Common regions stay region nodes (R2).
- An element holding a lifted block is built from nodes, never as rich text (`pg_di_holds_lifted()`). Every lifted block must come back exactly once as a node, else the whole-region fallback.

## Why the PHP rules?
- Page eval ran the region in one scope inside `get_page_content.php`; each `custom_php` node is a separate `eval()` in a closure. So shared variables force one node, and a variable read but never set is reported ("reads $x without setting it").
- With `PHP_REGIONS` off the PHP never ran: the region becomes one `custom_html` with the PHP as text, so conversion runs no code that did not run (custom_php evals regardless of `PHP_REGIONS`). A hosted site drops `custom_php` output: reported.
- Who writes PHP does not widen: the sub-action is a write (role <= 1 gate in `pg_panel_shared_component()`), the component is designer-only, and if a designer opens it to content editors the merge keeps `custom_php` nodes as stored.

## Rendering order?
- Component markers expand before `_expand_custom_php()` and before the designer/common region passes in `get_page_content.php`, so region tags printed by the component's nodes are still expanded. A designer region nested two deep, never expanded by the classic pass, is drawn after conversion.

## Tests?
- `tests/designer_region_import_test.php`; the DB part (`pg_designer_region_component()`) has no test (needs a database).
