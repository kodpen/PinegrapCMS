---
name: design-canvas-icon
description: The Design Canvas's own icon (drawing B, #8903): pg_design_canvas_icon(), its colours outside the main menu, the places that show it (File Manager, Workspace's New canvas, the editor's top bar)
module: image_editor
---

## Which drawing is it?
- Drawing B of the owner's pick (#8903, `workflow/decisions-made-panel-7.md`): an artboard with crop marks, the pen curve and its two anchors in the second colour. It is `pg_menu_icon('design_canvas')` (`includes/fn/output.php`), on the main menu's 24-unit grid (`main-menu-icons.md`).
- Not chosen: an easel (A), a canvas with a brush (C). The candidates' sheet: `plans/design-names-menu/nm2-icon-candidates.png` while the plan lasts.

## How is it shown outside the main menu?
- `pg_design_canvas_icon($space)` (`includes/fn/image_editor.php`): the drawing with `design-color pg-dc-icon`; `$space` is the margin class (`pg_menu_icon()`'s third argument, the menu's `me-3` by default).
- `body .design-color` (`backend.src.css`) sets the outline; `.pg-dc-icon` sets the tint and the pen curve's sky hue, light and dark, outside `#menu`. The editor on a site page has no backend.css: `pg_image_editor.src.css` colours `.pgie .pg-dc-icon` itself, `.pgie-dark` swapping the shades.

## Where is it shown?
- File Manager: the context menu's and the file tools' Design Canvas action (`L.image_editor_icon`; `menuIcon()` in `view_folders.php` takes a `bi-` name or drawn markup).
- Workspace: both New canvas entries (`CFG.canvas_icon` from `ws_screen_config()`; `icon('pg-design-canvas')` in `workspace.js`, the easel glyph only when the config has none).
- The editor's top bar, before the file name (`pgImageEditorConfig.brandIcon`, `.pgie-brand`).
- Test: `tests/design_canvas_icon_test.php` (#8902).
