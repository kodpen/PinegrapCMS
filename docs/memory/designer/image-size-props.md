---
name: image-size-props
description: How the Image element's width and height props are written: a whole number as the width/height attribute, a unit as a style, in a ratio box on the box; page, editor and canvas agree
---

## What does a width or height prop become?
- `pg_img_dimension()` (`includes/fn/image.php`) and its twin `_sdImgDim()` (`style_designer.js`): a whole number (`'48'`) is the `width`/`height` attribute (pixels; the layout box stands before the file arrives). Anything else is CSS with its own unit (`'50%'`, `'12rem'`, `'120px'`, `'auto'`); a bare decimal (`'48.5'`) is written `48.5px`. No style ever holds a number without a unit: a browser drops that declaration.
- Outside a ratio box the CSS goes into the image's one style attribute, after `object-position`, before the Attributes panel's `style` (which wins).
- In the image's own ratio box (`aspectRatio`, `<div class="ratio ratio-XxY">`) the box takes the size (a whole number as `px`) and the overflow; the `<img>` keeps the whole-number attributes and no size in its style, so `.ratio > *` still fills the box.
- `sizes` is still worked out from the raw prop (`pg_img_sizes()`, docs/memory/designer/responsive-images.md).

## Which writers follow it?
- The page: the image case of `_render_content_html()` (`includes/fn/designer.php`), so also shared components and system widgets. The e-mail pass (`pg_img_strip_responsive()`) drops only `srcset`/`sizes`/`<source>`: the attributes and the style stay, which mail clients read.
- The editor's HTML (code view, preview, SEO check, revision diff): `CONTENT.image.toHTML()` writes only the attributes; the size CSS comes from `getNodeExtraStyle()` (`_sdImgSizeCss()`) through `addGlobalAttrs()`, which also takes `object-position` outside a ratio box.
- The canvas: `CONTENT.image.render()` and the same `getNodeExtraStyle()`.
- `tests/designer_image_size_test.php` cuts the editor's functions out and compares their markup with the page's for each case (Node; skipped without it).

## What was wrong before?
- Blocks such as team-profiles (`'112'`) and the testimonials (`'48'`, `'40'`) printed `style="width:48;height:48"` in the editor's HTML: dropped by the browser (the attributes beside it kept the size).
- The page wrote every value as an attribute: `width="12rem"` was read as 12 px, `width="48.5"` and `width="120px"` were not valid attribute values.
- The editor printed a second `style` attribute on the `<img>` (object-position) and on the ratio box (overflow); the browser kept only the first, so a size with a unit or the box's size was lost. The ratio box's size had no unit (`width:200`), so the box spanned the column.
- On the canvas a percentage width also went on the image's wrapper, so `50%` drew a quarter of the column; the wrapper is now only made a block.

## What about pages already saved?
- `page.page_tree_code` keeps the HTML drawn at its last save; the tree is unchanged. A page gets the new markup when it is saved again or by Rebuild Page HTML (docs/memory/designer/page-html-rebuild.md). Shared components and widgets are drawn per request and follow at once. No migration.
