---
name: designer-canvas-bar
description: The canvas bar above the Page Design Studio's canvas (DP22, #10205): the Design | Prototype switch, screen sizes and zoom, the View menu with its badge, Find and History; its measures
---

## What is in the bar?
- Built in `createCanvasIframe()` as `#sd-bp-bar` (`.sd-bp-bar`), three zones with a line between them (`.sd-bp-zsep`). The ids `#sd-vb-*` and `#sd-bp-label` are the old ones, so every handler and shortcut still reaches them.
- Zone 1, `.sd-bp-mode`: the switch Design | Prototype (`#sd-vb-design`, `#sd-vb-proto`). Prototype is a mode of the same bar, not a button in its row (`designer-canvas-bar-prototype.md`, `_sdCanvasBarMode()`).
- Zone 2, `.sd-bp-canvas`: the six screen sizes as one single-choice group (`.sd-bp-sizes`, 375, 576, 768, 992, 1200, full), the same as a list for a narrow bar (`#sd-bp-size-select`), the width as text (`#sd-bp-label`, "1400 px · XXL"), then zoom −, the percentage (click resets to 100 %), + and Fit (`#sd-vb-fit`). An e-mail design shows its 600 and 375 widths in the group's place (`_sdEmailCanvasSync()`).
- Zone 3, `.sd-bp-tools`: "Canvas view" (`#sd-vb-view`) opens the View menu; Find & Replace (`#sd-vb-find`, Ctrl+F) and History (`#sd-vb-snapshots`) as icon buttons.

## What does the View menu hold?
- `#sd-vb-view-menu`, on `pgUI.overlayHost()` (a menu inside the folding bar would be cut). Switch rows named by what they do: Outline (`#sd-vb-outline`, key O), Snap to grid (8 px) (`#sd-vb-grid`), Dark canvas (`#sd-vb-theme`), Play animations (`#sd-vb-motion`), and Prototype comments when that part is on.
- A state is never written as a name: the old "Light" button with the tip "Dark canvas" became the switch "Dark canvas".
- The menu button's badge counts the switches that are on and shown (`_sdCanvasBarBadge()`, `_sdCanvasBarSync()`).
- Preview, Open on the site, Play as a prototype and Share are not here: they are the Preview menu beside Publish (`designer-preview-menu.md`).

## Which measures?
- Bar at least 44 px, `--sd-input-bg` with a 1 px bottom line; a control 28 px; 6 px between controls of a zone; between zones a 1 px line, 20 px high, with 12 px each side.
- The screen sizes are one group of six 28 px segments, the width text after it. The drawing fits the whole bar into 930 px (a 1440 window with both side columns open).
- A switch, an action and a mode look different: the mode is a segmented pair, a switch a menu row with its state, an action an icon button.

## How does it give way when narrow?
- By its own width, never the window's: the width text goes first, then the sizes fold into the list, then the labels; then the zones wrap. No control is ever removed or left under a side column (`_sdCanvasBarFold()`, `designer-narrow-screen-bars.md`).
- Tests: `tests/designer_canvas_bar_test.php` (the badge from the switches, the fold level by width).
