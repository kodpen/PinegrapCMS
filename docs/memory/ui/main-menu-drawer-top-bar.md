---
name: main-menu-drawer-top-bar
description: Page Design Studio draws no panel top bar: its tools at the head of the main menu's drawer, Go to Site and the account at its foot, a dot on the menu button for an unread notification (FR5)
module: output
---

## What does the editor show instead of the top bar?
- Owner (2026-11-26, agent channel poll 234, message 14331): "Evet, tek çubuk". A drawer-only screen (`menu_drawer`, today only Page Design Studio) prints no `.software-navbar`; `pg_menu_shell_parts()` gives `bar_in_drawer`, `output_header()` adds `pg-bar-in-drawer` to the container. Every other screen keeps its bar.
- The drawer (`pg_menu_drawer_bar()`, `includes/fn/output.php`): head = the logo (to `welcome.php`, through the editor's unsaved-changes guard) and a close button (`data-pg-menu-drawer-close`); one row of Search, Quick note, Help for this screen, Site Settings, Notifications; the main menu; foot = Go to Site (`#go_home_button`, labelled) and the account menu (`#user_menu_toggle`), which opens upward over the drawer.

## Why is the drawer #header?
- The wrapper around head, tools, `#menu` and foot carries `id="header"`, so every control keeps its ids, its rules (`#header .pg-usermenu`, `.pg-rightcluster` sizes) and its scripts (F1 reads `#help_button`, the poll `#notification_toggle`). Only one `#header` is printed. The bar's search, bell and help markup are one variable each, used in the bar or in the drawer.
- `backend.src.css`, block "The top bar in the drawer": `--pg-navbar-h: 0px` and a grid row of 0 on the container, the sidebar from the window's top, `.sd-wrapper` at `100dvh`. The phone rules that make room in the bar (quick note below 576 px, wordmark below 768 px, help below sm) do not apply in the drawer. The account name is `d-none` below lg in the bar; `@layer utilities` beats `@layer custom`, so `display: inline !important` shows it there.

## How does the drawer behave with the tools?
- `backend.src.js`, `MENU_BAR_IN_DRAWER`: a menu of a tool (account, quick note) leaves the drawer open; focus moving into an open `<dialog>` (search, Site Settings, the bell) does not shut it; Escape shuts that window or menu first, not the drawer. The close button gives the focus back to the opener.
- An unread notification adds `pg-menu-unread` to every `[data-pg-menu-drawer-toggle]`: a 7 px dot in `--pg-danger` at the button's corner (the notification poll removes it when nothing is unread).

## What was checked?
- On a sandbox at 1440 and 390 px, light and dark (#12054): no top bar, the editor fills the window, the account menu and Site Settings open over the drawer, the close button returns the focus to `#sd-left-toggle`, Pages and the dashboard keep their bar.

Tests: `tests/menu_drawer_screen_test.php`, `tests/panel_header_phone_test.php`. Related: `main-menu-drawer-only.md`, `panel-top-bar-phone.md`, `designer/designer-frame-tidy-drawing.md`.
