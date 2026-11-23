---
name: main-menu-icons-redraw
description: Plan (owner 2026-11-22): main menu row icons redrawn as one line family on category-coloured tiles with one hover move each; the System tools row's panel as one list; steps MI1 to MI3
priority: 6
---

## What is asked?
- Owner (2026-11-22, agent channel): the rows' moving, many-coloured icons, their sizes and their meanings did not fit. Shown two rounds of drawings he accepted the second; the System tools row lists every tool in one panel (poll 220). His words and what binds: `workflow/decisions-made-panel-17.md`.

## Where is the drawing?
- `ui/main-menu-icons-drawing/`. `icons.js` is the source: `window.ICONS` holds every icon (the SVG body on a 24-unit grid, the moving parts named by `data-m`, the category class `g-…`), `window.ICON_CSS` the moves.
- `icons.html` (`icons-dark.jpg`): each icon beside today's. `motion.html` (`menu-motion-dark.gif`): the menu with every move. `tools.html` (`tools-dark.jpg`): the System tools panel.
- `menu.html`, the first round, holds only for the measures: board M3 the rail, M4 an opened panel and a row's four states. Its glyphs are superseded by `icons.js`.
- The drawing wins over these words, but for its Turkish.

## What does a row look like?
- Row 34 px high, label 13.5 px. A 26 px tile, radius 8 px, holds the 15 px icon: the icon in the category's colour, the tile a 13% tint of it with a 16% inner line (`.ti`). On the rail the tile alone.
- Category colours: the untitled block blue, Content teal, Design rose, Ecommerce amber, Accounting lime, Marketing violet, System grey, from the panel's tone tokens, in both themes. A row takes the colour of its category in `pg_menu_categories()`, not of its module class.
- The current row: a stronger tile and the label at weight 600. Hover changes the row's ground and plays the icon's move; the icon does not grow or change colour.
- The structure stays: arrows, badges, the live count, the rail, the panels and their scripts are not touched (`ui/main-menu-overview.md`).

## Which icon does each row get, and how does it move?
- `plans/main-menu-icons-redraw-rows.md`.

## What happens to the panel entries' icons?
- They stay Bootstrap Icons on a tile. The tone is the row's category, so `data-pg-menu-tone` carries the category and the tone lines of `backend.src.css` shrink to seven.

## Which steps? (each enters `includes/fn/output.php` and `assets/css/backend.src.css`: one worker at a time)
- MI1 (M): the row's tile, the category colours of rows and panels, `pg_menu_icon()` redrawn from `icons.js` for every name, at rest. Done (#11202): `pg_menu_category_tone()`, `pg_menu_icon_tile()`, Page Design Studio's `menu_icon` is `design`; the old moves were deleted, so MI2 writes the new ones from `ICON_CSS`.
- MI2 (M): the moves and the live parts; the clock's script leaves `backend.src.js`; Design examples gets the pattern "Menu row icon" (a tile in each category colour, one icon moving).
- MI3 (S): the System tools panel as one list (`pg_menu_tools_markup()`: a heading per group, its tools under it, no entry for the screen, no second-level panels), then the close-out.
- Tests: every `menu_icon` name of `output_menu()` has a drawing and every category a tone; the menu's tests that pin markup a step redraws are rewritten to it, never dropped.
- Check of a step, on a sandbox with Workspace and ERP on: 1440 and 390 px, both themes, the rail and the wide menu, an opened panel, a row with a badge, a pinned row, reduced motion, the keyboard.

## What does the close-out do?
- Rewrites `ui/main-menu-icons.md`, `main-menu-communication-icon.md`, `main-menu-states.md`, the icon part of `main-menu-automation-flows-row.md` and the menu part of `settings-system-tools.md` to what was built.
- Keeps `ui/main-menu-icons-drawing/` as the reference and deletes this plan's two files.

Related: docs/memory/plans/main-menu-icons-redraw-rows.md, docs/memory/ui/main-menu-icons.md, docs/memory/ui/main-menu-categories.md
