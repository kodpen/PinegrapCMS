---
name: image-delivery-pipeline
description: Plan (approved 2026-10-19, not started): automatic WebP/AVIF derivatives, srcset/sizes, lazy loading except the LCP image, fetchpriority, derivative cache and disk accounting
---

## Why?
- Images are served as uploaded: WebP only by a manual action (`view_folder_and_files_f.php:7597`), no `srcset`/`sizes` anywhere, the HTML importer strips `srcset` (`assets/js/style_designer.js:37426-37432`), `loading="lazy"` only in widget galleries. AVIF has 96% browser support; CrUX shows the share of sites with good INP falling in 2026. Every builder compared serves responsive, modern-format images by default.

## What is decided?
- One engine: extend `pg_process_image_file()` (`includes/fn/image.php`) with derivative generation (widths 480, 960, 1440, 1920 capped at the original; formats WebP and, when GD/Imagick supports it, AVIF). Derivatives live under `data/cache/img/<file_id>/<w>.<ext>` and are produced on first request through `get_file.php` (`?w=&f=`), never in the upload path, so uploads stay fast and old sites gain it without a rebuild.
- Output: a helper `pg_img_tag($file, $attrs)` prints `<picture>`/`srcset`/`sizes` and is used by the designer renderer for `image` nodes (`includes/panel/designer.php` and `includes/fn/designer.php`), by the catalog and form widgets, and by the legacy page renderer only where it already prints `<img>` through a helper; legacy raw `style_code` HTML is left untouched.
- `loading="lazy"` on every image except the first image of the page (`fetchpriority="high"`); the designer marks a node "above the fold" automatically for the first image of the root section and offers a manual toggle.
- The importer stops stripping `srcset`/`sizes`; existing attributes are kept and re-generated from the node.
- Cache purge (`pg_purge_caches()`), System Status storage tile and a retention sweep (derivatives unused 90 days) cover the new folder; `get_file.php` keeps its 304/`max-age` headers.
- Settings › Appearance: formats on/off, quality, and a "regenerate" button; `CDN` constant unchanged.
- Optional later: Speculation Rules `prefetch` for navbar links.

## What remains?
1. Derivative engine + request route + tests (`tests/image_derivatives_test.php` on pure sizing logic) (M).
2. `pg_img_tag()` + designer `image` node renderer + importer fix (M).
3. Catalog/form widget images, product gallery (S).
4. Settings, purge, retention, status tile (S).
5. Memory update of `docs/memory/platform/image-*.md`.

## Open questions?
- AVIF on shared hosts without Imagick: fall back to WebP silently; System Status shows which formats the server can make.

## Where to start?
- Step 1; read `docs/memory/platform/image-*.md` and `get_file.php:1015-1097`.
