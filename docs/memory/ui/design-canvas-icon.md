---
name: design-canvas-icon
description: The Design Canvas's own mark (pg_design_canvas_icon()): drawing B, an artboard with crop marks and a pen curve; where it is shown, how it keeps two colours outside the menu, what is still to place
---

## What is it?
- Owner (2026-11-12, #8903, `workflow/decisions-made-panel-7.md`): drawing B, an artboard with its four crop marks and a pen curve between two anchors, the curve in the accent hue. Not chosen: an easel, a canvas with a brush.
- Drawn in `pg_menu_icon('canvas')` (`includes/fn/output.php`) on the menu icons' 24-unit grid (`main-menu-icons.md`), the marks thinner (1.4) than the outline so they stay readable at 16 px. `pg_design_canvas_icon($class, $size)` gives it with the design colour, the place's classes in place of the menu's `me-3`, and its size (1.15em unless told).
- It is no menu row: the Design Canvas (the image editor, `image-editor-overview.md`) has none.

## Where is it shown?
- The File Manager (`view_folders.php`): the context menu's Design Canvas action and the file pane's Design Canvas button (`CANVAS_MARK`, `iconMarkup()`; `.pg-fm-mark` keeps a glyph's 1.35rem slot so the labels stay in line).
- The editor's heading, before the file's name (`pgImageEditorConfig.mark`, `pg_image_editor_config()`; `pg_image_editor.js` puts the `<svg>` itself into the flex heading).
- `tests/design_canvas_icon_test.php`.

## How does it keep two colours outside the menu?
- The menu's colour rules all sit under `#menu` in `backend.src.css`, so this drawing carries its own: the body's tint as `#menu .pg-mi.design-color` sets it (the highlight at 55% in light, the base at 70% in dark) and the pen in the menu's sky (`--pg-mi-sky`, #0284c7 / #38bdf8), both through `light-dark()`, which follows `data-bs-theme` as Bootstrap 6's own colours do. The outline is `.design-color`.
- No hover movement: it is no row.

## What is still to place?
- A Workspace canvas: its New canvas entries (`bi-easel`) and a canvas's card are drawn by `workspace.js` and `workspace_page_card.js`, built into `workspace.min.js`, which other open work held when the mark landed. They take `pg_design_canvas_icon()` through the Workspace's config.
- Once `backend.src.css` is free, the two inline colours can become rules of its "Main menu icons" block (a `.pg-mi-canvas` line beside the others).

Related: docs/memory/ui/main-menu-icons.md, docs/memory/ui/image-editor-overview.md, docs/memory/designer/editor-turkish-terms.md
