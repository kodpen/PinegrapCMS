---
name: sizing-panel-lengths
description: Display & Sizing panel lengths (width, height, min/max, top/right/bottom/left): a number without a unit is written in px by the page, the editor and the canvas, in one order
---

## What does the panel take?
- Free CSS text: Width, Height and their Min/Max sub-rows, and the Position offsets Top/Right/Bottom/Left (Appearance › Display & Sizing, `rowCss()` in `style_designer.js`). The placeholders give CSS values (`e.g. 100%`, `e.g. 200px`, `e.g. auto`, `e.g. 0`); nothing adds a unit. Z Index and Overflow are selects.
- A number typed without a unit (`48`, `-8`, `1.5`) is read as pixels: the unit of the panel's examples and of an image's width/height attributes (docs/memory/designer/image-size-props.md). Written as typed, CSS drops the declaration (`width:48`).

## Who writes it?
- `pg_designer_css_length()` (`includes/fn/designer.php`) and its twin `_sdCssLen()`: a bare number gets `px` (`0` and `1e2` too), anything else stays as typed, trimmed.
- The page: `_pg_node_sizing_decls()` builds the declarations, `_build_node_sizing_attrs()` escapes them for container, area, row, col and semantic; the image case uses the same list (image-size-props.md).
- The editor's HTML and the canvas: `getNodeExtraStyle()`. Both lists have the same order (z-index, width, min-width, max-width, height, min-height, max-height, overflow, overflow-x, overflow-y, top, right, bottom, left, `--bs-aspect-ratio`), so the page and the editor write the same bytes. A prop is read as the editor's `if (p.x)`: `pg_designer_prop_text()` keeps the string `'0'` and drops the number 0 (PHP's `empty('0')` dropped `right:0` before).
- `tests/designer_image_size_test.php` (`test_designer_sizing_length_*`) compares `getNodeExtraStyle()` under Node with `_build_node_sizing_attrs()`.

## What was wrong before?
- Both sides wrote the value as typed: `width:48`, `min-height:200`, `top:-8`, which the browser ignored, so a container, row, column or semantic element kept its natural size. The PHP order differed from the editor's.

## What does not take the Sizing panel on the page?
- Content elements other than the Image (heading, paragraph, link, icon, spacer…) and components: the editor and the canvas apply the panel's style to them, the page renderer (`_render_content_html()`, `_render_component_html()`) does not. A known gap.

## What about pages already saved?
- `page.page_tree_code` keeps the HTML drawn at its last save; the tree is unchanged. A page gets the units when it is saved again or by Rebuild Page HTML (docs/memory/designer/page-html-rebuild.md). Shared components and widgets are drawn per request and follow at once. No migration.
