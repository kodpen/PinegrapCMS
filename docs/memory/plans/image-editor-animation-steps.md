---
name: image-editor-animation-steps
description: Steps AN0-AN11 of plans/image-editor-animation.md: screens, motion, tracks, GIF, saving, APNG, WebP, video, scenes, Present, PDF, keyframes, SVG; claim as [plan:image-editor-animation] AN<n>
---

## Which steps are there?
- S under 400 product lines, M about 1,000. A step owns its part `assets/js/pg_image_editor_<part>.js`, its model section, labels function, stylesheet section, test and memory topic (`ui/image-editor-parts.md`). Steps run side by side only where a line says so.

- AN0 (S) Screens. The holder. Done (#4732, `image-editor-animation-visuals.md`).
  - The Animate panel and play bar (desktop and phone), the Save animation dialog with its progress, the scene strip and Present, as `plans/image-editor/26-*.jpg` to `32-*.jpg`.

- AN1 (M) Motion and preview. Done (#4831).
  - Part `motion` (an Animate tool in the photo and canvas rails), section `MOTION` with `M.motionAt()`; per layer in, loop and out presets with delay, duration, ease and direction; the document's duration, loop and base move; the play bar (play, pause, scrub, time); the `<g>` per layer in `renderLayers()` and the text, draw and vector states.

- AN2 (M) Tracks. Done (#5062, part `tracks`, `ui/image-editor-tracks.md`).
  - A row per layer under the stage with its bar from the start of in to the end of out; drag the bar or an end; snapping to 0.1 s and to other ends; on a phone the panel's fields only.

- AN3 (M) Frames and GIF. Done (#5148, part `render`, `ui/image-editor-render.md`).
  - Part `render`: `renderFrame()` and `paintLayers()` with a time; action `base` (calls `pg_image_editor_render()`, writes nothing); the encoder worker with GIF; the dialog with estimate, progress, Cancel and Download.

- AN4 (M) Save into Files. Done (#6229, `ui/image-editor-animation-save.md`).
  - Left by AN3: the upload limit beside the estimate (`pg_upload_limits()` in the config) and a smaller size or rate offered when over it; Save to Files beside Download.
  - Action `animation`, the type by its bytes, a new file and its version with the document, reopening it, the same-format replace, `.webm` in the MIME map.

- AN5 (S) APNG and animated WebP. Done (#6228, `ui/image-editor-encode.md`).
  - In the worker; the dialog's formats.

- AN6 (M) Video. Done (#6314, `ui/image-editor-video-export.md`).
  - WebCodecs with the MP4 and WebM muxers, the MediaRecorder fallback, the limits.

- AN7a (M) Scenes. Done (#6409, `ui/image-editor-scenes.md`).
  - `doc.scenes` on a canvas, the strip under the play bar, the scene panel (duration, transition and its time, duplicate, delete, move), transitions in Save animation's frames across scenes, the validator's cap on scenes and their layers, a canvas animation tied to its canvas (#6229 review).

- AN7b (S) Scenes on the stage. Done (#6491, `ui/image-editor-scenes-play.md`).
  - Play, the scrub and the clock across every scene (the stage opening each scene as the playhead reaches it, the transition shown there), then back to the scene that was open; drag a card to reorder; the tracks of the open scene under the whole timeline.

- AN8 (M) Present and PDF. Done (#6637, `ui/image-editor-present.md`).
  - Present mode in the editor (full screen; click, Space, arrow keys, swipe); the PDF writer; video and GIF of all scenes (AN7a). No live-site viewer (question 1).

- AN9 (M) Keyframes. Done (#6290, `ui/image-editor-keyframes.md`).
  - A layer's position, scale, rotation and opacity at chosen times (diamonds on its track), eased between; moving a layer with the playhead away from 0 sets one; the path shown on the stage.

- AN10 (M) SVG motion. Done (#6349, `ui/image-editor-svg-motion.md`).
  - E19's content (`image-editor-animation-output.md`): the presets as `@keyframes` in the saved SVG.

- AN11 (S) Access and close-out. The holder.
  - Keys on the timeline (K, Home, End, arrows by frame, with Shift by second; Space pans the view), names, a warning for a loop longer than 5 s (WCAG 2.2.2); lasting decisions to topic files and these plan files deleted.
