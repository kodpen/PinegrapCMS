---
name: image-editor-animation-save
description: Saving an animation into Files (AN4): Save to Files, the upload limit, action animation (binary body, type by bytes), its version with the document, reopening, same-format replace
module: image_editor
---

## What does the dialog add?
- Save to Files beside Download (`pg_image_editor_render.js`). Under the estimate the server's limit (`animationLimit` in the config: the largest file, `pg_image_editor_upload_limits()`; a file over one request goes in parts, `image-editor-upload.md`); over it Save to Files waits and `M.animSmaller()` offers the largest smaller edge or rate that fits with a tenth spare (the size scales with area and frame count). Download still works.
- An animation the editor opened (the open answer's `animation`) is replaced when the format matches and it may be written over (`M.animSaveMode()`), behind a checked "Replace {name}" box; else a new file. After a new save the next one replaces it.
- While the file is sent Cancel and Escape wait: it is written whatever the dialog does.

## How is the file sent?
- One body (a file over one request: in parts first, then the request with its upload id, `image-editor-upload.md`): the request as one line of JSON, a line end, the file's bytes (`pg_image_editor_binary_body()`); `pg_image_editor_api()` splits only when the whole body is no JSON, and hands the bytes in `$request['binary']`. No base64.
- Action `animation` (`pg_image_editor_action_animation()`): the format from the bytes (`pg_image_editor_animation_type()`: GIF, APNG by an acTL before IDAT, animated WebP by VP8X's flag, MP4 `ftyp`, WebM by its EBML doctype, PDF), never the name; one body over one request is refused (413).
- New: beside the source in its folder (its stem, the format's extension, unique name), or a canvas's folder (a saved canvas through `pg_image_editor_file()`'s checks, or `save.folderId`); the bin and folder 0 refused. Replace: only an animation the editor made, from the same source (a saved canvas is its source), in the same format. The document passes `pg_image_edit_validate()` first.
- `files.type` is the extension; `.webm` and `.mp4` were already in `get_file.php`'s MIME map, which answers ranges.

## What does its version keep?
- The file's own row, `source = 'animation'` (`pg_image_editor_animation_source()`; `pg_file_versions_add()` with the format, so a video or PDF is kept without a size): the document with `animation: {source, base, format}` (`pg_image_editor_animation_doc_json()`); `base` is the version_no of the source's base row, made by `pg_file_versions_before_save()` when the browser drew on the file itself. Not written into `source.base`, which names a version of the row's own file.
- Only that marker counts: a still or canvas save drops a browser's `animation` member (`pg_image_editor_animation_strip()`), else a user who may edit a file could name another's picture as its source and have a privileged opener save its frames into it.

## How does it open again?
- `pg_image_editor_animation_open()` before the SVG and still paths: while the file holds its newest version's bytes and that document names an animation, the answer is the source file (its url, base, versions) with the animation's document, `file.replace` false (a still save is a copy), a notice naming both, and `animation` {id, name, format, replace}. A canvas animation opens as the canvas it was made from (`ui/image-editor-scenes.md`). Once the source's base row is pruned it opens as before: a still copy.
