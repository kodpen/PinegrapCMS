---
name: image-editor-animation
description: Plan (owner 2026-11-01): image editor animation, layers and scenes that move, saved as GIF, APNG, WebP, MP4 or WebM, a PDF of the scenes or animated SVG; held with plans/image-editor.md
---

## What is the status?
- Held by the holder of `image-editor.md` and run the same way: a coordinator gives a worker only a step line that says `free`, claimed with a draft `[plan:image-editor-animation] AN<n> — <topic>`, and the holder reviews it before it is marked ready (`image-editor-workers.md`: the files a step owns, the browser tries, the review).
- A step that waits on an open question says so in its line.

## What is it?
- Layers move: an entrance, a loop and an exit per layer from a set of presets, with delay, duration and easing, on a timeline the user plays, scrubs and then times on tracks; keyframes for a path of a layer's own come later.
- A document may hold scenes with transitions: an animated post, a short clip or a presentation.
- Outputs: GIF, APNG, animated WebP; MP4 or WebM where the browser can encode video; a PDF with a page per scene; animated SVG (what E19 was). A still save keeps working: every layer at rest.
- The document stays in the file's versions (E11), so a later open edits layers and motion, never frames.

## Which decisions bind it?
- Owner, 2026-11-01 (`workflow/decisions-made-product-16.md`): animation saved as GIF or another format, presentation and video where possible. It widens 2026-10-31's list, which had no video, and #4203's SVG-only animation.
- From `image-editor.md`: own code, no outside engine or library (encoders and muxers too); labels through `lang()`; the server does what it does well (the still base: geometry, colour, redaction), the browser the rest (frames, encoding).
- No schema change: motion and scenes live in the document a version row keeps (at most 1 MB, no `data:` picture; `image-editor-server.md`).

## Which files hold the rest?
- `image-editor-animation-design.md`: motion fields, presets, `M.motionAt()`, the preview, the frame renderer, the base.
- `image-editor-animation-output.md`: the encoders and their limits, the save into Files, re-editing, scenes and Present.
- `image-editor-animation-steps.md`: steps AN0-AN11, their order and what runs side by side.

## Which questions are open?
1. Presentation: scenes in the editor with a full-screen Present mode and PDF and video export, or a viewer on the live site as well (a share link, a page widget). AN7 and AN8 wait for the answer.
2. Sound: silent videos, or a music track chosen from Files. Videos are silent until it is answered; a yes adds a step.
- Not in this plan: a video widget for the designer (none exists); a saved video is a file in Files, linked or downloaded.

## Where does it start?
- AN0, the holder's screens, then AN1. AN1 to AN3 change no save-path code; AN4 joins the save-path queue after E13.
