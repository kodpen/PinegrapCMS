---
name: explorer-selection-bar-menu
description: The file manager's selection bar "More actions" menu is placed by Floating UI (fixed strategy), not displayed statically: why, and the rule for a menu in a clipped or bottom bar
---

## How is the selection bar's More actions menu placed?
- `selectionBarHtml()` in `view_folders.php` (folders and `view_product_groups.php`, which includes it) prints the three-dot toggle with `data-bs-placement="top-end"` and `data-bs-strategy="fixed"`, and no `data-bs-display="static"`.
- Bootstrap 6's Menu then positions it with Floating UI: flip and shift keep it inside the window, so it opens above the button and stays inside the right edge, on a desktop and at 375 px.

## Why not `data-bs-display="static"` like the toolbar menus?
- A static menu gets no placement at all: Bootstrap ignores `data-bs-placement` and writes no `data-bs-placement` on the box, so the box sat after its button, below the bar.
- The bar is the last row of `#explorer_app`, which clips its overflow, and sits just above the footer: the menu dropped out of the window and only a sliver showed under the button.
- `.pg-menu-end` (`right: 0`) only applies inside `.pg-toolbar` / `#button_bar`, so the box also ran past the right edge.
- The fixed strategy takes the box out of the app shell's clip; the menu stays in its wrapper, so the delegated click listener on `#explorer_statusbar` still gets its items.

## Rule for other menus?
- A menu whose toggle sits in a bottom bar, or inside a box that clips its overflow, is placed by Floating UI (`data-bs-strategy="fixed"`), not static. Static display suits menus hanging down from a top toolbar whose CSS places them.
- Checked (2026-11): no other panel toggle combined a `top` placement with static display.
