---
name: canvas-picture-stand-in-box
description: A bound picture's canvas stand-in (a sample photo SVG) takes the node's own box: no minimum size, so a round 40x40 avatar stays a circle (#9184, CF1)
---

## What does the stand-in draw?
- A sample photo since CF4, not the grey "Image [token]" card (`canvas-loop-as-published.md`); its size rules below are unchanged.

## What size does a bound picture's stand-in have on the canvas?
- The node's own: its width and height attributes, a ratio class or box (`ratio ratio-1x1`, `pg-ratio-*`), a size class (`pg-circle-40`, `pg-thumb-100`). The SVG (`_sdImgPlaceholderForToken()`, `_sdImgPlaceholderSized()`) only fills that box; it brings no shape of its own.
- A picture with no size at all takes the SVG's own size (300x200, or a real record's picture in a loop: `_sdFxGhostImageSizes()`), as the published page takes the photo's.

## What was wrong before?
- A canvas rule gave every `img[src^="data:image/svg+xml"]` in an image wrapper `min-width:120px; min-height:60px` (`!important`), meant as a hover target. A 40x40 `rounded-circle` became a 120x60 grey ellipse: the Google Reviews author photo, a 64x64 team member photo (120x64) and the 32x32 `__user_avatar_url` sample. The owner's pictures 1 and 4 of #9104.
- The rule is gone (#9184). Never give the stand-in a minimum, fixed size or ratio again: `tests/designer_canvas_round_picture_test.php` refuses a rule that sizes `img[src^="data:image/svg+xml"]`.

## What must a round picture in a starter carry?
- Equal sides: equal `width` / `height` attributes or a `pg-circle-*` class, with `rounded-circle object-fit-cover`, so a photo that is not square is still drawn as a circle on the canvas and on the page. The same test checks every round image node of the editor's starters in `style_designer.src.js`.
- The design templates' staff directory cards (`$optional_image('photo', 'full_name', 'rounded-circle object-fit-cover', …)` in about 45 templates; Café already has 64x64) have no size: a non-square member photo is oval at its own size on the page and on the canvas. Left open while design template work is paused (`workflow/coordinator-no-plans.md`); noted in `plans/designer-canvas-faults.md`.

## How was it checked?
- In a sandbox, the Google Reviews starter and a 64x64 bound team photo on the canvas: 120x60 / 120x64 before, 40x40 / 64x64 circles after, light and dark (pictures in #9184).

Related: docs/memory/designer/no-image-placeholder.md, docs/memory/designer/product-name-sku-and-canvas-placeholders.md
