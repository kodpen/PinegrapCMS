---
name: image-delivery-pipeline
description: Plan (approved 2026-10-19, in progress: steps 1-3 and the cache upkeep done): image settings with a schema step, page rebuild button, Loading row left
---

## Why?
- Images were served as uploaded: WebP only by a manual action, no `srcset`/`sizes`, `loading="lazy"` only in widget galleries. AVIF has 96% browser support; every builder compared serves responsive, modern-format images by default.

## What is decided?
- One engine, made on first request through `get_file.php` (`?w=&f=`), never in the upload path: `docs/memory/platform/image-derivatives.md`.
- Designer output: `srcset`/`sizes` on every image of a site file, AVIF/WebP `<picture>` on the Image element only, first image `fetchpriority="high"`, the rest lazy: `docs/memory/designer/responsive-images.md`. Legacy raw `style_code` HTML is left untouched.
- Cache purge, a daily sweep of deleted and changed files' derivatives and the System Status storage line cover the folder: `docs/memory/platform/image-derivatives.md`. No "unused 90 days" rule: the derivative's mtime is its Last-Modified, and a current derivative is the cache working.
- Settings › Appearance: formats on/off, quality, and a "regenerate" button; `CDN` constant unchanged.
- Optional later: Speculation Rules `prefetch` for navbar links.

## What is built?
- Step 1 (PR #589): `includes/image_engine.php` (engine moved out of `includes/fn/image.php`, AVIF output, derivative functions), the `get_file.php` route. Name carries a signature (`<w>-<sig>.<ext>`), folder beside `FILE_DIRECTORY_PATH`, widths round up, `.skip`/lock files, formats tested once; fixed Imagick resizing rotated photos sideways.
- Step 2 (PR #595): `pg_img_*` in `includes/fn/image.php`, the designer renderer (Image element and bare `<img>`), the load order, `srcset` in the version stamp, the e-mail strip.
- Step 3 (PR #599): `pg_img_responsive_html()` over widget output, shared components and page bodies (bound, carousel, text images; srcset only), `auto` sizes for lazy full-width images, the variant chooser drops srcset when it swaps a photo.
- Step 4a (PR #[PR4]): purge, daily sweep, storage line with formats. No delete hooks (the sweep reads `files`).
- Changed from the first sketch: no single `pg_img_tag()`; the sets come from `pg_img_responsive($src)` and each renderer writes its own tag. `<picture>` only on the Image element (CSS may target `> img` on imported markup). `sizes` is capped at the file width; `auto` only for lazy full-width images. The importer already kept `srcset`/`sizes` (and drops them when the source changes), so nothing was needed there.

## What remains?
4. Settings (schema step: config columns, so owner approval first): formats on/off and quality per format (quality enters the signature, so a change replaces derivatives; get_file.php reads them before it closes the connection and hands them to `pg_image_derivative()`), and a "rebuild page HTML" button that rewrites `page_tree_code` of every designer page so pages published before step 2 get the markup without a republish (S–M).
5. A "Loading" row in the single Image element's Options (today only the multi-select Options and the Attributes panel set it) (S).
6. Memory update with each step.

## Open questions?
- Owner: may step 4 add config columns for the image settings (formats on/off, quality per format) through a 2026.11.0 migration step?

## Where to start?
- Step 4 settings: ask the owner for the config columns, then `pg_image_settings()` (`includes/fn/image.php`) and Settings › Appearance. Client-built galleries in `pg_civ_variants.js` still print plain `<img>`; fine until a variant has large photos.
