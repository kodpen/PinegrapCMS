---
name: image-editor-steps-2
description: Steps E11-E20 of plans/image-editor.md: versions, canvas and artboard, optimisation, pen, SVG, Workspace, designer, access, close-out; claim as [plan:image-editor] E<n>
---

## Which steps are there?
- Sizes and screens as in `image-editor-steps.md`. E11's table is settled (#4202, `image-editor-server.md`); E12 needs E11 for editable texts.

- E11 (M) Versions. Screen 10. `free` Done (#4483, `ui/image-editor-versions.md`).
  - A sub-step in the open version's migration with `install_create_table()`, rerun twice on a dev DB; a save writes a version and its doc; opening loads the original plus the doc; the versions drawer (preview, restore, delete); retention: the original plus the last 10 saves (#4203).

- E12 (M) Canvas and artboard. Screens 14-17. `free` after E10. Done: part 1 #4634, part 2 #4741 (`ui/image-editor-artboard.md`).
  - New image dialog (square 1080, portrait 1080×1350, story 1080×1920, share 1200×630, banner 1920×600, custom; white, transparent, black, light blue); pictures as layers (Files, upload, paste).
  - Artboard tool: handles on the edges and corners; the artwork stays where it is, the board slides over it; width and height with a nine-point anchor; fit to content or to the selection; Shift keeps the ratio, Alt sizes from the centre. On a photo the same tool extends or trims the canvas with a fill; with it a frame may grow the canvas outward (the Polaroid of screen 08; E8 draws frames on the picture's edge).
  - File Manager "New image" opens it (today `createBlankImage()` sends a white PNG to the old editor).

- E13 (M) Optimisation. Screen 11. `free` after E12. Done (#5030, `ui/image-editor-export.md`).
  - Export panel: format (original, JPEG, WebP, AVIF, PNG from the server's list), quality with the exact size from `estimate`, longest edge (original, 2560, 1920, 1280, 1080), progressive JPEG, PNG colour reduction; size before and after; another format saved as a copy (#4676, 6a).
  - Profile `optimise`: the File Manager's "Export settings…" opens a picture straight on this panel.

- E13b (S) Metadata and profile. `free` after E13.
  - Camera details kept on request without the location (the engine strips all metadata today) and sRGB instead of the kept profile (Imagick only); the metadata note becomes screen 11's checkboxes.

- E14 (M) Vector pen and nodes. Screen 09. Done in #4564 (`ui/image-editor-vector.md`).
  - Bezier pen (click for corners, drag for curves), node editing (move, handles, add, delete, corner or smooth), open and closed paths, "convert to path" for shapes.

- E15 (M) SVG open and save. `free` Done (#4728, `ui/image-editor-svg.md`).
  - Opening an SVG gives editable layers for path, rect, circle, ellipse, line, polyline, polygon, text, g, data-URI pictures and gradients; anything else becomes a locked group kept as written. Saving writes SVG through `pg_svg_sanitize()` (tests with hostile samples); fonts by name, pictures embedded.

- E16 (M) Workspace canvas. `free` after E9 and E11. Done (#4762, `ui/image-editor-workspace.md`); a note's canvas opens again only through its file.
  - "New canvas" in a channel and in notes; saved through `workspace_file_save.php` with its doc; texts edit later; a preview picture in the message.

- E17 (S) Visual designer. `free` Done (#4777, `designer/image-editor-tool.md`).
  - The image widget's "Edit image" opens the modal over the designer; a saved version refreshes the picture (cache key); crop presets from the widget's ratio.

- E18 (S) Access. `free` after E9. Done (#4785, `ui/image-editor-access.md`).
  - Keyboard map and help sheet, names for every control, focus trap, reduced motion, two-finger rotate (the phone layout is E1b).

- E19 SVG animation. AN10 of `image-editor-animation-steps.md` (owner, 2026-11-01).

- E20 (S) Close-out.
  - Lasting decisions to topic files (`designer/image-editor-tool.md` rewritten, a `platform/` file for the save pipeline and one for the SVG sanitizer); the plan files and `plans/image-editor/` deleted.
