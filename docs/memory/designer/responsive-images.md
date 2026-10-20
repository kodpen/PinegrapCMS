---
name: responsive-images
description: Designer and widget images with srcset/sizes, AVIF/WebP <picture> on the Image element: which images, sizes rule, widget pass, variant swap, version stamp, e-mail strip (loading order: image-loading)
---

## What does a published designer image look like?
- `_render_content_html()` image case: a file of this site's file manager (`pg_img_responsive()`, `includes/fn/image.php`) gets `srcset` of the derivative widths in its own format (`?w=`), the file itself closing the list, and `sizes`. The Image element also gets AVIF/WebP `<source>`s in `<picture data-pg-picture style="display:contents">` (formats this server writes and the settings offer: `pg_image_delivery_formats()`; a format source stops at 1920 px).
- A bare `<img>` (semantic node: imported or written markup) gets only the `srcset`, never a `<picture>`: its CSS may target `> img`.
- The sets come from `pg_img_responsive($src)`; each renderer writes its own tag (no shared `pg_img_tag()`).
- No `<picture>` when the parent styles its direct children (`.row`, `.ratio`: `_pg_parent_styles_children()`, parent stack `_pg_parent_stack()`). In the Image element's own ratio box the `<picture>` is the box child (no display:contents) and the img gets `w-100 h-100`.
- Not touched: external addresses, SVG/GIF/BMP/TIFF, attachments, `erp-` files, an image with its own `srcset` (and its own `sizes` wins).
- `onerror` also removes `<source>`s and `srcset` before swapping to no-image.svg (both outrank `src`).

## What about widget, carousel and bound images?
- `pg_img_responsive_html()` adds `srcset`/`sizes` (own format, never a `<picture>`) to every `<img>` of a site file without one: on each system widget's output (`_expand_system_widgets()`, not for e-mail: catalog cards, product photos, cart lines with `^^token^^` src filled in), on each shared component at request time, and on the page body at publish (carousel slides, images in texts and custom HTML). Files are read with one query per pass (`pg_img_prefetch()`, PR #599).
- A bound image's `onerror` drops `srcset` too (its srcset comes after the token is filled). `pg_civ_variants.js` removes `srcset`/`sizes` and `<source>`s before it swaps a variant photo into `src`; the galleries it draws itself print plain `<img>`s (fine until a variant has large photos).

## Why sizes = "(max-width: Npx) 100vw, Npx"?
- N = the file's displayed width (EXIF-oriented). With w descriptors the browser lays out an image with no CSS width at the sizes value; the cap keeps the old layout. Chromium (390@3x, 1440, 1920@2x): 13 layouts (fluid, ratio, .row child, bare, width attr, height-only, w-100, flex, card) same size; test page 4.0 MB to 0.9 MB on a phone.
- `auto` first only for a lazy image whose class sets its width (`w-100`, `card-img*`: `pg_img_fills_width()`): Chrome lays an auto-sized image without CSS width out at 300 px. A px width on the node gives `sizes="Npx"`.
- Store template home in Chromium (Bootstrap served locally): 29 images, 3 widths, same size before and after.

## How do images load?
- A page body's first image first, the rest lazily, a node's own `loading` attribute before both: docs/memory/designer/image-loading.md.

## How are cached derivatives invalidated?
- `pg_version_assets()` (output stamp, `includes/fn/output.php`) also versions every local address in a `srcset` (`pg_version_srcset()`, `&amp;v=<mtime>`), so a replaced file is not served from a browser or CDN copy of its derivatives.

## What about e-mail?
- `get_page_content()` with `$email` ends with `pg_img_strip_responsive()`: the `<picture data-pg-picture>` is unwrapped and every `srcset`/`sizes` dropped. The e-mail pass only absolutises `src`.

## When does a page get it?
- `page_tree_code` is written at publish; older pages get it from Rebuild Page HTML (`docs/memory/designer/page-html-rebuild.md`), run unasked when the formats change. Shared components get it on the next request. A classic page's raw `style_code` HTML is left as written.
