---
name: main-menu-icons
description: Main menu icons: the rows' one-line drawings (pg_menu_icon()) on a tile in their category's colour (pg_menu_icon_tile(), pg_menu_category_tone()), the seven tones, the panel entries' tiles
module: output
---

## Which icons are not Bootstrap Icons?
- Only the rows of the panel's main menu (`output_menu()`): each item names its drawing with `'menu_icon' => '<name>'`, drawn by `pg_menu_icon($name, '', '')` (`includes/fn/output.php`). Everything else stays `bi`, but a place citing a row draws its icon (`main-menu-icon-cite.md`).
- Drawings copied from `ui/main-menu-icons-drawing/icons.js` (owner 2026-11-22, `workflow/decisions-made-panel-17.md`): one line family, 24-unit grid, `currentColor`, stroke 1.7, one colour. Parts that move carry `data-m` (the move), the Automation flows dot `data-l`. Which name takes which drawing, its idea and its move: `main-menu-icon-rows.md`. The drawing folder stays as the reference.
- Page Design Studio (row 21) is `menu_icon` `design`, the selection frame; the neon sign went (#11202). `design_canvas` is the Artifact Canvas's own two-colour drawing (`design-canvas-icon.md`), not a row's.
- A new main menu item gets its own drawing in `pg_menu_icon()`; without one, a `bi` icon (`'icon'`), an svg or a letter, on the tile. `tests/menu_panel_test.php` checks every `menu_icon` name of `output_menu()` has a drawing.

## What colour is a row?
- The colour of its category in `pg_menu_categories()`, not of its module class: `pg_menu_category_tone($slot)` gives `top` (the untitled block, and a row no category lists), `content`, `design`, `ecommerce`, `accounting`, `marketing` or `system`.
- `backend.src.css`, block "Main menu icons", has one line a category: `:is(#menu, .pg-look-mi) [data-pg-menu-tone="…"]` sets `--pg-menu-tone` to blue, teal, rose, amber, lime, violet or grey (`--pg-tone-*`, `light-dark()` so both themes). A new category needs its line; the test checks each.
- The row's icon sits on `pg_menu_icon_tile()`: `<span class="pg-mi-tile me-3" data-pg-menu-tone="…">`, 32 px (`--pg-menu-tile`, 2rem), radius 10 px, the 1.2rem (19 px) icon (`--pg-menu-icon`) in the tone on a 13% tint with a 16% inner line. The open row's tile is filled: `main-menu-open-row.md`. The row is 40 px high, its label 13.5 px; collapsed, 11 px item padding centres the tile on the 66 px rail (`--pg-rail-w`).
- 1.2rem since #11271: the 15 px icons were too small to tell apart (`workflow/decisions-made-panel-19.md`).
- The Workspace's pin actions (My Notes, Incoming e-mail) use the same tile.

## Which icons do the panel entries carry?
- Bootstrap Icons, always `bi bi-<name> bi-me-2`. A list or screen: the outline icon of what it lists, one icon per subject across the menu and never the same one for two subjects. Create/Add: always `bi-plus-lg`. Reports: one chart shape each. No `-fill` or half-filled glyphs. A link that leaves the panel: `box-arrow-up-right`. A module's own settings: `sliders`. `bi-stars` stays the AI mark.
- The System tools tools take their icons from `includes/settings/registry.php`, so the screen, the menu and the search box show the same ones; the group names are headings without icons (`settings-system-tools.md`).
- A jump point inside a screen (the Visitors sections): no icon, `.pg-menu-anchor` (`visitors-screen-look.md`).
- On a tile like the rows': a panel carries its row's category (`data-pg-menu-tone`, passed to `pg_menu_panel()` and `pg_menu_other_markup()`), the glyph in the tone on a 13% tint, the same 32 px tile and 1.2rem glyph; an entry is 40 px high.
- Bootstrap Icons' `::before` rule is unlayered and beats `@layer custom`, so its `line-height: 1` stays: the tile centres the glyph with top padding, not line-height.

## How do they move?
- `ui/main-menu-icon-motion.md`: one move per icon on hover and focus, the live flows dot and Calendars day.

Related: docs/memory/ui/main-menu-icon-rows.md, docs/memory/ui/main-menu-icon-motion.md, docs/memory/ui/main-menu-states.md, docs/memory/ui/main-menu-categories.md
