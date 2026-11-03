---
name: image-editor-steps-2
description: Steps E11-E20 of plans/image-editor.md: versions, canvas and artboard, optimisation, pen, SVG, Workspace, designer, access, close-out; claim as [plan:image-editor] E<n>
---

## Which steps are there?
- Sizes and screens as in `image-editor-steps.md`. A done step keeps one line; what it built is in the topic file it names.

- E11 (M) Versions. Screen 10. Done (#4483, `ui/image-editor-versions.md`).
- E12 (M) Canvas and artboard. Screens 14-17. Done: part 1 #4634, part 2 #4741 (`ui/image-editor-artboard.md`).
- E13 (M) Optimisation. Screen 11. Done (#5030, `ui/image-editor-export.md`).
- E13b (S) Metadata and profile. Done (#5099, `ui/image-editor-export.md`).
- E14 (M) Vector pen and nodes. Screen 09. Done (#4564, `ui/image-editor-vector.md`).
- E15 (M) SVG open and save. Done (#4728, `ui/image-editor-svg.md`).
- E16 (M) Workspace canvas. Done (#4762, `ui/image-editor-workspace.md`); a note's canvas opens again only through its file.

- E16b (S) Pictures in a Workspace canvas. `free`.
  - A Workspace canvas with a pasted or uploaded picture keeps no document: `pg_file_versions_doc_storable()` refuses a `data:` picture, and `ws_canvas_keep()` has no `pg_image_editor_pictures_saved()` as Files has (E12 part 2). It opens again as a flat picture.
  - Keep each such picture as a file beside the canvas's own file, under the same access, point its layer at it, then keep the document. Test: a canvas with a pasted picture, sent and opened again, gives the picture back as a layer.

- E17 (S) Visual designer. Done (#4777, `designer/image-editor-tool.md`).
- E18 (S) Access. Done (#4785, `ui/image-editor-access.md`).
- E19 SVG animation. AN10 of `image-editor-animation-steps.md` (owner, 2026-11-01).

- E20 (S) Close-out.
  - Lasting decisions to topic files (`designer/image-editor-tool.md` rewritten, a `platform/` file for the save pipeline and one for the SVG sanitizer); the plan files and `plans/image-editor/` deleted.
