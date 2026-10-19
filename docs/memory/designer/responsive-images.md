---
name: responsive-images
description: Designer images published with srcset/sizes and AVIF/WebP <picture> (image-delivery step 2): which images, the sizes rule, first-image fetchpriority and lazy rest, version stamp, e-mail strip
---

## What does a published designer image look like?
- `_render_content_html()` image case: a file of this site's file manager (`pg_img_responsive()`, `includes/fn/image.php`) gets `srcset` of the derivative widths in its own format (`?w=`), the file itself closing the list, and `sizes`. The Image element also gets AVIF/WebP `<source>`s in `<picture data-pg-picture style="display:contents">` (only formats `pg_image_derivative_formats()` says this server writes; a format source stops at 1920 px).
- A bare `<img>` (semantic node: imported or written markup) gets only the `srcset`, never a `<picture>`: its CSS may target `> img`.
- No `<picture>` when the parent styles its direct children (`.row`, `.ratio`: `_pg_parent_styles_children()`, parent stack `_pg_parent_stack()`). In the Image element's own ratio box the `<picture>` is the box child (no display:contents) and the img gets `w-100 h-100`.
- Not touched: external addresses, bound `^^tokens^^`, SVG/GIF/BMP/TIFF, attachments, `erp-` files, a node whose Attributes panel has `srcset` (author's own; sizes from the panel win too).
- `onerror` also removes `<source>`s and `srcset` before swapping to no-image.svg (both outrank `src`).

## Why sizes = "(max-width: Npx) 100vw, Npx"?
- N = the file's displayed width (EXIF-oriented). With w descriptors the browser lays out an image with no CSS width at the sizes value; capping at the file width keeps the old layout. Checked in Chromium (390@3x, 1440, 1920@2x): fluid, ratio, .row child, bare img, width attr, narrow, height-only, w-100, flex, card all rendered the same size; image bytes on the test page 4.0 MB to 0.9 MB on a phone.
- Not `auto`: Chrome lays an auto-sized image without CSS width out at 300 px. A px width on the node gives `sizes="Npx"`.

## How do images load?
- `pg_img_loading_attrs()`: while `generate_style_code_from_tree()` writes a page body (`pg_img_page_state()`), the first image gets `fetchpriority="high"`, every later one `loading="lazy" decoding="async"`. Shared components and widgets drawn at request time get nothing automatic.
- The node's own choice wins: `props.loading` (multi-select Options "Loading attribute", printed since PR #[PR]) or a `loading` in the Attributes panel. No attribute is written twice.

## How are cached derivatives invalidated?
- `pg_version_assets()` (output stamp, `includes/fn/output.php`) also versions every local address in a `srcset` (`pg_version_srcset()`, `&amp;v=<mtime>`), so a replaced file is not served from a browser or CDN copy of its derivatives.

## What about e-mail?
- `get_page_content()` with `$email` ends with `pg_img_strip_responsive()`: the `<picture data-pg-picture>` is unwrapped and every `srcset`/`sizes` dropped. The e-mail pass only absolutises `src`.

## When does a page get it?
- `page_tree_code` is written at publish; an existing page gets the markup when it is published again. Shared components get it on the next request.
