---
name: bug-hunt-areas-image-pipeline
description: The bug hunt of the image pipeline (2026-11-24): uploaded pictures and their made sizes; what was fixed, left for a decision, read clean
---

## Which area was hunted?
- Image pipeline: uploads and their made sizes (`includes/image_engine.php`, `get_file.php`, `includes/fn/image.php`, `includes/image_negotiation.php`, `add_file.php`).
- Asked for: very large, CMYK, animated or EXIF-rotated pictures, a wrong extension, made sizes deleted with the original, a replaced picture's old sizes from a cache, the width and height cache, WebP/AVIF for a browser that cannot show them, memory and time limits.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/platform/image-derivatives.md
