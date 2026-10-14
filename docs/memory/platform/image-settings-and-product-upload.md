---
name: image-settings-and-product-upload
description: Image config keys and defaults, product upload pipeline (image_profile=product), files.image_width/height cache rule, optimizable-percent badge cost.
---

## How are image settings read and what are they?
- `pg_image_settings()` / `pg_image_settings_ready()`: same pattern as `pg_page_noindex_ready()`. Probe ALL SIX columns at once (the upgrade runs six separate `ALTER`s). If columns are missing, embedded defaults apply and the Settings block is hidden.
- `image_product_optimize` = 1: product upload pipeline on.
- `image_product_max_dimension` = 2400: product photo long-edge ceiling.
- `image_product_min_dimension` = 500: warning threshold (Merchant Center 2027).
- `image_file_resize_trigger` = 2560: the resize button on files appears above this.
- `image_file_max_dimension` = 1920: resize target.
- `image_resize_quality` = 82: only when actually shrinking. Plain optimize keeps the old size ladder (85/75/65/60).

## How does the product upload path work?
- `add_file.php` runs the pipeline only with the opt-in field `image_profile=product`, sent ONLY by the product and variant-set screens. Every other upload (file manager, picker dropzone, `editor_select_image.php`) is stored byte-for-byte.
- The record row is written at upload time with `optimized = 1` + `image_width/height`, so the Files screen shows no badge promising a gain already taken.
- An image picked from the library is never touched (it may be used on a dozen other pages). The only warning point is the product's SEO Detail panel (`pg_seo_render_catalog_image_note()`), and it does NOT affect the score (Merchant Center is not mandatory).

## What is the rule for files.image_width / image_height?
- They are a cache, filled only when empty (`pg_file_image_dimensions()`).
- Any code that changes a file's pixels MUST write both. The rotate branch did not, and a quarter turn swaps width/height, so the list showed the old size. The resize button now reads these columns, so a stale value becomes a visible bug.

## Why is the optimizable-percent badge expensive?
- `calculate_optimizable_percent($path)` does a full decode + recompress.
- `OPTIMIZE_PERCENT_THRESHOLD = 30` (view_files.php): compute only if the unoptimized image count is <= 30; above that show the button without a percentage.
- Dashboard widget (includes/dashboard/widgets/widget_18.php) never computes; it reads only the cached column.
- Supported types: `jpg`, `jpeg`, `png`, `gif`, `bmp`, `tiff`, `tif`, `webp`. `tif` only in the thumbnail/size list: `optimize_image()` rejects writing it, so offering the button guarantees an error.

Related: docs/memory/platform/image-processing-engine.md
