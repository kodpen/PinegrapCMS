---
name: image-editor-steps
description: Steps E1-E10 of plans/image-editor.md: core modal, crop, server save, adjust, layers, shapes, text, stickers, the switch that removes the old editor, redaction; claim as [plan:image-editor] E<n>
---

## Which steps are there?
- S is under 400 product lines, M about 1,000 (E9's library deletion not counted). "Screen" numbers are in `image-editor-visuals.md`. Order: E1, E1b, E2, E3, then E4-E8 in any order, E9, E10. Each step's UI also works on a phone in the E1b layout.

- E1 (M) Core modal. Screens 18, 19, 01, 12, 13. Done in #4122 (`ui/image-editor-modal.md`).
  - Bundle, stylesheet, loader, `pg_image_editor_strings()`, the `<dialog>` with full screen, top bar, rail, panel, zoom and pan (wheel, pinch, keys), document model, undo and redo, profiles and `tools`.
  - Opens `{src}` and `{blank}`; no caller switched yet. The JS maths (matrices, rotated space) gets `node --test` tests.

- E1b (S) Phone layout. Screens 20-25. Done in #4186 (`ui/image-editor-modal.md`).
  - Narrower than 600 px: the rail becomes a scrolling bottom bar, the tool's panel a sheet above it (handle, at most 42 % of the height; closed for Select until something is chosen), the top bar keeps close, name, undo, redo and save; the mini bar sits at the bottom; handles get 44 px touch targets; safe-area insets.
  - Lower than 500 px (a phone held sideways): an icon rail on the left and the panel as a sheet over the right of the stage. Replaces E1's rule that hides the panel under 768 px.

- E2 (M) Crop and geometry. Screen 02. Done in #4208 (`ui/image-editor-crop.md`).
  - Crop box and handles, ratios (free, original, 1:1, 4:3, 3:2, 16:9, 4:5, 9:16), quarter turns, flips, straighten ±45° with cover scale, output size with presets, crop shape (rounded, circle).
  - Layers follow turns and flips; cancel restores them.

- E3 (M) Server save.
  - `image_editor_api.php` open and save, `pg_image_edit_validate()`, `pg_image_edit_apply()` steps 1, 2, 5 and 6 for Imagick and GD, replace, copy and new, the refusals; PHP tests for the validator and the geometry.

- E4 (M) Adjust and filters. Screen 03.
  - Brightness, contrast, saturation, exposure, temperature, gamma, vignette, sharpen; nine filters as fixed values of the same numbers; preview through SVG filters, server step 3; GD hosts send the colour layer.

- E5 (M) Layers and selection. Screen 01.
  - Select, move, resize, rotate, multi-select, align and distribute, order, lock, hide, duplicate, delete, copy and paste (also a pasted picture), snapping guides, the layers list, keyboard nudges.

- E6 (M) Drawing and ready-made shapes. Screen 04.
  - Pen with smoothing and eraser, line, arrow (head styles), box with a corner-radius handle and value, ellipse, triangle, polygon (3-12 sides), star (4-12 points, inner ratio), speech bubble; stroke, fill, opacity, dash.

- E7 (M) Text. Screen 05.
  - Typing in place, font (site fonts and system), size, weight, italic, alignment, line height, letter spacing, colour, background box with padding and radius, outline, shadow; one line layout for view and export.

- E8 (S) Stickers and frames. Screens 07, 08.
  - Built-in SVG stickers with an editable label, a picture from Files as a sticker, frames (line, thick, polaroid, corners) and background fill.

- E9 (M) Switch and removal.
  - The `openImageEditor` alias; callers: `view_folders.php`, `style_designer.src.js`, `workspace.js` (saving through `workspace_file_save.php`), `edit_file.php`, `edit_design_file.php`, `view_files.php`, `view_design_files.php`, `widget_18.php`, `content.php`, `frontend.src.js`, `output.php`; `image_editor_edit.php` becomes a redirect that opens the modal.
  - Delete `assets/lib/image_editor/`, `image_editor_modal.js`, `image_editor_save.php` and the old loader; `clean_up.php` lists the removed names; memory topic files follow; the PR body names the `[NEW]` changelog line.

- E10 (M) Redaction. Screen 06.
  - Pixelate, blur, solid box and brush-shaped areas; browser preview, burned in by server step 4; metadata stripped; versions per open question 1.
