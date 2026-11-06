---
name: image-editor
description: Plan (owner 2026-10-31): Pinegrap's own image editor, a core modal with full screen on demand, replacing the bundled third-party one; held by one session; steps E1-E29
---

## What is the status?
- Held (owner, 2026-10-31): the session the owner handed it to keeps it and claims its own steps with a draft PR `[plan:image-editor] E<n> — <topic>`. Coordinators give a worker only a step line that says `free`; since 2026-11-01 (owner) the steps go to workers that way and the holder reviews each before it is marked ready (`image-editor-workers.md`). If the holder stops, the owner names the next one here.
- Every UI step is tried in a browser (pointer and drawing paths are out of a unit test's reach) at desktop size and on a phone (390×844 portrait, 844×390 landscape, touch); its PR says what to try on both.

## What is it?
- One editor for the whole software, written here with no outside engine: photo work (crop, rotate, flip, straighten, resize, adjust, filters, frames), marking (arrows, ready-made shapes, pen, text, stickers), redaction, vectors and SVG, a blank canvas with Illustrator-like artboard sizing, and optimisation on save.
- A core part: an in-page modal with a full-screen button, opened from any screen by one call; a caller may limit the tools (arrows only). The full-page editor `image_editor_edit.php` retired in E9 (a redirect now).
- Texts and shapes stay editable: a save keeps its document (versions table), so a later open edits layers, not pixels. That makes it a canvas too: File Manager "New image", Workspace canvases.
- It replaced the bundled third-party editor (`assets/lib/image_editor/`, 2.3 MB), removed with its loader in E9 (#4609, `ui/image-editor-callers.md`).

## Which files hold the rest?
- `image-editor-visuals.md`: the prototype screens the owner approved, in `plans/image-editor/`, and the prototype's source. Look at the screen of a step before starting it; the work follows them.
- `image-editor-design.md`: parts, the call, the look, the document model.
- `image-editor-server.md`: browser and server work, the save pipeline, versions, security.
- `image-editor-steps.md` (E1-E10), `image-editor-steps-2.md` (E11-E20), `-steps-3.md` (E21-E25), `-steps-4.md` (E26-E29) and `-steps-5.md` (E23b, E30-E32); `image-editor-workers.md`: which steps run side by side, a step's files, the review.
- `image-editor-video.md`: the plan held with this one. The animation plan is done (`ui/image-editor-animation.md`).

## Which decisions bind it?
- Owner, 2026-10-31 (`workflow/decisions-made-product-14.md`; the table in `decisions-made-schema-7.md`, the five answers of 2026-10-31 in `decisions-made-product-15.md`): own code, no engine; core modal with full screen; callable with a tool subset; texts through `lang()`; work on the server where it is efficient, else in JS; a versions table; optimisation inside; ready-made shapes and corner rounding; artboard sizing; File Manager "New image" opens the canvas.
- Owner, 2026-11-01 (`decisions-made-product-16.md`): layers animate and save as GIF or another animated format, with presentation and video where possible: done, `ui/image-editor-animation.md`.
- Owner, 2026-11-03 (`decisions-made-product-24.md`): new tools (E21-E29: sticker change, outlines, scissors, warp, shape builder, deformation, selection, background removal with an own algorithm) and video editing inside the editor (`image-editor-video.md`).

## Where does it start?
- E1-E18 are done; the core and the tools are in parts (`ui/image-editor-parts.md`). Next: the `free` lines, the small tools first (E21-E23).

## Which questions were answered?
- #4202 and #4203 (2026-10-31): the redacted save, retention, the document's home, the live site's edit button and SVG animation (`decisions-made-product-15.md`, `image-editor-server.md`).
- A new question goes through the architect (CLAUDE.md Working mode 10).
