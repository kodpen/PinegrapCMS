---
name: design-canvas-icon
description: The canvas's own icon (two sheets and a wave, owner 2026-11-20): pg_design_canvas_icon() in two colours, pg_design_canvas_icon_one_colour() (#9250), and where each form is shown
module: image_editor
---

## Which drawing is it?
- Two round-cornered sheets and a wave (owner, 2026-11-20, `workflow/decisions-made-panel-10.md`), drawn since #10516. It is `pg_menu_icon('design_canvas')` (`includes/fn/output.php`), on the main menu's 24-unit grid and stroke of 1.7 (`main-menu-icons.md`).
- The back sheet, up and left, is an open outline that stops where the front sheet covers it, plus its module tint (`.pg-mi-2`); the front sheet is an outline only; the wave on it is the accent group (`.pg-mi-a pg-mi-wave`). No crop marks, no anchors.
- It replaced drawing B (#8903, `decisions-made-panel-7.md`), an artboard with crop marks, the pen curve and two anchors. Both sheets are in git history: `git show a5952c21:docs/memory/plans/artifact-canvas-rename/icon-candidates.png`, `git show f62cf699:docs/memory/plans/design-names-menu/nm2-icon-candidates.png`.

## How is it shown outside the main menu?
- `pg_design_canvas_icon($space)` (`includes/fn/image_editor.php`): the drawing with `design-color pg-dc-icon`; `$space` is the margin class (`pg_menu_icon()`'s third argument, the menu's `me-3` by default).
- One-colour form: `pg_design_canvas_icon_one_colour($space)`, the same drawing with `pg-dc-icon pg-dc-mono` and no `design-color`. `.pg-dc-mono` (`backend.src.css`) gives the outline and the wave `color: inherit` and the tints `fill: none`, so it takes its neighbours' colour at rest, on hover, disabled, in both themes.
- `body .design-color` (`backend.src.css`) sets the two-colour form's outline; `.pg-dc-icon` sets the tint and the wave's sky hue, light and dark, outside `#menu`. The editor on a site page has no backend.css: `pg_image_editor.src.css` colours `.pgie .pg-dc-icon` itself, `.pgie-dark` swapping the shades.

## Where is each form shown?
- One colour, among one-colour icons (owner, 2026-11-13, #9250, `workflow/decisions-made-panel-8.md`):
  - File Manager: the context menu's and the file tools' canvas action (`L.image_editor_icon`; `menuIcon()` in `view_folders.php` takes a `bi-` name or drawn markup).
  - Workspace: both New canvas entries, the channel's "+" menu and the files tools (`CFG.canvas_icon` from `ws_screen_config()`; `icon('pg-design-canvas')` in `workspace.js`, the easel glyph only when the config has none).
  - Workspace: the joined canvas card's head (`h.icon('pg-design-canvas')` in `workspace_page_card.js`), and a guest's waiting canvas (`CONF.canvas_icon` from `workspace_guest.php`; `workspace_guest.css` sizes it and drops the tint, the page has no backend.css).
  - Panel: "Edit this image in the Artifact Canvas" on Edit Design File, Edit File, the design files list and the dashboard's widget 18 (#10536), and the Translations screen's queue row "Edit in the Artifact Canvas" (#10646); its picture chip keeps `bi-image`.
  - File Manager: "New image" in the New menu and the context menu (#10646).
  - Channels: "Edit image" on a message's picture (channel and thread menus); a note's "Edit the picture" (an element to `options.media.action()`, `workspace_editor.js`) (#10646).
  - Page Design Studio (#10646): the three image buttons (operator's Image row, image address, background image) and both "Edit Image" menu entries, titled "Edit the image in the Artifact Canvas". `_sdCanvasIcon()` reads `pgImageEditorConfig.canvasIcon` (beside `brandIcon`), the brush glyph without it; `style_designer.css` sizes it and drops the tint, the screen has no backend.css.
- Two colours: the main menu (`pg_menu_icon()`) and the editor's top bar, before the file name (`pgImageEditorConfig.brandIcon`, `.pgie-brand`; on a site page `pg_image_editor.src.css`).
- A new place among Bootstrap icons takes the one-colour form.
- Test: `tests/design_canvas_icon_test.php` (#8902; both forms since #9250's step DI1; the sheets and the wave since #10516).
