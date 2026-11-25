---
name: main-menu-drawer-focus
description: The phone main menu drawer (below lg) is inert while shut, Escape gives focus back to the bar button, focus leaving it shuts it; the symptoms they fixed
---

## How does the shut phone drawer stay out of reach?
- The stylesheet only slides it off screen (`translateX(-100%)`, no `visibility`), so its rows stayed in the tab order and the reading order: Tab from the bar walked every off-screen row first. `paintMenuInert()`, called at the top of `paintMenuToggle()` (open, close, `paintMenuState()`, `syncMenuMode()`), sets `inert` on `.software-sidebar` in drawer mode while shut and removes it otherwise; wide screens are never inert. `pgMenu.openPanel()` opens the drawer before it focuses a row, so `inert` is already gone.
- Opened from its button (the bar's, or a screen's own `data-pg-menu-drawer-toggle`), the drawer takes the focus: `focusMenuDrawer()` puts it on the open panel's way back, else the current row, else the first row. Left on the button, Tab went through the bar (after the sidebar in the page) and never reached a row (`tests/menu_rail_keyboard_test.php`).
- Escape that closes the drawer puts the focus on `#menu_mode_toggle` when it was inside the sidebar; a link click or a header menu closing it does not move focus (`tests/panel_drawer_focus_test.php`).

## What happens when focus leaves the open drawer?
- It shuts (`drawerLostFocus()`, a `focusout` on `.software-sidebar` in `initMenuDrawer()`), as the widened rail of a wide screen does; that rail's handler acts only while `.pg-menu-peek` is set, never in drawer mode. The sidebar comes before `.software-content` in the page, so Tab past the last row used to walk the controls under the scrim.
- Not shut: focus moving into a `[data-pg-menu-hold]` layer, or onto `#menu_mode_toggle` (its own click toggles the drawer; shutting on its mousedown focus would reopen it). A `focusout` with no `relatedTarget` (a frame, a blank spot, a redrawn row) is read on the next turn from `document.activeElement`; `<body>` counts as staying.

Related: docs/memory/ui/main-menu-states.md, docs/memory/ui/settings-modal-layout.md
