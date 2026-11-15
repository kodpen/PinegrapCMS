---
name: designer-left-panel-hide
description: The Page Design Studio toolbar's menu button (#sd-left-toggle) opens and shuts the panel's main menu as the phone drawer at every width (#9712); the old left panel hide (sd-hide-left) is gone
module: output
---

## What does the menu button do?
- `#sd-left-toggle` (`bi-layout-sidebar` shut, `bi-layout-sidebar-inset` open) is the first item of the designer toolbar (`.sd-toolbar-tabs`), outside `.sd-toolbar-pages`, so the component editor and read-only mode keep it.
- It opens and shuts the panel's main menu, the drawer the phone shows, at every width: the menu slides in over the screen with the scrim and the designer keeps its whole width. Labels "Show menu" / "Hide menu", `aria-controls="menu"`, `aria-expanded` follows the drawer; `no-popover` like the bar button, since the title popover swallowed the Escape that shuts the drawer.
- How the screen gets the drawer: `ui/main-menu-drawer-only.md`.

## Why did it change?
- #8972 built it as "hide the designer's left panel" (Components / Shared / System, palette, Overview tree, with its rail). The owner's report #9712: the main menu could not be reached from Page Design Studio at any width (`hide_menu` printed no menu and no bar button); the button was meant to be the menu's.

## What went with the old hide?
- `sd-hide-left` on `.sd-body`, `_sdLeftSetHidden()`, `_sdLeftHideInit()`, the command palette's `act_left_panel` (Ctrl+Shift+P) and the rule in `style_designer.css` are gone, with the labels "Hide the left panel" / "Show the left panel" in code.
- `pinegrap_designer_hide_left` is read no more: `_sdLeftHideForget()` (end of `initPanelDocking()`) removes it at load, so a panel hidden by it comes back.
- The panel header's "|<" dock button stays the way to park the left panel on its 32px rail (docking: `sd-dock-left`, `pinegrap_designer_dock_left`).

Tests: `tests/menu_drawer_screen_test.php`.
