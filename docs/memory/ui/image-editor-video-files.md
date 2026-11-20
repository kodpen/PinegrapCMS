---
name: image-editor-video-files
description: Artifact Canvas on a video in Files (FV1, #9775): offered by type on any MP4 or WebM, a saved project opens as itself, a plain video as a new canvas holding it, saved beside it
module: image_editor
---

## Why does Files offer it?
- Owner (2026-11-15, agent channel message 5103): he looked for video editing in the File Manager and found none (`workflow/decisions-made-product-56.md`). The video plan (V0 to V11, closed by #9234) took a video only through Import inside the canvas; no step gave Files a door. FV1 (#9775) added it.

## Where is it shown?
- On every MP4, M4V or WebM (`pg_image_editor_clip_types()`) the user may edit: in the context menu by type alone (`itemCanvasVideo()` in `view_folders.php`, no query per file), after Download; in the file window when the file is on disk, first among its tools. The label is "Artifact Canvas", no text of its own. Before FV1: pictures and saved projects only.

## What does a click open?
- It asks the file window's answer (`explorer_file_get`, one file). `editor_project` (newest version accepted by `pg_image_editor_video_project()`) opens the video as its project (`image-editor-video-reopen.md`); the open checks the bytes.
- Any other video opens a new canvas (`openVideoCanvas()`: `blank` with `clip: { id, name, url }`, `save: { mode: 'new', folderId: <the video's folder>, name: <its name> }`). The editor's `clipOpen()` decodes it, sizes the canvas to it (`M.clipCanvas()`: its own size, scaled down as a whole past 8000 px; not an undo step), skips the New image dialog, opens Animate and adds the video as `clipAdd()` does, one clip from 0, selected.
- A codec the browser cannot decode (H.265 in headless Chromium) gets Import's message (`clipSource()`); the empty canvas stays open.

## Where does a save go?
- Save to Files and the canvas's Save write new, uniquely named files into the video's folder (`get_unique_name()`): `beach-clip.mp4` gives `beach-clip[1].mp4`. Never over the plain source; a later Save to Files may replace that new file.

## How was it checked?
- `tests/image_editor_clip_test.php` (`M.clipCanvas()`), `tests/image_editor_reopen_test.php` (the wiring). The browser run and what it could not try: `image-editor-video-reopen.md`.
