---
name: image-editor
description: Plan (owner 2026-10-31): Pinegrap's own image editor, a core modal with full screen on demand, replacing the bundled third-party one; held by one session; steps E1-E20
---

## What is the status?
- Held (owner, 2026-10-31): the session the owner handed it to works the steps and claims each with a draft PR `[plan:image-editor] E<n> — <topic>`. Coordinators skip this plan and give no step of it to a worker; a step line that says `free` may be taken by anyone. If the holder stops, the owner names the next one here.
- Every UI step is tried in a browser (pointer and drawing paths are out of a unit test's reach); its PR says what to try.

## What is it?
- One editor for the whole software, written here with no outside engine: photo work (crop, rotate, flip, straighten, resize, adjust, filters, frames), marking (arrows, ready-made shapes, pen, text, stickers), redaction, vectors and SVG, a blank canvas with Illustrator-like artboard sizing, and optimisation on save.
- A core part: an in-page modal with a full-screen button, opened from any screen by one call; a caller may limit the tools (arrows only). The full-page editor `image_editor_edit.php` retires.
- Texts and shapes stay editable: a save keeps its document (versions table), so a later open edits layers, not pixels. That makes it a canvas too: File Manager "New image", Workspace canvases.
- It replaces the bundled third-party editor (`assets/lib/image_editor/`, 2.3 MB), removed with its loader in E9.

## Which files hold the rest?
- `image-editor-visuals.md`: the prototype screens the owner approved, in `plans/image-editor/`, and the prototype's source. Look at the screen of a step before starting it; the work follows them.
- `image-editor-design.md`: parts, the call, the look, the document model.
- `image-editor-server.md`: browser and server work, the save pipeline, versions, security.
- `image-editor-steps.md` (E1-E10) and `image-editor-steps-2.md` (E11-E20).

## Which decisions bind it?
- Owner, 2026-10-31 (`workflow/decisions-made-product-13.md`; the table in `decisions-made-schema-7.md`): own code, no engine; core modal with full screen; callable with a tool subset; texts through `lang()`; work on the server where it is efficient, else in JS; a versions table; no video; optimisation inside; ready-made shapes and corner rounding; artboard sizing; File Manager "New image" opens the canvas.

## Where does it start?
- E1, after the visuals and the two design files.

## Which questions are open? (recommendation first)
1. Redacted save: replace in place, delete every version that holds unredacted pixels, strip metadata; "Save as copy" keeps the original.
2. Retention: the original plus the last 10 saves per file; deleting the file deletes them; they count in disk usage.
3. The document's home: a column of the version row, or a file under `data/`.
4. The live site's edit button (`includes/fn/content.php`, `frontend.src.js`): the modal on the page, loaded only for a signed-in editor, or a jump to the File Manager.
5. SVG animation (E19): CSS keyframe presets per layer in the saved SVG, or none.
- A step that meets one asks it through the architect (CLAUDE.md Working mode 10) unless an answer stands here.
