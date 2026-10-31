---
name: image-editor-server
description: Server side of plans/image-editor.md: what the browser and the server each do, the save pipeline through the image engine, replace, copy and new, the versions table proposal, security
---

## Who does what?
- Owner rule (2026-10-31): the server does what it does well; what would be slow there is written in JS.
- Browser: all interaction and preview; the overlay (shapes, text, stickers, frames, inserted pictures) drawn at output size into a transparent PNG; on a server without Imagick also the colour step (GD would loop over the pixels in PHP).
- Server: the original's pixels. The browser sends the recipe and the overlay, never the result picture, so a 48 MP phone photo is edited on a preview and rendered at full size by the server.

## What does a save run?
- `pg_image_edit_apply()` in `includes/fn/image_editor.php`; pixels only through `includes/image_engine.php` (`pg_process_image_file()`, `pg_image_ensure_memory()`), in this order:
  1. read the original: EXIF orientation, CMYK to sRGB, ICC kept, as uploads do (`platform/image-processing-engine.md`, `image-color-profile.md`);
  2. quarter turns, flips, straighten (cover scale), crop, crop shape mask (rounded, circle), resize to the output size;
  3. colour (Imagick: colour matrix, gamma, vignette, unsharp mask) or the browser's colour layer;
  4. redaction (pixelate, blur, solid) burned in;
  5. overlay composite; artboard extension with its fill;
  6. encode: format, quality, longest edge, metadata, colour profile; `optimized = 1`; derivatives follow the new file (`platform/image-derivatives.md`).
- Parity: the adjustments are one colour matrix plus gamma; the preview (SVG `feColorMatrix`, `feComponentTransfer`) and Imagick read the same numbers, and a test holds them.

## What do replace, copy and new do?
- Replace keeps the `files` row's id and name, so every link keeps working; copy and new add a row in the chosen folder (name, folder, type, size, image_width, image_height, user, design, optimized, timestamp).
- Animated files: replace refused, copy allowed (`platform/image-files-left-alone.md`); ERP rows refused (`platform/erp-documents-in-file-manager.md`).

## What does the versions table hold? (E11; columns through an architect question)
- Proposal `file_versions`: id, file_id, version (1 = the original), doc (MEDIUMTEXT JSON), stored_name (pixels under `data/file_versions/`), width, height, size, redacted, created_at, created_by; KEY (file_id, version).
- Opening loads the original and the newest doc, so layers and texts edit again; restore makes a version current; deleting the file deletes its versions.

## What does security need?
- Each call: the session token check `image_editor_save.php` does today, `check_edit_access()` on the target folder, the doc validated against limits (sizes, counts, number ranges) before any pixel work, the overlay PNG decoded and its size checked.
- SVG: an allowlist sanitizer `pg_svg_sanitize()` (listed elements and attributes; `href` only `#id` or `data:image/*`; no script, event handlers, `foreignObject`, external URLs, `@import`). The server never rasterises SVG: GD cannot, and Imagick's SVG readers are missing on many hosts and have a bad record.
- A redacted save strips all metadata: an EXIF thumbnail can hold the unredacted picture.
