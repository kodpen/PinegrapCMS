---
name: main-menu-states
description: Main menu on wide screens: expanded / mini / hidden, the one bar button (#menu_mode_toggle) that walks them (the drawer on phones), the cookie, server-drawn classes, Ctrl+D
---

## What are the states?
- lg and up (Bootstrap 6's lg, 1024px; the menu's media queries in `backend.src.css`): `expanded` (icons and labels, `--pg-rail-w-expanded`), `collapsed` (the icon rail, the default) and `hidden` (no column, the content takes the width). Below lg the menu is a drawer of its own (`.drawer-open`, not Bootstrap's `.drawer`), open or shut; the drawer never writes the wide-screen state. `backend.src.js` decides drawer mode with `MENU_DRAWER_BREAKPOINT` (1024, the stylesheet's width; keep the two equal).
- A row that opens a panel widens the collapsed rail over the content while the panel is open (`.pg-menu-peek`); that is not a state and writes no cookie (`main-menu-panels.md`). With the menu hidden, `pgMenu.openPanel()` slides it in the same way (`main-menu-panel-scripts.md`).

- A navigator screen (the workspace, the File Manager, the Catalog Manager) is open or the rail only, from a cookie of its own (`main-menu-navigator-screens.md`).

## Which control does what?
- One button, at the left end of the bar (`pg_menu_mode_button()`, `#menu_mode_toggle`, `includes/fn/output.php`). Above lg each click moves on to the next state: mini (collapsed) → open (expanded) → hidden → mini. Below lg it opens and shuts the drawer.
- Its picture is the state it shows: `bi-layout-sidebar-inset` mini, `bi-square-half` open, `bi-layout-sidebar` hidden; on a phone `bi-layout-sidebar` shut, `bi-layout-sidebar-inset` open. Hidden was `bi-square` until the owner asked for another: an empty square read as an unticked check box (the file manager uses it for one); hidden and the shut drawer now look the same (`tests/menu_state_test.php`). All five are printed and CSS picks one from the container's classes and the width, so nothing flips once the script runs; `paintMenuToggle()` keeps `aria-expanded` and the label ("Menü görünümü: Minik/Açık/Gizli", on a phone "Menüyü göster/gizle", from the `data-label-*` attributes) right.
- It replaced, on the owner's request, both the bar's show/hide button (`#menu_drawer_toggle`, which brought back the last visible state from a `softwaremenulast` cookie, now gone) and the foot of the menu (`pg_menu_mode_switch()`, a small menu of the three states).
- Ctrl+D clicks it. On the front-end toolbar Ctrl+D is still the fullscreen switch.

## Where is the state kept?
- Cookie `softwaremenustatus` = expanded | collapsed | hidden, kept a year (the old one lasted a day). `pg_menu_state()` reads it (anything else is collapsed; `tests/menu_state_test.php`) so `output_header()` draws `.expanded` or `.menu-hidden` on `.software-container` before any script. `.menu-hidden` is drawn only where the sidebar is (not on `hide_menu` screens such as the page designer).
- `backend.src.js`: `setMenuState()` writes the cookie, `paintMenuState()` the classes, `paintMenuToggle()` the button, `shownMenuState()` reads the state off the container for the next click; `syncMenuMode()` drops the wide classes on entering drawer mode and repaints from the cookie on leaving it.

## What does hidden do to the layout?
- The grid column goes to 0 and the rail slides out (`translateX(-100%)`), its `visibility: hidden` delayed by the slide so its links leave the tab order; the pocket corner (`.software-sidebar::before/::after`) goes with it. Above lg the bar button's `li` is the rail's width, so its icon sits over the menu icons.

## What does the tour say?
- The frame tour's menu step says that a row with an arrow opens its pages in the menu and that the button at the top left walks the three states, only narrowing on the navigator screens; its narrow step rings `#menu_mode_toggle`. `PG_TOUR_SHELL_KEY` is `shell.6` (the navigator sentence); `shell.5` for the one button, `shell.4` for the earlier controls (#598).
