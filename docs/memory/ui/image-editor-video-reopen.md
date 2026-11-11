---
name: image-editor-video-reopen
description: A video project saved and opened again (V9, #8701): clips and sounds by fileId, pictures kept as files, Image Editor on a video in Files, an unsaved canvas's video replaced
module: image_editor
---

## What does V9 add to AN4's save and open?
- AN4 (`image-editor-animation-save.md`) already kept the document in the export's version, reopened it and replaced in the same format. V9 closed four gaps a video project met, found in the code and tried on a sandbox (#8701).

## Why do clips and sounds point at their files again on open?
- A clip's or sound's `src` is the file's address (`PATH` + its name, plus `?v=` from an upload), so a source renamed in Files after the save played nothing ("video-broken"). `pg_image_editor_animation_open()` runs `pg_image_editor_doc_media_fresh()`: one query for the document's video layers (open and closed scenes) and `doc.sounds` with a `fileId`, through `pg_image_editor_sound_where()` (no bin, no ERP document, a role-3 user's folders), then `pg_image_editor_doc_media_named()` writes each found file's address and name. A file not found keeps what was saved. Pictures are left alone.

## How do pictures from this computer reach the kept document?
- A `data:` picture made the document unstorable (`pg_file_versions_doc_storable()`), so the version kept none and the video opened as a plain file. Save to Files now sends them as a still save does: `saveAnimationFile()` adds `pictures` (`M.animPictures()`: PNG or JPEG `data:` layers without a file, every scene), `pg_image_editor_action_animation()` calls `pg_image_editor_pictures_saved()` before `pg_image_editor_animation_doc_json()`, and the answer's `pictures` repoint the layers (`M.picturesTaken()`, also behind the canvas's `takePictures()`). `pg_image_editor_pictures_saved()` now repoints closed scenes' layers too.

## How does Files open a video project?
- Files offers Image Editor only on pictures (`is_image`). The file window (`handle_file_window.php`) sets `editor_project` for an MP4 or WebM on disk whose newest version `pg_image_editor_video_project()` accepts (source `animation`, document naming mp4 or webm); `view_folders.php` then shows Image Editor among its tools. One version read for the one file opened; the lists and the context menu stay without a query per file. The open itself checks the bytes (`pg_image_editor_animation_file()`).

## Why could a video made on an unsaved canvas not be replaced?
- It opens as its own canvas, so the editor names the video itself as `file_id`. Action `animation` sent that through `pg_image_editor_file()`, whose picture check refuses an MP4 or WebM ("This file cannot be opened in the image editor."); a GIF passed. Now an animation that names itself as a canvas (`pg_image_editor_animation_file()` with a canvas document) takes its own row, folder and source; the ERP check stays, the bin and edit access are checked on its folder as before.

## What was tried and what was not?
- On a sandbox (#8701): a canvas with two clips in two scenes, effects and transitions, a detached sound, music and a picture from this computer saved as WebM; the source renamed; reopened from the file window; replaced by a VP9/Opus WebM the editor encoded; a picture imported and saved again; reopened with three layers and three versions; a plain WebM shows no Image Editor; the window at 390 px.
- Not tried: MP4 from the editor, since headless Chromium has no AAC encoder.
- Tests: `tests/image_editor_reopen_test.php`.
