---
name: files-video-edit
description: Plan (owner 2026-11-15, agent channel message 5103): Design Canvas on a video in Files, the door the finished video plan left out; one step, FV1
priority: 6
---

## What is asked?
- Owner (2026-11-15, agent channel message 5103): "tasarım tuvalinde video düzenlenir olacaktı ama o adımlar yarım kaldı nedenini bulurmusun. bugün dosya yöneticide video düzenle seçeneği yok."
- The cause: the video plan (`image-editor-video`, V0 to V11) was complete and closed by #9234 (2026-11-13). It took a video only through Import inside the Design Canvas; no step gave Files a door.
- Files shows Design Canvas on pictures (`item.is_image` in the context menu of `view_folders.php`) and, in the file window, on a video only when the canvas itself saved it (`file.editor_project`, `ui/image-editor-video-reopen.md`).

## Which step is there?
- FV1 (S to M) Design Canvas on a video in Files. Given 2026-11-15 (agent channel message 5116), `@elcin`.
  - The context menu and the file window's tools show Design Canvas on an MP4 or WebM on disk the user may edit. By type: no query per file in the lists.
  - A video the canvas saved opens as its project, as today. A plain video opens a new canvas of the video's own size in the video's folder, with the video as its one clip from 0 and the Animate tool open (`clipAdd()`, the source by its file id as Import > From the site does).
  - Save to Files writes a new file beside the source, never over the plain source. A video the browser cannot decode says what Import says.
  - The label stays "Design Canvas": no new text.
  - Tests for the pure parts, and a browser run, which also tries what no real browser tried: MP4 output with sound (V9's note) and the J, L, I, O keys (V11's note).
  - Pictures (the context menu on a video, the file window, the canvas just opened; 1440 and 390) are the architect's look.
- Close-out, in FV1's PR: `ui/image-editor-video-reopen.md` corrected where it says "only on pictures", this plan deleted.
