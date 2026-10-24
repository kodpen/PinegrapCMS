---
name: main-menu-states
description: Main menu on wide screens: expanded / mini / hidden chosen at its foot, the bar button that hides and restores it (the drawer on phones), cookies, server-drawn classes, Ctrl+D
---

## What are the states?
- lg and up (992px): `expanded` (icons and labels, `--pg-rail-w-expanded`), `collapsed` (the icon rail, the default) and `hidden` (no column, the content takes the width). Below lg the menu is the off-canvas drawer, open or shut; the drawer never writes the wide-screen state.
- A row that opens a panel widens the collapsed rail over the content while the panel is open (`.pg-menu-peek`); that is not a state and writes no cookie (`main-menu-panels.md`).

## Which control does what?
- The foot of the menu (`pg_menu_mode_switch()`, `includes/fn/output.php`): one button that opens a small menu of the three states (`role="menuitemradio"`, `data-pg-menu-mode`, `aria-checked`). Its picture is the current state; on the expanded menu it also carries "Menü görünümü". Not shown below lg. It replaced the caret (`#menu_toggle`) that only flipped expanded and collapsed and had no name.
- The bar (`pg_menu_toggle_button()`, `#menu_drawer_toggle`, now on every width): on a phone it opens and shuts the drawer; on a wide screen it hides the menu and brings back the last visible state (cookie `softwaremenulast`). Its picture is a side panel, filled (`bi-layout-sidebar-inset`) while the menu is on screen and empty (`bi-layout-sidebar`) while it is not, in place of the old burger. Both icons are printed and CSS picks one from the container's classes, so nothing flips once the script runs; the script keeps `aria-expanded` and the label ("Menüyü göster/gizle") right.
- Ctrl+D clicks the bar button. On the front-end toolbar it is still the fullscreen switch.

## Where is the state kept?
- Cookie `softwaremenustatus` = expanded | collapsed | hidden, kept a year (the old one lasted a day). `pg_menu_state()` reads it (anything else is collapsed; `tests/menu_state_test.php`) so `output_header()` draws `.expanded` or `.menu-hidden` on `.software-container` before any script. `.menu-hidden` is drawn only where the sidebar is (not on `hide_menu` screens such as the page designer).
- `backend.src.js`: `setMenuState()` writes the cookies, `paintMenuState()` the classes and `aria-checked`, `paintMenuToggle()` the bar button; `syncMenuMode()` drops the wide classes on entering drawer mode and repaints from the cookie on leaving it.

## What does hidden do to the layout?
- The grid column goes to 0 and the rail slides out (`translateX(-100%)`), its `visibility: hidden` delayed by the slide so its links leave the tab order; the pocket corner (`.software-sidebar::before/::after`) goes with it. Above lg the bar button's `li` is the rail's width, so its icon sits over the menu icons.

## What does the tour say?
- The frame tour's menu step names both controls; `PG_TOUR_SHELL_KEY` went to `shell.4` so everybody sees the changed frame once (#598).
