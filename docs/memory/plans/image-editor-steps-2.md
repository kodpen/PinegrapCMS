---
name: image-editor-steps-2
description: Steps E11-E20 of plans/image-editor.md: versions, canvas and artboard, optimisation, pen, SVG, Workspace, designer, access, animation, close-out; claim as [plan:image-editor] E<n>
---

## Which steps are there?
- Sizes and screens as in `image-editor-steps.md`. E11's table is settled (#4202, `image-editor-server.md`); E12 needs E11 for editable texts; E19 comes last (#4203).

- E11 (M) Versions. Screen 10. `free`
  - A sub-step in the open version's migration with `install_create_table()`, rerun twice on a dev DB; a save writes a version and its doc; opening loads the original plus the doc; the versions drawer (preview, restore, delete); retention: the original plus the last 10 saves (#4203).

- E12 (M) Canvas and artboard. Screens 14-17. `free` after E10.
  - New image dialog (square 1080, portrait 1080×1350, story 1080×1920, share 1200×630, banner 1920×600, custom; white, transparent, black, light blue); pictures as layers (Files, upload, paste).
  - Artboard tool: handles on the edges and corners; the artwork stays where it is, the board slides over it; width and height with a nine-point anchor; fit to content or to the selection; Shift keeps the ratio, Alt sizes from the centre. On a photo the same tool extends or trims the canvas with a fill; with it a frame may grow the canvas outward (the Polaroid of screen 08; E8 draws frames on the picture's edge).
  - File Manager "New image" opens it (today `createBlankImage()` sends a white PNG to the old editor).

- E13 (M) Optimisation. Screen 11. `free` after E12.
  - Export panel: format (original, JPEG, WebP, AVIF, PNG from the server's list), quality with the exact size from `estimate`, longest edge (original, 2560, 1920, 1280, 1080), metadata (location always removed, camera details optional), sRGB or the kept profile, progressive JPEG, PNG colour reduction; size before and after.
  - Profile `optimise`: the File Manager opens a picture straight on this panel.

- E14 (M) Vector pen and nodes. Screen 09. Done in #4564 (`ui/image-editor-vector.md`).
  - Bezier pen (click for corners, drag for curves), node editing (move, handles, add, delete, corner or smooth), open and closed paths, "convert to path" for shapes.

- E15 (M) SVG open and save. `free` after E7 and E14.
  - Opening an SVG gives editable layers for path, rect, circle, ellipse, line, polyline, polygon, text, g, data-URI pictures and gradients; anything else becomes a locked group kept as written. Saving writes SVG through `pg_svg_sanitize()` (tests with hostile samples); fonts by name, pictures embedded.

- E16 (M) Workspace canvas. `free` after E9 and E11.
  - "New canvas" in a channel and in notes; saved through `workspace_file_save.php` with its doc; texts edit later; a preview picture in the message.

- E17 (S) Visual designer. `free` after E9 and E11.
  - The image widget's "Edit image" opens the modal over the designer; a saved version refreshes the picture (cache key); crop presets from the widget's ratio.

- E18 (S) Access. `free` after E9.
  - Keyboard map and help sheet, names for every control, focus trap, reduced motion, two-finger rotate (the phone layout is E1b).

- E19 (M, later) SVG animation presets.
  - Per layer: entrance (fade, slide, scale, draw the stroke) and loop (pulse, float, spin, blink) with delay, duration, easing; a play button. The server writes one `<style>` of `@keyframes` from the preset names and numbers, never the browser's CSS, inside `prefers-reduced-motion: no-preference`. Only SVG output moves (`<img>`, CSS background, inline); raster formats keep the first frame; no SMIL, script, GIF or video.

- E20 (S) Close-out.
  - Lasting decisions to topic files (`designer/image-editor-tool.md` rewritten, a `platform/` file for the save pipeline and one for the SVG sanitizer); the plan files and `plans/image-editor/` deleted.
