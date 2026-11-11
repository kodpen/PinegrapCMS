---
name: design-colour-mode
description: A design's colour mode (style.style_color_mode, 11.0.186): light, dark or the system on <html>, the early script's order (a visitor's stored choice wins), canvas and Preview
---

## What is it?
- Settings, Theme, "Colour mode": Not set, Light, Dark, Follow the system, for every page of a Bootstrap design (#8711, step B11 of the bold pages plan). Owner (2026-11-11): `workflow/decisions-made-product-44.md`; column approved in `workflow/decisions-made-schema-15.md`.
- Kept in `style.style_color_mode` VARCHAR(8) NOT NULL DEFAULT '' (step `upgrade_2026_11_0_design_color_mode()`, 11.0.186, no backfill). Keys `''`, `light`, `dark`, `auto`; `pg_design_color_mode_key()` turns anything else into `''`. Readiness: `pg_design_color_mode_ready()` (`pg_schema_has()`), sent to the editor as `themes.colorModeReady`.
- Not set (`''`) is today's behaviour: the page gets nothing and its output is byte for byte what it was.

## What does the published page get?
- `pg_design_color_mode_apply()` (called in `get_page_content.php` for a visual design, never in an e-mail): on the first `<html>`, `data-pg-color-mode="<mode>"`, plus `data-bs-theme="light|dark"` for light and dark (right without script); a `data-bs-theme` the design's own markup put there is replaced. Auto writes no `data-bs-theme`: without script the page is light.
- An inline script before `</head>` (`pg_design_color_mode_script()`) runs before the first paint.

## In what order is the mode chosen?
- 1. The visitor's stored choice (`localStorage` `pg-theme`: `light`, `dark` or `auto` = their "System"), on any page of a design with a mode, switcher on that page or not. 2. The design's mode from `data-pg-color-mode`. 3. Without either: the system, as before (only on a page with a switcher; a page with neither sets nothing).
- Auto follows the system live (a `matchMedia` listener) unless a switcher's engine runs (`window.pgThemeInit`): the engine follows the system itself and knows when the visitor has picked since.
- With a switcher on the page, `pg_theme_switcher_inject()`'s inline resolve and `pg_theme_switcher.js` (`designMode()`) fall back to `data-pg-color-mode` when nothing is stored, so the switcher shows the design's mode as current. Without the attribute both still mean "auto".

## How do the canvas and the Preview show it?
- The canvas opens once per editor in the design's mode (`_sdColorModeOpened` in `_sdInitCanvasDoc()`: dark → Dark view; auto → the editor's own system setting; Not set or light → light, as before). Picking a mode in the pane sets the view too (`applyCanvasTheme(…, {source: 'design'})`); Not set leaves the view. The Preview follows the canvas view (`_sdPreviewDarkView()`).
- Nothing is stored for the view itself (designer-canvas-theme-view).

## Which paths carry the column?
- Save (`save_system_style()`, key `color_mode`, only when posted and the column exists), the editor (`edit_system_style.php` → hidden input `#sd-style-color-mode`, in `_pgStyleJSON()`), Duplicate (`duplicate_style.php`), a page detached into its own design (`includes/panel/designer.php`).
- Not carried: the design list (its Theme column shows look and palette only) and a custom design template (its table has no column for it; a second schema change was not approved). Tests: `tests/design_colour_mode_test.php`.
