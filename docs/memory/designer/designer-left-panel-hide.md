---
name: designer-left-panel-hide
description: The Page Design Studio toolbar's menu button (#sd-left-toggle) that hides the whole left panel and its rail (sd-hide-left, pinegrap_designer_hide_left) and how it sits with docking.
---

## What does the menu button do?
- `#sd-left-toggle` (`bi-layout-sidebar`) is the first item of the designer toolbar (`.sd-toolbar-tabs`), outside `.sd-toolbar-pages`, so the component editor (`$sd_component`, page group hidden) and read-only mode keep it.
- One press hides the left panel (`#sd-components`: Components / Shared / System tabs, palette, search, Overview tree) and the left rail together; the canvas takes the whole width. The next press brings the panel back.
- Title and `aria-label` follow the state: "Hide the left panel" / "Show the left panel"; `aria-expanded` is `true` while shown. The command palette (Ctrl+Shift+P) has the same toggle (`act_left_panel`). No keyboard shortcut of its own.
- Added in #8972, because the panel header's "|<" dock button only parks the panel on its 32px rail and never removes it.

## How is the hidden state kept?
- One class on `.sd-body`: `sd-hide-left`. CSS `.sd-body.sd-hide-left > .sd-panel-left, > .sd-rail-left { display:none }` sits after the docking rules with equal specificity, so it wins without `!important`. No inline width or display is written.
- `localStorage` key `pinegrap_designer_hide_left` (`'1'` / `'0'`), read and written in try/catch; applied at load by `_sdLeftHideInit()`, called at the end of `initPanelDocking()`.

## How does it live with docking?
- Docking state (`sd-dock-left`, `sd-dock-forced`, `pinegrap_designer_dock_left`) is untouched underneath; `_sdDockApply()` on resize keeps working while hidden.
- Hiding closes the left drawer first (`_sdDockSetOpen('left', false)`).
- Showing a docked panel (forced below `_SD_DOCK_BP` or chosen) also opens its drawer, so one press leaves the palette on screen at every width; `_sdDockSetOpen` closes the other drawer and, when forced, the bottom sheet: one layer at a time holds.
- `_sdDockSetOpen('left', true)` while hidden shows the panel first (`_sdLeftSetHidden(false, true)`), so any opener of the left drawer brings the panel back.
- The canvas frame's width label ("1400px · XXL") follows by the frame's own ResizeObserver; the selection's size badge is re-placed with `_positionSizeBadge()`.
