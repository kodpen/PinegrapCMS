---
name: image-size-props
description: How the Image element's size is written: whole-number width/height as attributes, a unit as a style, the Sizing panel's style; in a ratio box the box takes the style, the img its attributes
---

## What does a width or height prop become?
- `pg_img_dimension()` (`includes/fn/image.php`) and its twin `_sdImgDim()` (`style_designer.js`): a whole number (`'48'`) is the `width`/`height` attribute (pixels; the layout box stands before the file arrives). Anything else is CSS with its own unit (`'50%'`, `'12rem'`, `'120px'`, `'auto'`); a bare decimal (`'48.5'`) is written `48.5px` (docs/memory/designer/sizing-panel-lengths.md). No style ever holds a number without a unit.
- `sizes` is still worked out from the raw prop (`pg_img_sizes()`, docs/memory/designer/responsive-images.md).

## What else from the Sizing panel reaches the image?
- All of it, as on every element: min/max width and height, top/right/bottom/left, z-index, overflow (`_pg_node_sizing_decls()` on the page, `getNodeExtraStyle()` in the editor, same order), and its classes (visually-hidden, focus ring, placeholder, `ratio`). Before, the page's image case wrote none of them while the editor and the canvas did.
- Outside a ratio box they go into the `<img>`'s one style attribute: font, inline style, `object-position`, the sizing style, then the Attributes panel's `style` (which wins).

## What does the ratio box take?
- The image has its own box (`<div class="ratio ratio-XxY">`) for a preset `aspectRatio`, and (`<div class="ratio">` with `--bs-aspect-ratio`) for the Sizing panel's custom ratio when it is switched on with a value. Same test on both sides: `_sdImgInRatio()` and the image case of `_render_content_html()`. `'custom'` alone is no box (the editor used to wrap it in `ratio-custom`, a box with no height).
- The box takes the sizing style: the size (a whole number as `px`), min/max, offsets, z-index, overflow, plus the image's `rounded*` classes.
- The `<img>`, the element the operator selected, keeps its id, the Attributes panel's entries, its `style` (font, `object-position`, user style), its classes and the Sizing panel's classes. The editor and the canvas used to put them on the box, the page on the `<img>`.

## Which writers follow it?
- The page: the image case of `_render_content_html()` (`includes/fn/designer.php`), so also shared components and system widgets. The e-mail pass (`pg_img_strip_responsive()`) drops only `srcset`/`sizes`/`<source>`.
- The editor's HTML (code view, preview, SEO check, revision diff): `CONTENT.image.toHTML()` writes the tags and the attributes; `addGlobalAttrs()` the style, on the `<img>` or the box.
- The canvas: `CONTENT.image.render()` and `buildNodeEl()` (`_attrEl` / `_boxEl`). A percentage width, min-width or max-width makes the image's wrapper a block, or the percentage resolved against the shrunk wrapper.
- `tests/designer_image_size_test.php` runs the editor's functions under Node (skipped without it) against the page's markup, attribute order aside.

## How does the Attributes panel list them?
- `getNodeBuiltinAttrs()`: `width`/`height` rows only while they are attributes (empty or a whole number). A unit is in the merged `style` row outside a ratio box (`_sdInlineStyleProps()`: an edit there writes the prop, a whole number goes back to the attribute); in a ratio box it is the box's and not listed.

## What was wrong before?
- Editor: `style="width:48;height:48"` beside the attributes (team-profiles, testimonials); page: every value an attribute (`width="12rem"` read as 12 px); a second, dropped `style` attribute; the panel listed `width: 50%` as an attribute.

## What about pages already saved?
- `page.page_tree_code` keeps the HTML drawn at its last save; the tree is unchanged. A page gets the new markup when it is saved again or by Rebuild Page HTML (docs/memory/designer/page-html-rebuild.md). Shared components and widgets are drawn per request and follow at once. No migration.
