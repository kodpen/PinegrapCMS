---
name: main-menu-open-row
description: How the main menu's open row and a panel's current entry are told: the tile filled with the category's tone, the drawing white (dark: the menu's ground), the quiet row fill, hover lighter
module: output
---

## How is the open row told?
- Owner (2026-11-28, `workflow/decisions-made-panel-20.md`): by its tile filled with the category's tone (`--pg-mi-tint`/`--pg-mi-line` 100%, so the hiding parts `.pg-mi-h` take the tone too), the drawing `--pg-mi-on`: white in light, the menu's ground (`--pg-navbar-bg`) in dark; a soft shadow in the tone under it in light only.
- The row takes `--pg-quiet` and `--pg-fg-emphasis` at 600; the pale blue fill and blue words are gone: users took them for one more blue tile. On the rail (above 1024 px, not drawer-only) the row has no fill: the filled tile alone tells it.
- Hover is `--pg-hover`, lighter than the quiet fill, and never fills a tile.
- Every tone passes 3:1 for the drawing in both themes (white 3.56:1 on light teal is the lowest); `tests/menu_panel_test.php` computes them. A tone that fails gets a darker fill, never a darker drawing.
## Where is it shown and tested?
- Design examples' "Menu row icon" shows a Products row at rest beside the open one (`.pg-look-mi-row`, `.pg-look-mi-open`).
- `tests/menu_panel_test.php` (`test_menu_open_row_is_told_by_a_filled_tile`) pins the rules; `backend.src.css` blocks "Main menu icons" and the row's `.list-group-item-action.active` rule hold them.

## How is a panel's current entry told?
- The same way: `a.pg-menu-current` takes `--pg-quiet` and weight 600 with the body emphasis colour (no blue ink); its `::before` tile is filled with the tone and the glyph takes `--pg-mi-on`.

Related: docs/memory/ui/main-menu-icons.md, docs/memory/ui/main-menu-panels.md, docs/memory/workflow/decisions-made-panel-20.md
