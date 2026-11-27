---
name: main-menu-drawer-only
description: A drawer-only screen (output_header()'s menu_drawer, Page Design Studio): the main menu as the phone drawer at every width, opened by the screen's own button (#9712)
module: output
---

## What is a drawer-only screen?
- A screen that needs its whole width passes `menu_drawer => true` to `output_header()` (today only Page Design Studio, `includes/designer_screen.php`, beside `hide_menu`). `pg_menu_shell_parts()` decides: the sidebar is printed, `#menu_mode_toggle` is not, and `.software-container` carries `menu-drawer-only`, never `expanded` or `menu-hidden`. `hide_menu` alone still prints no menu; its other readers (section rail, search field) are unchanged (#9712).

- Such a screen also draws no top bar: its controls stand in the drawer (`main-menu-drawer-top-bar.md`, #12054). Owner (2026-11-26): "Evet, tek çubuk": only the editor loses the top bar; every other screen keeps it.

## How do the stylesheet and the script follow?
- `backend.src.css` repeats the below-lg drawer rules under `.menu-drawer-only` at every width (block "Drawer-only screens", keep the two in step); the scrim's rules sit outside any query.
- `backend.src.js`: `MENU_DRAWER_ONLY` makes `isMenuDrawerMode()` true whatever the width, so the phone drawer's behaviour holds as it is (scrim, Escape, inert while shut, focus leaving shuts it, no cookie). The screen's own button, marked `data-pg-menu-drawer-toggle` with `data-label-show` / `data-label-hide`, opens and shuts it (`paintMenuDrawerToggles()`); Escape gives the focus back to the opener last used (`menuDrawerToggle()`). Pictures `.pg-mt-shut` / `.pg-mt-open` inside it.

## Why does the bar over a site page on a phone become one?
- `toolbar.php` (the bar in `#software_toolbar` over a site page) is a frame as wide as the site page's layout viewport. A phone widens that viewport to a page wider than its screen (an element 1400 px wide gives 1408), so the frame passed for a wide screen: the menu came up open from the desktop `softwaremenustatus` cookie, on the left of the opened bar (#11187).
- `pgSiteBarPhoneDrawer()` in `backend.src.js`, run before `MENU_DRAWER_ONLY` is read, adds `menu-drawer-only` in a `body.toolbar` frame when `screen.width` is under 1024. The bar keeps `#menu_mode_toggle`; `backend.src.css` shows only its drawer pictures (`.pg-mt-shut` / `.pg-mt-open`) under `.menu-drawer-only`.

## Why?
- Page Design Studio needs its whole width, so it was drawn with `hide_menu` and the main menu could not be reached there at any width (owner's report #9712). Its toolbar's first button (`#sd-left-toggle`) is now the menu's opener (`designer/designer-left-panel-hide.md`).

Tests: `tests/menu_drawer_screen_test.php`. Related: `main-menu-states.md`, `main-menu-drawer-focus.md`.
