---
name: designer-canvas-theme-view
description: The editor's Dark/Light canvas view and the theme switchers on the canvas are one state on the canvas <html>, never stored: applyCanvasTheme(), pgThemeNoStore, why not <body>.
---

## Where does the editor's Dark/Light view live?
- On the canvas `<html>` as `data-bs-theme` (dark, or no attribute / the engine's `light` for light), the attribute `pg_theme_switcher.js` writes on the published page. `applyCanvasTheme(mode, {source})` in `assets/js/style_designer.js` is the one place that sets it, the toolbar button (`#sd-vb-theme`), the Themes panel mode tabs and the panel's values. A fresh canvas document is written with the attribute on `<html>` (`_sdInitCanvasDoc()`), so the Animations button (`_sdCanvasRealmReset()`) keeps the view.
- It used to sit on `<body>`, which overrides `<html>` for everything below it: with the toolbar on Dark, a canvas switcher set `<html>` light and the canvas stayed dark.
- The view is not saved: the editor opens light.

## Why is a switcher on the canvas never stored as the visitor's theme?
- The canvas iframe shares the site's origin, so its `localStorage` is the site's. `applyCanvasTheme()` used to click every `[data-bs-theme-value]` item and flip `.theme-toggle` checkboxes; the engine's `pgSetTheme()` then stored `pg-theme`, and the admin's own site opened dark. The canvas also opened in whatever the admin last picked on the site.
- `_sdThemeSwitchers()` sets `window.pgThemeNoStore = true` on the canvas window before it loads the engine. In that mode the engine reads and writes no `localStorage`: the choice is kept in memory and starts from the view on `<html>`. The published page's behaviour does not change.

## How do the toolbar and a canvas switcher stay in step?
- Toolbar / Themes panel → `applyCanvasTheme()` → `_sdThemeEngineShow(mode)` calls the canvas engine's `pgSetTheme()` (only an engine in no-store mode: an engine a page brought along itself would store the choice), which redraws every switcher.
- Canvas switcher → engine → `pg-theme-change` on the canvas document → `_sdCanvasThemeChanged()` reads `<html>` ("System" is resolved there to light or dark) and calls `applyCanvasTheme(mode, {source: 'canvas'})`, so the toolbar button and the Themes panel follow. The switchers are never clicked by the editor.
- The editor's preview window is a separate document and is not covered here.

## What tests cover it?
- `tests/designer_canvas_controls_test.php`: the engine and `applyCanvasTheme()` run together under Node on a DOM stand-in (toolbar, switch, menu, Themes tab; nothing stored at any step; the published page still stores the visitor's choice), and the wiring (no clicks, the flag before the file, the listener, `<html>` on a fresh document).
