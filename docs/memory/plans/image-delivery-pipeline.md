---
name: image-delivery-pipeline
description: Plan (approved 2026-10-19, in progress: step 1 derivative engine and get_file.php route done): srcset/sizes, lazy loading except the LCP image, fetchpriority, settings, purge and retention left
---

## Why?
- Images are served as uploaded: WebP only by a manual action (`view_folder_and_files_f.php:7597`), no `srcset`/`sizes` anywhere, the HTML importer strips `srcset` (`assets/js/style_designer.js:37426-37432`), `loading="lazy"` only in widget galleries. AVIF has 96% browser support; CrUX shows the share of sites with good INP falling in 2026. Every builder compared serves responsive, modern-format images by default.

## What is decided?
- One engine, made on first request through `get_file.php` (`?w=&f=`), never in the upload path. How it works now: `docs/memory/platform/image-derivatives.md`.
- Output: a helper `pg_img_tag($file, $attrs)` prints `<picture>`/`srcset`/`sizes` and is used by the designer renderer for `image` nodes (`includes/panel/designer.php` and `includes/fn/designer.php`), by the catalog and form widgets, and by the legacy page renderer only where it already prints `<img>` through a helper; legacy raw `style_code` HTML is left untouched. It emits an AVIF `<source>` only when `pg_image_derivative_formats()` says AVIF works.
- `loading="lazy"` on every image except the first image of the page (`fetchpriority="high"`); the designer marks a node "above the fold" automatically for the first image of the root section and offers a manual toggle.
- The importer stops stripping `srcset`/`sizes`; existing attributes are kept and re-generated from the node.
- Cache purge (`pg_purge_caches()`), System Status storage tile and a retention sweep (derivatives unused 90 days) cover the new folder.
- Settings › Appearance: formats on/off, quality, and a "regenerate" button; `CDN` constant unchanged.
- Optional later: Speculation Rules `prefetch` for navbar links.

## What is built?
- Step 1 (PR #589): `includes/image_engine.php` (engine moved out of `includes/fn/image.php` so get_file.php can load it, AVIF output, derivative functions), the `get_file.php` route, `tests/image_derivatives_test.php`.
- Changed from the first sketch: the name carries a signature (`<w>-<sig>.<ext>`) so a replaced source is noticed without hooks; the folder sits beside `FILE_DIRECTORY_PATH` (multi-site); a requested width rounds up to a step; `.skip` markers and a lock file; a once-tested format list; no derivatives for ERP, workspace and attachment files. The same PR fixed the Imagick half resizing rotated photos to their sideways shape.

## What remains?
2. `pg_img_tag()` + designer `image` node renderer + importer fix (M). The plan's `[NEW]` changelog line goes in here, when sites start using derivatives.
3. Catalog/form widget images, product gallery (S).
4. Settings (quality enters the signature, so a change replaces derivatives), purge, status tile with the formats this server makes (`pg_image_derivative_formats()`; "regenerate" also deletes `formats.json`), retention sweep (orphan folders of deleted file ids, leftover `.tmp*`/`.lock`; "unused" cannot use the derivative's mtime, which is its Last-Modified), removing a file's folder when the file is deleted (S).
5. Memory update of `docs/memory/platform/image-*.md` with each step.

## Open questions?
- None open. AVIF without a writer falls back to WebP silently.

## Where to start?
- Step 2; read `docs/memory/platform/image-derivatives.md`, then the designer `image` node renderer.
