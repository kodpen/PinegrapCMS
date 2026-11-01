---
name: image-editor-design
description: Design of plans/image-editor.md: the parts and file names, the one call and its options, the modal's look, the document model with layers in rotated space
---

## Which parts are there?
- The model, the core `assets/js/pg_image_editor.js` and one file per tool (`ui/image-editor-parts.md`) bundled to `pg_image_editor.min.js` through `pg_js_bundles()`, `assets/css/pg_image_editor.src.css` built to `.min.css`; a few-line loader printed by `pg_image_editor_includes()` (new module `includes/fn/image_editor.php`) fetches both on the first open.
- `pg_image_editor_strings()` returns every label as `lang('English')`, printed as JSON with the loader; Turkish through `tools/tr_add.php`. The bundle holds no text.
- `image_editor_api.php` (root): `open` (file facts, server capabilities, the saved document), `save`, `estimate`, `versions`, `restore`. Old names are not reused: once E9 removes `image_editor_save.php`, `clean_up.php` may list it again (`platform/clean-up-tool-file-list.md`).
- No jQuery or Bootstrap inside: own `pgie-` classes, colours from the panel's `--pg-*` tokens with fallbacks, so one editor serves panel pages (Bootstrap 6), site pages (5.3.8) and the visual designer.

## How is it called?
- `pgImageEditor.open({src | fileId | blank: {width, height, background}, profile: 'photo'|'annotate'|'canvas'|'optimise', tools: [...], output: {format, quality, maxEdge}, save: {mode: 'replace'|'copy'|'new'|'callback', url, token, folderId}, fullscreen, onSave, onClose})` returns a Promise of the saved file or null.
- `tools` limits the rail; a single tool shows a floating mini bar instead (screens 12, 13).
- `window.openImageEditor()` stays as an alias that maps today's options (fileName, saveUrl, onSaved, allowReplace, newFile, folderId), so callers move in one step (E9).

## What does it look like?
- A `<dialog>` opened with `showModal()`, 84 % × 86 % of the window, with a full-screen button (Fullscreen API, else a fixed full-window class) (screens 18, 19). Under 768 px it opens full screen. Escape leaves full screen first; closing with unsaved changes asks.
- Top bar: close, file name and size, undo, redo, versions, save split button (save, save as copy, export settings), full screen. Left: tool rail. Right: the tool's panel. Bottom: hint line and zoom. Light and dark follow the panel.

## What is the document?
- JSON: source (file id, natural size), geometry (quarter turns, flips, straighten angle, crop box, crop shape, output size), adjustments, filter, frame, artboard (size, background), layers.
- A layer: id, type (arrow, line, rect, ellipse, triangle, polygon, star, bubble, pen, path, text, sticker, image, redact, group, svg), geometry, style (corner radius, sides, points, inner ratio where the type has them), flags (locked, hidden), later `anim`.
- Layers live in rotated space (after quarter turns and flips, before straighten and crop): text stays upright and a crop never moves a layer; the picture alone takes the full transform. One path builder gives the `d` string that both the SVG view and the canvas export draw (`Path2D`); text has one own line layout for both.
- The prototype's source holds this model and its maths (`computeGeo()`, `mapShape()`, `cropReset()`, `regularPts()`, `bubblePath()`, `applyArtboard()`): `image-editor-visuals.md`.
