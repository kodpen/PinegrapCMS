---
name: image-editor-animation
description: The image editor's animation as a whole: what binds it (own code, no schema change, editor-only presentation), the AN step ids and their topic files, the timeline keys and the WCAG 2.2.2 note
module: image_editor
---

## What is it?
- Layers move (in, loop and out presets, keyframes set one by one or recorded by dragging), on a timeline played, scrubbed and timed on tracks; a canvas may hold scenes with transitions. Saved as GIF, APNG, animated WebP, MP4 or WebM, a PDF of the scenes, or animated SVG; a still save draws every layer at rest.
- The document stays in the file's versions, so a later open edits layers and motion, never frames.

## Which decisions bind it?
- Owner, 2026-11-01 (`workflow/decisions-made-product-16.md`): animation saved as GIF or another format, presentation and video where possible; it widened the SVG-only animation of #4203.
- Own code, no outside engine or library, encoders and muxers included; labels through `lang()`; the server draws the still base, the browser the frames and the encoding.
- No schema change: motion and scenes live in the document a version row keeps (at most 1 MB, no `data:` picture).
- Presentation is editor only (#4714, `decisions-made-product-18.md`): Present, PDF, video and GIF; no share link and no widget on the live site. No video widget in the designer: a saved video is a file in Files.
- A move recorded by dragging (owner 2026-11-05, `decisions-made-product-31.md`).
- Redaction layers never move: one moving away in an export would show what it hides.

## Which step id is in which topic file?
- Other plans and PRs name the steps by id: AN1 motion and the play bar `image-editor-motion.md`, AN2 tracks `image-editor-tracks.md`, AN3 frames and GIF `image-editor-render.md`, AN4 save into Files `image-editor-animation-save.md`, AN5 APNG and WebP `image-editor-encode.md`, AN6 video `image-editor-video-export.md`, AN7a scenes `image-editor-scenes.md`, AN7b scenes played `image-editor-scenes-play.md`, AN8 Present and PDF `image-editor-present.md`, AN9 keyframes `image-editor-keyframes.md`, AN10 SVG motion `image-editor-svg-motion.md`, AN12 recording `image-editor-record.md`, AN11 this file.
- The screens of 2026-11-01 and 2026-11-05 are `docs/memory/plans/image-editor/26-*.jpg` to `34-*.jpg` (motion, tracks, save, progress, scenes, Present, phone, recording), kept with the image editor plan's screens.

## Which keys does the timeline answer?
- In the Animate tool (`motionKey()`): K plays and pauses; Left and Right step the playhead one frame of 30 fps, Shift one whole second, landing on that grid; Home goes to the start, End to the end; each pauses as a scrub does (`M.timelineKey()`, tested in `tests/image_editor_access_test.php`). Up and Down still nudge the selection; Space and drag pan the view as in every tool; a running recording takes none of them.
- A focused scrub slider keeps its own arrows (10 ms steps, `ownsKey()`). The `?` sheet shows a Timeline group when the call has the Animate tool (`M.keyMap()`); Play carries `aria-keyshortcuts="K"`.

## Which names does it give?
- Every track row is a group named after its layer, each bar an image named by its kind and times, each keyframe diamond a button named by its time; the play bar, the scrub (with its time as `aria-valuetext`), the tracks and the scene strip are named regions.

## Why the 5-second note?
- WCAG 2.2.2: moving content that lasts more than 5 seconds beside other content needs a way to pause it, and a GIF, APNG or WebP has none. The panel's whole-animation section shows a note when the play time (all scenes, times the repeat count; forever always) passes 5 s and anything moves (`M.motionLong()`, `motionMoves()`). It warns only; the save is not blocked.
