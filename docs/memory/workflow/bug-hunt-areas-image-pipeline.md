---
name: bug-hunt-areas-image-pipeline
description: The bug hunt of the image pipeline (2026-11-24, #11449): the two faults fixed, the finding left for a decision, what was read clean
---

## Which area was hunted?
- Image pipeline: uploads and their made sizes (`includes/image_engine.php`, `get_file.php`, `includes/fn/image.php`, `includes/image_negotiation.php`, `pg_file_store_upload()`, `includes/explorer/handle_upload.php`, `handle_files_media.php`, `optimize_image.php`, API `files.php`).
- Asked for: very large, CMYK, animated or EXIF-rotated pictures, a wrong extension, made sizes deleted with the original, a replaced picture's old sizes from a cache, the width and height cache, WebP/AVIF for a browser that cannot show them, memory and time limits.

## What did the hunt's PR fix?
- `pg_image_ensure_memory()` passed any size while `memory_limit` was `-1` (set by the File Manager's Optimize and Modify Selected, the default of php-cli without a php.ini): the 1 GB ceiling is checked first (`platform/image-processing-engine.md`).
- The width and height cache took the sideways stored size of an EXIF-rotated photo from four writers while a written file gave the upright one; `pg_image_display_size()` is used by all (`platform/image-settings-and-product-upload.md`). Edit File's size label follows it.
- Tests: `tests/image_pipeline_test.php`.

## What was left for a decision (PR body)?
- A picture replaced under its name (Artifact Canvas Replace) keeps its address and its derivative addresses (`photo.jpg?w=480`), answered `public, max-age=604800`: a visitor's browser and an edge cache show the old pixels on pages for up to a week. The panel stamps its pictures (`platform/panel-picture-change-stamp.md`); stamping page markup touches the page cache and every published page.

## What was read clean?
- CMYK JPEG through GD (colours kept) and Imagick (converted only when CMYK); grey JPEG; 16-bit PNG; orientation 5–8 in both halves; animated GIF/APNG/WebP told by bytes.
- Wrong extension: a PNG or WebP named `.jpg` gets derivatives in its real format (sent under the name's type, which browsers sniff); an AVIF or other unread type gets `.skip` and the original; `pg_img_file_name()` gives `.avif` and `.gif` no responsive markup.
- Made sizes after delete, rename, optimize, rotate, replace: folder by file id, signature by mtime, size and quality; the daily sweep removes old signatures and gone files.
- WebP/AVIF: negotiation needs `image/webp` with q > 0 (`image/*` never counts), `Vary: Accept` on every answer, AVIF falls back to WebP, `<source>` only for formats written and offered.
- Time limits: 120 s on derivatives, uploads and the cap, 300 s on Optimize, 600 s on the File Manager batch.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/platform/image-derivatives.md
