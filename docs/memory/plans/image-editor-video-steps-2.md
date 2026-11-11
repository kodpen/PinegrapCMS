---
name: image-editor-video-steps-2
description: More steps of plans/image-editor-video.md, beside image-editor-video-steps.md: V7b the clip's frames in the export, V8b the largest upload as a setting; claim as [plan:image-editor-video] V<n>
---

## Which steps are there?
- Steps added after `image-editor-video-steps.md` reached its size limit. The sizes (S, M), what a step owns and the `free` mark are as that file says.

- V7b (S) The clip's frames in the export. `free` after V3b; one at a time with V5 and V10 (the `clip` part) and before them: until it lands an exported video shows a still.
  - Today a save and every frame of an animation's export paint the clip's source in (`clipReady()`, `clipPaint()`: `ui/image-editor-clip.md`, `ui/image-editor-mix.md`).
  - The export's frame loop (`renderFrame()`, the `render` part) waits for each video layer's frame of that moment (`M.clipAt()`, the source's `frameAt()`) and paints it: the clip's trim and speed, nothing off its span. A still save keeps the source in.
  - Touches `render` beside V8's save hook. Tests: the source time of an export frame (trim, speed, off the span). In a browser: an exported MP4 and WebM plays the clip's own frames in step with its sound.

- V8b (S) The largest upload as a setting. Done (#8635).
  - Owner (2026-11-11, #8587): one setting in megabytes, 0 keeps PHP's `upload_max_filesize`. Kept as the `config_text` row `image_editor_upload_max_mb`, no schema step (#8637: the `config` row is full, `data/config-table-no-varchar.md`; `workflow/decisions-made-schema-15.md`).
  - `pg_image_editor_upload_limits()` answers the setting as the largest file when it holds more than 0, never below one request; the part size stays. One bound for an uploaded video or sound and a saved animation.
  - The field "Largest Video Or Sound In The Image Editor (MB)" in Settings, Features, beside "Shrink Pictures On Upload Above" (`platform/image-upload-cap.md`); the highest value it takes is the step's call.
  - Tests: the largest file from the setting (0, a value, a value under one request). Topic `ui/image-editor-upload.md`.
