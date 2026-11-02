---
name: image-editor-steps
description: Steps E1-E10 of plans/image-editor.md: core modal, crop, server save, adjust, layers, shapes, text, stickers, the switch that removes the old editor, redaction; claim as [plan:image-editor] E<n>
---

## Which steps are there?
- S under 400 product lines, M about 1,000 (E9's deletion not counted). Screens: `image-editor-visuals.md`. Order and `free`: `image-editor-workers.md`. Each step's UI works on a phone (E1b layout).

- E1 (M) Core modal. Screens 18, 19, 01, 12, 13. Done in #4122 (`ui/image-editor-modal.md`).
  - Bundle, loader, labels, the `<dialog>` with full screen, top bar, rail, panel, zoom and pan, document model, undo and redo, profiles and `tools`.

- E1b (S) Phone layout. Screens 20-25. Done in #4186 (`ui/image-editor-modal.md`).
  - Narrower than 600 px: a scrolling bottom bar of tools and the panel as a sheet above it; lower than 500 px: an icon rail on the left and the panel over the stage's right.

- E2 (M) Crop and geometry. Screen 02. Done in #4208 (`ui/image-editor-crop.md`).
  - Crop box, ratios, quarter turns, flips, straighten ±45°, output size, crop shape; layers follow turns and flips.

- E3 (M) Server save. Done in #4258 (`platform/image-editor-save.md`).
  - `image_editor_api.php` open and save, the validator, `pg_image_edit_apply()` steps 1, 2, 5, 6 (Imagick, GD), replace, copy, new.

- E4 (M) Adjust and filters. Screen 03. Done in #4302 (`ui/image-editor-adjust.md`).
  - Eight adjustments, nine filters as fixed values of them; SVG filter preview, server step 3; GD's colour layer.

- E5 (M) Layers and selection. Screen 01. Done in #4327 (`ui/image-editor-layers.md`).
  - Select, move, resize, rotate, align, distribute, order, lock, hide, duplicate, delete, copy and paste, snapping, the list.

- E6 (M) Drawing and ready-made shapes. Screen 04. Done in #4475 (`ui/image-editor-draw.md`).
  - Pen with smoothing and eraser, line, arrow (head styles), box with a corner-radius handle and value, ellipse, triangle, polygon (3-12 sides), star (4-12 points, inner ratio), speech bubble; stroke, fill, opacity, dash.

- E7 (M) Text. Screen 05. Done in #4488 (`ui/image-editor-text.md`).
  - Typing in place, system and site fonts, size, weight, italic, alignment, line and letter spacing, colour, background box (padding, radius), outline, shadow; one layout for view and export.

- E8 (S) Stickers and frames. Screens 07, 08. Done in #4486 (`ui/image-editor-sticker.md`).
  - SVG stickers with a label, a picture from Files as a sticker; frames (line, thick, polaroid, corners), fill.

- E9 (M) Switch and removal. Done in #4609 (`ui/image-editor-callers.md`).
  - Every caller on the new editor, `image_editor_edit.php` a redirect; the old editor, its endpoint and loader removed.

- E10 (M) Redaction. Screen 06. Done (#4579).
  - Pixelate, blur, solid box and brush-shaped areas; browser preview, burned in by server step 4; metadata stripped; the versions stay and the served file loses its metadata (#4203).

- E10b (S) Redaction through save.url. Done (#4745).
  - A save to another endpoint (`save.url`: the Workspace's `workspace_file_save.php`) loses the redaction: the redact part sends its masks only for replace and copy and `pg_image_editor_render()` takes none, so the saved picture shows what was covered. The hook sends them for `url` too; `pg_image_editor_render()` checks them with `pg_image_editor_take_redactions()`, burns them in as a save's step 4 does and removes the masks on every path. A test renders a solid area through it; try a redacted Workspace picture.

- E10c (S) A redacted Workspace replace. `free` after E10b.
  - Owner, #4783: the replaced original is listed in the message's earlier versions only for the person who edited. `ws_file_edits.redacted` (step `11.0.144`, `install_add_column()`), set by `workspace_file_save.php` when the replace burnt masks in; `ws_file_history()` leaves such rows out for anyone else. A test.
