---
name: design-canvas-icon-one-colour
description: Plan (owner 2026-11-13, #9250): the Design Canvas icon is drawn in one colour where it stands among one-colour icons (Workspace's New canvas entries, the File Manager); one step, DI1
---

## What is asked?
- Owner (2026-11-13), with a picture of a channel's "+" menu where New canvas is the only coloured icon: "yeni tuval ikonu sırıttlı açıkçası. siyah beyaz çevirelim tekrar." Asked where, he chose "Tek renkli listelerde". Record: `workflow/decisions-made-panel-8.md` (#9250).

## What is drawn today?
- `pg_design_canvas_icon()` (`includes/fn/image_editor.php`) gives drawing B with `design-color pg-dc-icon`; `.pg-dc-icon` (`backend.src.css`) sets the tint and the pen curve's hue outside `#menu` (`ui/design-canvas-icon.md`).
- It is shown in Workspace's two New canvas entries (`CFG.canvas_icon`, `icon('pg-design-canvas')` in `workspace.js`), in the File Manager's context menu and file tools (`L.image_editor_icon`, `menuIcon()` in `view_folders.php`) and in the editor's top bar (`.pgie-brand`).

## What changes?
- In Workspace's two New canvas entries and in the File Manager's context menu and file tools the drawing takes one colour, the one its neighbours take (`currentColor`): no tint and no second hue, in both themes, at rest, on hover and when disabled.
- The same drawing: its lines stay, only the colours go. No new icon, no glyph swapped in.

## What stays?
- The main menu's icon and the editor's top bar: drawing B in its two colours (#8903, `workflow/decisions-made-panel-7.md`).
- The editor on a site page (`pg_image_editor.src.css`) draws the top bar's icon: untouched.

## Which steps are there?
- DI1 (S) The one-colour form, used by the four places above. Claim as `[plan:design-canvas-icon-one-colour] DI1 — The one-colour form`.
  - How the one-colour form is asked for (an argument, a class of its own) is the worker's call.
  - `tests/design_canvas_icon_test.php` covers both forms. `php tools/build_css.php` after `backend.src.css`; `php tools/build_js.php` if a bundled script changes.
  - The PR shows the "+" menu and the File Manager's menu before and after, in both themes.
  - `ui/design-canvas-icon.md` says where each form is shown; this plan is deleted.
