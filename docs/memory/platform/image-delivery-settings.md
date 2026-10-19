---
name: image-delivery-settings
description: Image delivery settings (2026.11.0 step 11.0.9): formats offered (bit set) and quality per format, where they are read, one clamp, what a change does to derivatives and pages
---

## What can the operator set?
- Site Settings › Features › Image Optimization (the existing image card, not Appearance: every image setting in one card).
- `config.image_derivative_formats`: bits 1 WebP, 2 AVIF, default 3. A number because `config` takes no VARCHAR (InnoDB row, `tests/innodb_test.php`).
- `config.image_quality_webp` (80, 40–100) and `image_quality_avif` (50, 20–100). Resized JPEG/PNG derivatives use `image_resize_quality` (2026.4.4).
- Hidden until the columns exist (`pg_image_delivery_ready()`); the defaults are what the code did before the settings.

## Where are they read?
- One clamp: `pg_image_delivery_settings($row)` in `includes/image_engine.php` (pure, so get_file.php can call it). `pg_image_settings()` adds `derivative_formats`, `quality_webp`, `quality_avif` and `delivery`; `pg_image_delivery()` returns the last.
- get_file.php does not load functions.php: it reads the four columns with its own query before it closes the connection, only for `?w=`/`?f=`, and passes the result to `pg_image_derivative()`. Before the upgrade the query fails (false, mysqli reporting is off in router.php) and the defaults apply.
- `pg_image_derivative_offered($writable, $settings)`: written by the server AND switched on. Used by `pg_image_derivative()` (a format switched off is answered like one that cannot be written: AVIF → WebP → source format), `pg_image_delivery_formats()` for the markup's `<source>`s, and the System Status storage label.

## What does a change do?
- Quality: it is in the derivative's signature, so each picture is made again on its next request; the old file of that width and format is pruned then, the rest by the daily sweep (`pg_image_derivative_stale_entries(..., $settings)`).
- Formats: the sweep deletes conversions to a format switched off (not resized copies of a file already in it). The page HTML is rebuilt in the same save, because the Image elements' `<source>`s list the formats: docs/memory/designer/page-html-rebuild.md.
