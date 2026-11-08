---
name: designer-theme-part
description: The Themes tab of the designer's assets panel as the part 'theme' (D5 part 5): what moved, what the core keeps, the host the part works through
---

## What is in the part?
- `assets/js/style_designer_theme.js` (bundle `style_designer_theme.min.js`, part `theme` in `pg_designer_parts()`): `THEME_VAR_GROUPS` (the Bootstrap variable rows), `_themeCascadeRulesFor()` / `_writeCascadeBlock()` (the `.btn-*`, `.bg-*`… rules a theme colour writes), `_writeThemeVar()`, the group collapse state, the tab's header (`_renderThemeHead()`), "Save these colours as a theme" (`_sdSaveAsTheme()`) and the rows themselves.
- Handed to every editor (`designer_screen.php`); it runs the first time the Themes tab is shown.

## What does the core keep, and why?
- The rule-block helpers, `_findRuleBlock()` to `_parseThemeDecls()`: `_ensureBootstrapSentinels()` splits the old light block with `_themeUpgradeLightSelector()` while the design loads, before any tab is shown.
- `_sdThemeCssFromUserStyles()` (read by `tests/designer_theme_vars_test.php`), `_themeMode` and the Light / Dark tabs, the Design / Themes tab switch.
- `renderThemePanel()` is a stub: while the part has not run and `#sd-tab-themes` is hidden it does nothing (showing the tab draws it); else `_sdPartCall('theme', 'render', [_themeHost])`. The callers (user styles changed from the Styles panel, undo, the mode set by the toolbar, the theme select) are unchanged.

## How does the part reach the assets panel's state?
- `_themeHost` (inside `initAssetsPanel()`): `wrap`, `mode()`, `userStyles()`, `setUserStyles(content)` (finds or creates the User Styles entry and saves the field), `activeTheme`, `openDesignSettings`, `themeCss`, and the helpers `blockFor`, `setDecl`, `upgrade`, `modeDecls`, `hasDarkDefault`. The part keeps the last host it was handed (`H`) and wraps those helpers under their core names.
- Entry points: `render(host)` and `write(host, mode, name, value)`. Core functions come from the kit (`applyAssetsToIframe`, `_sdThemes`, `_sdThemeFind`, `_sdThemeValue`, `_sdOpenThemeSettings` were added).
- Tests: `tests/designer_parts_test.php` (`theme` cases) runs the part under Node on a fake host.
