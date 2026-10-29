---
name: main-menu-drawer-focus
description: The phone main menu drawer (below lg) is inert while shut and Escape gives focus back to the bar button; the symptom it fixed
---

## How does the shut phone drawer stay out of reach?
- The stylesheet only slides it off screen (`translateX(-100%)`, no `visibility`), so its rows stayed in the tab order and the reading order: Tab from the bar walked every off-screen row first. `paintMenuInert()`, called at the top of `paintMenuToggle()` (open, close, `paintMenuState()`, `syncMenuMode()`), sets `inert` on `.software-sidebar` in drawer mode while shut and removes it otherwise; wide screens are never inert. `pgMenu.openPanel()` opens the drawer before it focuses a row, so `inert` is already gone.
- Escape that closes the drawer puts the focus on `#menu_mode_toggle` when it was inside the sidebar; a link click or a header menu closing it does not move focus (`tests/panel_drawer_focus_test.php`).

Related: docs/memory/ui/main-menu-states.md, docs/memory/ui/settings-modal-layout.md
