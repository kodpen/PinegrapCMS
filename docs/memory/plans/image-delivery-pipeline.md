---
name: image-delivery-pipeline
description: Plan (approved 2026-10-19, in progress: steps 1-2 done, derivatives and designer srcset/picture/lazy): widget and catalog images, settings, purge, retention and page rebuild left
---

## Why?
- Images were served as uploaded: WebP only by a manual action, no `srcset`/`sizes`, `loading="lazy"` only in widget galleries. AVIF has 96% browser support; every builder compared serves responsive, modern-format images by default.

## What is decided?
- One engine, made on first request through `get_file.php` (`?w=&f=`), never in the upload path: `docs/memory/platform/image-derivatives.md`.
- Designer output: `srcset`/`sizes` on every image of a site file, AVIF/WebP `<picture>` on the Image element only, first image `fetchpriority="high"`, the rest lazy: `docs/memory/designer/responsive-images.md`. Legacy raw `style_code` HTML is left untouched.
- Cache purge (`pg_purge_caches()`), System Status storage tile and a retention sweep (derivatives unused 90 days) cover the new folder.
- Settings › Appearance: formats on/off, quality, and a "regenerate" button; `CDN` constant unchanged.
- Optional later: Speculation Rules `prefetch` for navbar links.

## What is built?
- Step 1 (PR #589): `includes/image_engine.php` (engine moved out of `includes/fn/image.php`, AVIF output, derivative functions), the `get_file.php` route. Name carries a signature (`<w>-<sig>.<ext>`), folder beside `FILE_DIRECTORY_PATH`, widths round up, `.skip`/lock files, formats tested once; fixed Imagick resizing rotated photos sideways.
- Step 2 (PR #595): `pg_img_*` in `includes/fn/image.php`, the designer renderer (Image element and bare `<img>`), the load order, `srcset` in the version stamp, the e-mail strip.
- Changed from the first sketch: no single `pg_img_tag()`; the sets come from `pg_img_responsive($src)` and each renderer writes its own tag. `<picture>` only on the Image element (CSS may target `> img` on imported markup). `sizes` is capped at the file width, never `auto`. The importer already kept `srcset`/`sizes` (and drops them when the source changes), so nothing was needed there.

## What remains?
3. Widget and catalog images (S–M): their `src` is a `^^token^^` resolved per row at request time, so the srcset is built after substitution (a `__image_srcset` style token or a pass over the row HTML); the variant chooser JS swaps `src` (`data-pg-bind`) and must clear or swap `srcset` too. Product gallery, carousel slides and thumbnails (`_pg_render_carousel_thumbs()`).
4. Settings (quality enters the signature, so a change replaces derivatives), purge, status tile with `pg_image_derivative_formats()` ("regenerate" also deletes `formats.json`), retention sweep (orphan folders of deleted file ids, leftover `.tmp*`/`.lock`; "unused" cannot use the derivative's mtime, which is its Last-Modified), removing a file's folder when the file is deleted, and a job that rewrites `page_tree_code` of every designer page so pages published before step 2 get the markup without a republish (S–M).
5. A "Loading" row in the single Image element's Options (today only the multi-select Options and the Attributes panel set it) (S).
6. Memory update with each step.

## Open questions?
- None open.

## Where to start?
- Step 3; read `docs/memory/designer/responsive-images.md`, then `includes/fn/widgets.php` (loop templates) and the variant chooser `assets/js/pg_civ_variants.js` (`data-pg-bind`).
